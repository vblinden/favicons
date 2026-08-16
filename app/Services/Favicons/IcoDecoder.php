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

        $best = null;

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

            $frame = substr($contents, $offset, $size);
            $score = $width * $height;

            if ($best === null || $score > $best['score']) {
                $best = [
                    'score' => $score,
                    'width' => $width,
                    'height' => $height,
                    'frame' => $frame,
                ];
            }
        }

        if ($best === null) {
            return false;
        }

        if (str_starts_with($best['frame'], "\x89PNG")) {
            return @imagecreatefromstring($best['frame']);
        }

        return $this->bmpFrameToImage($best['frame'], $best['width'], $best['height']);
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
            'VheaderSize/Vwidth/Vheight/vplanes/vbitCount/Vcompression',
            substr($frame, 0, 26),
        );

        if ($header === false || $header['headerSize'] < 40) {
            return false;
        }

        $bitCount = $header['bitCount'];
        $dibHeight = (int) abs($header['height']);
        $imageHeight = (int) ($dibHeight / 2);

        if ($imageHeight < 1) {
            $imageHeight = $height;
        }

        $imageWidth = $header['width'] > 0 ? (int) $header['width'] : $width;

        if ($bitCount !== 32 || $header['compression'] !== 0) {
            return false;
        }

        $pixelOffset = $header['headerSize'];
        $pixelBytes = $imageWidth * $imageHeight * 4;

        if (strlen($frame) < $pixelOffset + $pixelBytes) {
            return false;
        }

        $image = imagecreatetruecolor($imageWidth, $imageHeight);

        if ($image === false) {
            return false;
        }

        imagesavealpha($image, true);
        imagealphablending($image, false);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        for ($y = 0; $y < $imageHeight; $y++) {
            for ($x = 0; $x < $imageWidth; $x++) {
                $index = $pixelOffset + ((($imageHeight - 1 - $y) * $imageWidth) + $x) * 4;
                $b = ord($frame[$index]);
                $g = ord($frame[$index + 1]);
                $r = ord($frame[$index + 2]);
                $a = ord($frame[$index + 3]);
                $alpha = 127 - (int) floor($a / 2);
                $color = imagecolorallocatealpha($image, $r, $g, $b, $alpha);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        return $image;
    }
}
