<?php

namespace App\Services\Favicons;

use App\Models\Favicon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FaviconStore
{
    public function __construct(
        private FallbackIconGenerator $fallbackIconGenerator,
        private IcoDecoder $icoDecoder,
    ) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('favicons.disk'));
    }

    public function find(string $domain): ?Favicon
    {
        return Favicon::query()->where('domain', $domain)->first();
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

        return Favicon::query()->updateOrCreate(
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
    }

    public function absolutePath(Favicon $favicon): string
    {
        return $this->disk()->path($favicon->storage_path);
    }

    public function contents(Favicon $favicon): string
    {
        return $this->disk()->get($favicon->storage_path);
    }

    public function dataUri(Favicon $favicon, int $size = 64): string
    {
        $size = max((int) config('favicons.min_size'), min((int) config('favicons.max_size'), $size));

        if ($this->shouldGenerateLetterFallback($favicon) || ! $favicon->storage_path || ! is_file($this->absolutePath($favicon))) {
            return 'data:image/png;base64,'.base64_encode(
                $this->fallbackIconGenerator->generate($favicon->domain, $size),
            );
        }

        if ($favicon->content_type === 'image/svg+xml') {
            return 'data:image/svg+xml;base64,'.base64_encode($this->contents($favicon));
        }

        $contents = $this->resize($favicon, $size) ?? $this->contents($favicon);

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    public function response(Favicon $favicon, ?int $size = null): Response
    {
        $etag = '"'.hash('sha256', $favicon->domain.'|'.$favicon->fetched_at?->timestamp.'|'.($size ?? 'master')).'"';

        if (request()->headers->get('If-None-Match') === $etag) {
            return response('', 304, $this->cacheHeaders($etag));
        }

        if ($favicon->content_type === 'image/svg+xml') {
            return response($this->contents($favicon), 200, array_merge($this->cacheHeaders($etag), [
                'Content-Type' => 'image/svg+xml',
            ]));
        }

        if ($size !== null) {
            if ($this->shouldGenerateLetterFallback($favicon)) {
                return response($this->fallbackIconGenerator->generate($favicon->domain, $size), 200, array_merge($this->cacheHeaders($etag), [
                    'Content-Type' => 'image/png',
                ]));
            }

            $resized = $this->resize($favicon, $size);

            if ($resized !== null) {
                return response($resized, 200, array_merge($this->cacheHeaders($etag), [
                    'Content-Type' => 'image/png',
                ]));
            }

            // Unresizable master (rare) — serve original bytes instead of a letter tile.
        }

        $path = $this->absolutePath($favicon);

        if (! is_file($path)) {
            $contents = $this->fallbackIconGenerator->generate($favicon->domain);

            return response($contents, 200, array_merge($this->cacheHeaders($etag), [
                'Content-Type' => 'image/png',
            ]));
        }

        /** @var BinaryFileResponse $response */
        $response = response()->file($path, array_merge($this->cacheHeaders($etag), [
            'Content-Type' => $favicon->content_type,
        ]));

        return $response;
    }

    public function resize(Favicon $favicon, int $size): ?string
    {
        $size = max((int) config('favicons.min_size'), min((int) config('favicons.max_size'), $size));

        if ($this->shouldGenerateLetterFallback($favicon)) {
            return $this->fallbackIconGenerator->generate($favicon->domain, $size);
        }

        if ($favicon->content_type === 'image/svg+xml') {
            return null;
        }

        $contents = $this->contents($favicon);
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

    /**
     * @return array<string, string>
     */
    private function cacheHeaders(string $etag): array
    {
        $maxAge = (int) config('favicons.cache_max_age');
        $stale = (int) config('favicons.stale_while_revalidate');

        return [
            'Cache-Control' => "public, max-age={$maxAge}, stale-while-revalidate={$stale}",
            'ETag' => $etag,
        ];
    }

    private function extensionFor(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'svg') => 'svg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'icon') => 'ico',
            default => 'bin',
        };
    }

    /**
     * Letter tiles are only used when no remote fallback (e.g. Star Avatars) was stored.
     */
    private function shouldGenerateLetterFallback(Favicon $favicon): bool
    {
        return $favicon->isFallback() && $favicon->source_url === null;
    }
}
