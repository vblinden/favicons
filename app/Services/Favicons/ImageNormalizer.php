<?php

namespace App\Services\Favicons;

class ImageNormalizer
{
    public function __construct(
        private IcoDecoder $icoDecoder,
    ) {}

    /**
     * @return array{contents: string, content_type: string, width: int|null, height: int|null}|null
     */
    public function normalize(string $contents, string $contentType): ?array
    {
        if ($this->looksLikeSvg($contents, $contentType) || $this->isRejectedContent($contents)) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false && $this->looksLikeIco($contents)) {
            $image = $this->icoDecoder->toImage($contents);
        }

        if ($image === false) {
            return null;
        }

        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);

        ob_start();
        imagepng($image);
        imagedestroy($image);

        $png = (string) ob_get_clean();

        if ($this->isRejectedContent($png)) {
            return null;
        }

        return [
            'contents' => $png,
            'content_type' => 'image/png',
            'width' => $width,
            'height' => $height,
        ];
    }

    public function isRejectedContent(string $contents): bool
    {
        /** @var list<string> $hashes */
        $hashes = config('favicons.rejected_content_sha1', []);

        if ($hashes === [] || $contents === '') {
            return false;
        }

        return in_array(sha1($contents), $hashes, true);
    }

    public function guessContentType(string $url, string $contents): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            str_ends_with($path, '.svg') || $this->looksLikeSvg($contents, '') => 'image/svg+xml',
            str_ends_with($path, '.png') || str_starts_with($contents, "\x89PNG") => 'image/png',
            str_ends_with($path, '.jpg'), str_ends_with($path, '.jpeg'), str_starts_with($contents, "\xff\xd8\xff") => 'image/jpeg',
            str_ends_with($path, '.gif') || str_starts_with($contents, 'GIF8') => 'image/gif',
            str_ends_with($path, '.webp') || str_contains(substr($contents, 0, 16), 'WEBP') => 'image/webp',
            str_ends_with($path, '.ico') => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }

    public function looksLikeImage(string $contents): bool
    {
        return str_starts_with($contents, "\x89PNG")
            || str_starts_with($contents, "\xff\xd8\xff")
            || str_starts_with($contents, 'GIF8')
            || str_contains(substr($contents, 0, 16), 'WEBP')
            || $this->looksLikeIco($contents);
    }

    public function looksLikeIco(string $contents): bool
    {
        return str_starts_with($contents, "\x00\x00\x01\x00")
            || str_starts_with($contents, "\x00\x00\x02\x00");
    }

    public function looksLikeSvg(string $contents, string $contentType): bool
    {
        if (str_contains($contentType, 'svg')) {
            return true;
        }

        return str_contains($contents, '<svg');
    }
}
