<?php

namespace App\Services\Favicons;

use App\Enums\FaviconFormat;
use App\Enums\FaviconTheme;
use App\Models\Favicon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FaviconStore
{
    public function __construct(
        private FallbackIconGenerator $fallbackIconGenerator,
        private IcoDecoder $icoDecoder,
        private ImageNormalizer $imageNormalizer,
    ) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('favicons.disk'));
    }

    public function find(string $domain, FaviconTheme $theme = FaviconTheme::Default): ?Favicon
    {
        return Favicon::query()
            ->where('domain', $domain)
            ->where('theme', $theme->value)
            ->first();
    }

    public function hasStoredFile(?Favicon $favicon): bool
    {
        return $favicon !== null
            && filled($favicon->storage_path)
            && $this->disk()->exists($favicon->storage_path);
    }

    public function canRasterize(?Favicon $favicon): bool
    {
        if (! $this->hasStoredFile($favicon)) {
            return false;
        }

        $contentType = strtolower((string) $favicon->content_type);
        $path = strtolower((string) $favicon->storage_path);

        if (str_contains($contentType, 'svg') || str_ends_with($path, '.svg')) {
            return false;
        }

        $cacheKey = 'favicon:rasterizable:'.$favicon->id.':'.$favicon->fetched_at?->timestamp;

        return (bool) Cache::remember($cacheKey, 86400, function () use ($favicon) {
            $contents = $this->disk()->get($favicon->storage_path);

            return $contents !== null
                && $contents !== ''
                && ! $this->imageNormalizer->isRejectedContent($contents);
        });
    }

    /**
     * @param  array{contents: string, content_type: string, source_url: string|null, width: int|null, height: int|null, status: string}  $resolved
     */
    public function persist(string $domain, array $resolved, FaviconTheme $theme = FaviconTheme::Default): Favicon
    {
        $existing = $this->find($domain, $theme);

        if ($existing !== null) {
            $this->forgetVariants($existing);
        }

        if ($existing?->storage_path) {
            $this->disk()->delete($existing->storage_path);
        }

        if ($resolved['contents'] === '') {
            $contents = $this->fallbackIconGenerator->generate($domain);
            $contentType = 'image/png';
            $extension = 'png';
            $width = (int) config('favicons.fallback_size');
            $height = $width;
            $status = 'fallback';
            $sourceUrl = null;
        } else {
            $contents = $resolved['contents'];
            $contentType = $resolved['content_type'];
            $extension = $this->extensionFor($contentType);
            $width = $resolved['width'];
            $height = $resolved['height'];
            $status = $resolved['status'];
            $sourceUrl = $resolved['source_url'];
        }

        $path = hash('sha256', $domain.'|'.$theme->value).'.'.$extension;
        $this->disk()->put($path, $contents);

        return Favicon::query()->updateOrCreate(
            [
                'domain' => $domain,
                'theme' => $theme->value,
            ],
            [
                'source_url' => $sourceUrl,
                'storage_path' => $path,
                'content_type' => $contentType,
                'width' => $width,
                'height' => $height,
                'status' => $status,
                'fetched_at' => now(),
            ],
        );
    }

    public function persistPlaceholder(string $domain, FaviconTheme $theme = FaviconTheme::Default): Favicon
    {
        return $this->persist($domain, [
            'contents' => '',
            'content_type' => 'image/png',
            'source_url' => null,
            'width' => null,
            'height' => null,
            'status' => 'fallback',
        ], $theme);
    }

    public function etag(Favicon $favicon, int $size, FaviconFormat $format = FaviconFormat::Png): string
    {
        return '"'.hash(
            'sha256',
            $favicon->domain.'|'.$favicon->theme.'|'.$favicon->fetched_at?->timestamp.'|'.$size.'|'.$format->value.'|'.config('favicons.image_revision'),
        ).'"';
    }

    public function response(
        Favicon $favicon,
        int $size,
        ?string $ifNoneMatch = null,
        bool $recordRequest = false,
        FaviconFormat $format = FaviconFormat::Png,
    ): Response {
        $size = $this->clampSize($size);
        $etag = $this->etag($favicon, $size, $format);
        $headers = $this->headers($favicon, $etag, $format);

        if ($ifNoneMatch === $etag) {
            return response('', 304, $headers);
        }

        $contents = $this->variantContents($favicon, $size, $format);

        if ($recordRequest) {
            defer(fn () => $favicon->recordRequest());
        }

        return response($contents, 200, $headers);
    }

    public function statusHeader(Favicon $favicon): string
    {
        return match (true) {
            $favicon->isLetterFallback() => 'letter',
            $favicon->isFallback() => 'fallback',
            default => 'ok',
        };
    }

    public function resize(Favicon $favicon, int $size, FaviconFormat $format = FaviconFormat::Png): ?string
    {
        $size = $this->clampSize($size);

        if ($this->shouldGenerateLetterFallback($favicon)) {
            return $this->encodeImageBytes(
                $this->fallbackIconGenerator->generate($favicon->domain, $size),
                $format,
            );
        }

        if (! $this->hasStoredFile($favicon)) {
            return $this->encodeImageBytes(
                $this->fallbackIconGenerator->generate($favicon->domain, $size),
                $format,
            );
        }

        $contents = $this->disk()->get($favicon->storage_path);
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            $image = $this->icoDecoder->toImage($contents);
        }

        if ($image === false) {
            return null;
        }

        $resized = $this->resample($image, $size);
        imagedestroy($image);

        if ($resized === null) {
            return null;
        }

        $encoded = $this->encodeGdImage($resized, $format);
        imagedestroy($resized);

        return $encoded;
    }

    private function variantContents(Favicon $favicon, int $size, FaviconFormat $format): string
    {
        $size = $this->clampSize($size);
        $path = $this->variantPath($favicon, $size, $format);

        if ($this->disk()->exists($path)) {
            return (string) $this->disk()->get($path);
        }

        $key = 'favicon:variant:'.$favicon->id.':'.$size.':'.$format->value.':'.$favicon->fetched_at?->timestamp.':'.config('favicons.image_revision');

        $contents = Cache::remember(
            $key,
            (int) config('favicons.variant_cache_seconds'),
            function () use ($favicon, $size, $format) {
                return $this->resize($favicon, $size, $format)
                    ?? $this->encodeImageBytes(
                        $this->fallbackIconGenerator->generate($favicon->domain, $size),
                        $format,
                    );
            },
        );

        $this->disk()->put($path, $contents);

        return $contents;
    }

    private function variantPath(Favicon $favicon, int $size, FaviconFormat $format): string
    {
        return sprintf(
            'variants/%s/%s/%d.%s',
            $favicon->id,
            $favicon->fetched_at?->timestamp ?? '0',
            $size,
            $format->extension(),
        );
    }

    private function forgetVariants(Favicon $favicon): void
    {
        $directory = 'variants/'.$favicon->id;

        if ($this->disk()->exists($directory)) {
            $this->disk()->deleteDirectory($directory);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(Favicon $favicon, string $etag, FaviconFormat $format): array
    {
        $maxAge = max(0, (int) config('favicons.http_max_age'));
        $stale = (int) config('favicons.stale_while_revalidate');

        $headers = [
            'Cache-Control' => "public, max-age={$maxAge}, stale-while-revalidate={$stale}",
            'ETag' => $etag,
            'Content-Type' => $format->contentType(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Favicon-Status' => $this->statusHeader($favicon),
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Content-Disposition' => 'inline; filename="favicon.'.$format->extension().'"',
        ];

        if ($favicon->fetched_at !== null) {
            $headers['Last-Modified'] = $favicon->fetched_at->toRfc7231String();
        }

        return $headers;
    }

    private function clampSize(int $size): int
    {
        return max((int) config('favicons.min_size'), min((int) config('favicons.max_size'), $size));
    }

    private function extensionFor(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'icon') => 'ico',
            default => 'png',
        };
    }

    private function shouldGenerateLetterFallback(Favicon $favicon): bool
    {
        return $favicon->isLetterFallback();
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage|null
     */
    private function resample($image, int $size)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width === $size && $height === $size) {
            $clone = imagecreatetruecolor($size, $size);

            if ($clone === false) {
                return null;
            }

            imagesavealpha($clone, true);
            $transparent = imagecolorallocatealpha($clone, 0, 0, 0, 127);
            imagefill($clone, 0, 0, $transparent);
            imagecopy($clone, $image, 0, 0, 0, 0, $size, $size);

            return $clone;
        }

        if (function_exists('imagescale')) {
            $scaled = imagescale($image, $size, $size, IMG_BICUBIC_FIXED);

            if ($scaled !== false) {
                imagesavealpha($scaled, true);

                return $scaled;
            }
        }

        $canvas = imagecreatetruecolor($size, $size);

        if ($canvas === false) {
            return null;
        }

        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $size, $size, $width, $height);

        return $canvas;
    }

    /**
     * @param  \GdImage  $image
     */
    private function encodeGdImage($image, FaviconFormat $format): ?string
    {
        ob_start();

        $ok = match ($format) {
            FaviconFormat::Webp => function_exists('imagewebp') && imagewebp($image, null, 90),
            FaviconFormat::Png => imagepng($image),
        };

        $contents = (string) ob_get_clean();

        if (! $ok || $contents === '') {
            return null;
        }

        return $contents;
    }

    private function encodeImageBytes(string $pngBytes, FaviconFormat $format): string
    {
        if ($format === FaviconFormat::Png) {
            return $pngBytes;
        }

        $image = @imagecreatefromstring($pngBytes);

        if ($image === false) {
            return $pngBytes;
        }

        $encoded = $this->encodeGdImage($image, $format);
        imagedestroy($image);

        return $encoded ?? $pngBytes;
    }
}
