<?php

namespace App\Services\Favicons;

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

    public function find(string $domain): ?Favicon
    {
        return Favicon::query()->where('domain', $domain)->first();
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
    public function persist(string $domain, array $resolved): Favicon
    {
        $existing = $this->find($domain);

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

        $path = hash('sha256', $domain).'.'.$extension;
        $this->disk()->put($path, $contents);

        $favicon = Favicon::query()->updateOrCreate(
            ['domain' => $domain],
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

        return $favicon;
    }

    public function etag(Favicon $favicon, int $size): string
    {
        return '"'.hash('sha256', $favicon->domain.'|'.$favicon->fetched_at?->timestamp.'|'.$size.'|'.config('favicons.image_revision')).'"';
    }

    public function response(Favicon $favicon, int $size, ?string $ifNoneMatch = null, bool $recordRequest = false): Response
    {
        $size = $this->clampSize($size);
        $etag = $this->etag($favicon, $size);
        $headers = $this->headers($favicon, $etag);

        if ($ifNoneMatch === $etag) {
            return response('', 304, $headers);
        }

        $contents = $this->variantContents($favicon, $size);

        if ($recordRequest) {
            defer(fn () => $favicon->recordRequest());
        }

        return response($contents, 200, $headers);
    }

    public function resize(Favicon $favicon, int $size): ?string
    {
        $size = $this->clampSize($size);

        if ($this->shouldGenerateLetterFallback($favicon)) {
            return $this->fallbackIconGenerator->generate($favicon->domain, $size);
        }

        if (! $this->hasStoredFile($favicon)) {
            return $this->fallbackIconGenerator->generate($favicon->domain, $size);
        }

        $contents = $this->disk()->get($favicon->storage_path);
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            $image = $this->icoDecoder->toImage($contents);
        }

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($size, $size);

        if ($canvas === false) {
            imagedestroy($image);

            return null;
        }

        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $size, $size, $width, $height);
        imagedestroy($image);

        ob_start();
        imagepng($canvas);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }

    private function variantContents(Favicon $favicon, int $size): string
    {
        $size = $this->clampSize($size);

        $key = 'favicon:variant:'.$favicon->id.':'.$size.':'.$favicon->fetched_at?->timestamp.':'.config('favicons.image_revision');

        return Cache::remember(
            $key,
            (int) config('favicons.variant_cache_seconds'),
            function () use ($favicon, $size) {
                return $this->resize($favicon, $size)
                    ?? $this->fallbackIconGenerator->generate($favicon->domain, $size);
            },
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(Favicon $favicon, string $etag): array
    {
        $stale = (int) config('favicons.stale_while_revalidate');

        $headers = [
            'Cache-Control' => "public, max-age=0, must-revalidate, stale-while-revalidate={$stale}",
            'ETag' => $etag,
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Content-Disposition' => 'inline; filename="favicon.png"',
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
        return $favicon->isFallback() && $favicon->source_url === null;
    }
}
