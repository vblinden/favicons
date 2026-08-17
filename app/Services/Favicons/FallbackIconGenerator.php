<?php

namespace App\Services\Favicons;

use Illuminate\Support\Str;

class FallbackIconGenerator
{
    public function generate(string $domain, ?int $size = null): string
    {
        $size ??= (int) config('favicons.fallback_size');
        $size = max(16, min(512, $size));

        $letter = $this->letterFor($domain);
        $image = imagecreatetruecolor($size, $size);

        if ($image === false) {
            throw new \RuntimeException('Unable to create fallback favicon.');
        }

        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        [$r, $g, $b] = $this->colorFor($domain);
        $background = imagecolorallocate($image, $r, $g, $b);
        $foreground = imagecolorallocate($image, 255, 255, 255);

        $padding = (int) max(1, $size * 0.08);
        imagefilledrectangle($image, $padding, $padding, $size - $padding - 1, $size - $padding - 1, $background);

        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($letter);
        $textHeight = imagefontheight($font);
        $scale = max(1, (int) floor(($size * 0.45) / max($textWidth, $textHeight)));

        $this->drawScaledCharacter(
            $image,
            $letter,
            $font,
            $scale,
            $foreground,
            $size,
        );

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function letterFor(string $domain): string
    {
        $host = preg_replace('/^www\./i', '', $domain) ?? $domain;
        $first = Str::substr($host, 0, 1);

        if ($first === '') {
            return '?';
        }

        return Str::upper($first);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function colorFor(string $domain): array
    {
        $hash = md5($domain);
        $hue = hexdec(substr($hash, 0, 2)) / 255;

        return $this->hslToRgb($hue, 0.45, 0.42);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s == 0) {
            $value = (int) round($l * 255);

            return [$value, $value, $value];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return [
            (int) round($this->hueToRgb($p, $q, $h + 1 / 3) * 255),
            (int) round($this->hueToRgb($p, $q, $h) * 255),
            (int) round($this->hueToRgb($p, $q, $h - 1 / 3) * 255),
        ];
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    /**
     * @param  \GdImage  $image
     */
    private function drawScaledCharacter($image, string $letter, int $font, int $scale, int $color, int $canvasSize): void
    {
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);
        $glyph = imagecreatetruecolor($charWidth, $charHeight);

        if ($glyph === false) {
            return;
        }

        imagesavealpha($glyph, true);
        $clear = imagecolorallocatealpha($glyph, 0, 0, 0, 127);
        imagefill($glyph, 0, 0, $clear);
        imagestring($glyph, $font, 0, 0, $letter, $color);

        $targetWidth = $charWidth * $scale;
        $targetHeight = $charHeight * $scale;
        $destX = (int) (($canvasSize - $targetWidth) / 2);
        $destY = (int) (($canvasSize - $targetHeight) / 2);

        imagecopyresampled(
            $image,
            $glyph,
            $destX,
            $destY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $charWidth,
            $charHeight,
        );

        imagedestroy($glyph);
    }
}
