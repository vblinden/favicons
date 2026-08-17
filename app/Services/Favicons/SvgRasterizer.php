<?php

namespace App\Services\Favicons;

use Imagick;
use ImagickException;
use Throwable;

class SvgRasterizer
{
    /**
     * Convert SVG markup to PNG bytes when possible.
     *
     * Prefers embedded raster images (safe, no Imagick required). Falls back to
     * Imagick, then optional CLI tools (`rsvg-convert`, `magick`) when available.
     */
    public function toPng(string $svg, int $size = 128): ?string
    {
        $embedded = $this->largestEmbeddedRaster($svg);

        if ($embedded !== null) {
            return $embedded;
        }

        return $this->rasterizeWithImagick($svg, $size)
            ?? $this->rasterizeWithCli($svg, $size);
    }

    public function canRasterize(string $svg): bool
    {
        return $this->largestEmbeddedRaster($svg) !== null
            || $this->imagickAvailable()
            || $this->cliAvailable() !== null;
    }

    public function imagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(Imagick::class);
    }

    private function largestEmbeddedRaster(string $svg): ?string
    {
        if (! preg_match_all(
            '/(?:xlink:)?href\s*=\s*[\'"]data:image\/(png|jpe?g|webp|gif);base64,([^\'"]+)[\'"]/i',
            $svg,
            $matches,
            PREG_SET_ORDER,
        )) {
            return null;
        }

        $best = null;
        $bestLength = 0;

        foreach ($matches as $match) {
            $payload = preg_replace('/\s+/', '', $match[2]) ?? '';
            $binary = base64_decode($payload, true);

            if ($binary === false || $binary === '') {
                continue;
            }

            $length = strlen($binary);

            if ($length <= $bestLength) {
                continue;
            }

            if (@imagecreatefromstring($binary) === false) {
                continue;
            }

            $best = $binary;
            $bestLength = $length;
        }

        return $best;
    }

    private function rasterizeWithImagick(string $svg, int $size): ?string
    {
        if (! $this->imagickAvailable()) {
            return null;
        }

        try {
            $image = new Imagick;
            $image->setBackgroundColor('transparent');
            $image->setResolution($size, $size);
            $image->readImageBlob($svg);
            $image->setImageFormat('png32');
            $image->thumbnailImage($size, $size, true, true);

            $png = $image->getImageBlob();
            $image->clear();
            $image->destroy();

            return $png !== '' ? $png : null;
        } catch (ImagickException|Throwable) {
            return null;
        }
    }

    private function rasterizeWithCli(string $svg, int $size): ?string
    {
        $binary = $this->cliAvailable();

        if ($binary === null) {
            return null;
        }

        $input = tempnam(sys_get_temp_dir(), 'favicon-svg-');
        $output = tempnam(sys_get_temp_dir(), 'favicon-png-');

        if ($input === false || $output === false) {
            return null;
        }

        $svgPath = $input.'.svg';
        $pngPath = $output.'.png';

        try {
            if (file_put_contents($svgPath, $svg) === false) {
                return null;
            }

            $path = $this->executablePath($binary);

            if ($path === null) {
                return null;
            }

            $command = match ($binary) {
                'rsvg-convert' => [
                    $path,
                    '-w', (string) $size,
                    '-h', (string) $size,
                    '-f', 'png',
                    '-o', $pngPath,
                    $svgPath,
                ],
                'magick' => [
                    $path,
                    '-background', 'none',
                    $svgPath,
                    '-resize', "{$size}x{$size}",
                    $pngPath,
                ],
                default => null,
            };

            if ($command === null) {
                return null;
            }

            $process = proc_open(
                $command,
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                null,
                ['bypass_shell' => true],
            );

            if (! is_resource($process)) {
                return null;
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);

            if ($status !== 0 || ! is_file($pngPath)) {
                return null;
            }

            $png = file_get_contents($pngPath);

            return $png !== false && $png !== '' ? $png : null;
        } finally {
            @unlink($input);
            @unlink($output);
            @unlink($svgPath);
            @unlink($pngPath);
        }
    }

    private function cliAvailable(): ?string
    {
        foreach (['rsvg-convert', 'magick'] as $binary) {
            $path = $this->executablePath($binary);

            if ($path !== null) {
                return $binary;
            }
        }

        return null;
    }

    private function executablePath(string $binary): ?string
    {
        $paths = [
            '/opt/homebrew/bin/'.$binary,
            '/usr/local/bin/'.$binary,
            '/usr/bin/'.$binary,
        ];

        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
