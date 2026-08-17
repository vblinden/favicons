<?php

namespace App\Services\Favicons;

/**
 * Loads the largest usable frame from a Windows ICO (BMP or embedded PNG).
 */
class IcoDecoder
{
    /**
     * @return \GdImage|false
     */
    public function toImage(string $contents): mixed
    {
        if (strlen($contents) < 6) {
            return false;
        }

        $header = unpack('vreserved/vtype/vcount', substr($contents, 0, 6));

        if ($header === false || $header['reserved'] !== 0 || ! in_array($header['type'], [1, 2], true) || $header['count'] < 1) {
            return false;
        }

        $frames = [];

        for ($i = 0; $i < $header['count']; $i++) {
            $entryOffset = 6 + ($i * 16);

            if (strlen($contents) < $entryOffset + 16) {
                break;
            }

            $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbitCount/VbytesInRes/VimageOffset', substr($contents, $entryOffset, 16));

            if ($entry === false) {
                continue;
            }

            $width = $entry['width'] === 0 ? 256 : $entry['width'];
            $height = $entry['height'] === 0 ? 256 : $entry['height'];
            $size = $entry['bytesInRes'];
            $offset = $entry['imageOffset'];

            if ($size < 1 || $offset < 0 || ($offset + $size) > strlen($contents)) {
                continue;
            }

            $frames[] = [
                'width' => $width,
                'height' => $height,
                'bitCount' => $entry['bitCount'],
                'frame' => substr($contents, $offset, $size),
            ];
        }

        usort($frames, function (array $a, array $b): int {
            $scoreA = $a['width'] * $a['height'];
            $scoreB = $b['width'] * $b['height'];

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            $pngA = str_starts_with($a['frame'], "\x89PNG") ? 1 : 0;
            $pngB = str_starts_with($b['frame'], "\x89PNG") ? 1 : 0;

            if ($pngA !== $pngB) {
                return $pngB <=> $pngA;
            }

            return $b['bitCount'] <=> $a['bitCount'];
        });

        foreach ($frames as $candidate) {
            if (str_starts_with($candidate['frame'], "\x89PNG")) {
                $image = @imagecreatefromstring($candidate['frame']);
            } else {
                $image = $this->bmpFrameToImage($candidate['frame'], $candidate['width'], $candidate['height']);
            }

            if ($image !== false) {
                return $image;
            }
        }

        return false;
    }

    /**
     * @return \GdImage|false
     */
    private function bmpFrameToImage(string $frame, int $width, int $height): mixed
    {
        if (strlen($frame) < 40) {
            return false;
        }

        $header = unpack(
            'VheaderSize/Vwidth/Vheight/vplanes/vbitCount/Vcompression/VsizeImage/Vxppm/Vyppm/VcolorsUsed',
            substr($frame, 0, 40),
        );

        if ($header === false || $header['headerSize'] < 40 || $header['compression'] !== 0) {
            return false;
        }

        $bitCount = $header['bitCount'];

        if (! in_array($bitCount, [1, 4, 8, 24, 32], true)) {
            return false;
        }

        $dibHeight = (int) abs($header['height']);
        $imageWidth = $header['width'] > 0 ? (int) $header['width'] : $width;

        if ($height > 0 && $dibHeight === $height * 2) {
            $imageHeight = $height;
        } elseif ($height > 0 && $dibHeight === $height) {
            $imageHeight = $height;
        } else {
            $imageHeight = max(1, intdiv($dibHeight, 2) ?: $dibHeight);
        }

        $palette = [];
        $pixelOffset = $header['headerSize'];

        if ($bitCount <= 8) {
            $paletteCount = $header['colorsUsed'] > 0 ? $header['colorsUsed'] : (1 << $bitCount);
            $paletteBytes = $paletteCount * 4;

            if (strlen($frame) < $pixelOffset + $paletteBytes) {
                return false;
            }

            for ($i = 0; $i < $paletteCount; $i++) {
                $index = $pixelOffset + ($i * 4);
                $palette[] = [
                    ord($frame[$index + 2]),
                    ord($frame[$index + 1]),
                    ord($frame[$index]),
                ];
            }

            $pixelOffset += $paletteBytes;
        }

        $xorStride = ((int) (($imageWidth * $bitCount + 31) / 32)) * 4;
        $andStride = ((int) (($imageWidth + 31) / 32)) * 4;
        $xorBytes = $xorStride * $imageHeight;
        $andBytes = $andStride * $imageHeight;

        if (strlen($frame) < $pixelOffset + $xorBytes) {
            return false;
        }

        $andOffset = $pixelOffset + $xorBytes;
        $hasAndMask = $bitCount < 32 && strlen($frame) >= $andOffset + $andBytes;

        $image = imagecreatetruecolor($imageWidth, $imageHeight);

        if ($image === false) {
            return false;
        }

        imagesavealpha($image, true);
        imagealphablending($image, false);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        for ($y = 0; $y < $imageHeight; $y++) {
            $row = $pixelOffset + (($imageHeight - 1 - $y) * $xorStride);

            for ($x = 0; $x < $imageWidth; $x++) {
                if ($hasAndMask && $this->andMaskIsTransparent($frame, $andOffset, $andStride, $imageWidth, $imageHeight, $x, $y)) {
                    continue;
                }

                [$r, $g, $b, $a] = $this->pixelAt($frame, $row, $x, $bitCount, $palette);
                $color = imagecolorallocatealpha($image, $r, $g, $b, $a);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        return $image;
    }

    /**
     * @param  list<array{0: int, 1: int, 2: int}>  $palette
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function pixelAt(string $frame, int $row, int $x, int $bitCount, array $palette): array
    {
        if ($bitCount === 32) {
            $index = $row + ($x * 4);
            $b = ord($frame[$index]);
            $g = ord($frame[$index + 1]);
            $r = ord($frame[$index + 2]);
            $a = 127 - (int) floor(ord($frame[$index + 3]) / 2);

            return [$r, $g, $b, $a];
        }

        if ($bitCount === 24) {
            $index = $row + ($x * 3);

            return [ord($frame[$index + 2]), ord($frame[$index + 1]), ord($frame[$index]), 0];
        }

        $paletteIndex = match ($bitCount) {
            1 => (ord($frame[$row + intdiv($x, 8)]) >> (7 - ($x % 8))) & 0x01,
            4 => (ord($frame[$row + intdiv($x, 2)]) >> ($x % 2 === 0 ? 4 : 0)) & 0x0F,
            default => ord($frame[$row + $x]),
        };

        $color = $palette[$paletteIndex] ?? [0, 0, 0];

        return [$color[0], $color[1], $color[2], 0];
    }

    private function andMaskIsTransparent(string $frame, int $andOffset, int $andStride, int $width, int $height, int $x, int $y): bool
    {
        $row = $andOffset + (($height - 1 - $y) * $andStride);
        $byte = $row + intdiv($x, 8);

        if ($byte >= strlen($frame)) {
            return false;
        }

        return ((ord($frame[$byte]) >> (7 - ($x % 8))) & 0x01) === 1;
    }
}
