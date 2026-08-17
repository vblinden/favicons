<?php

use App\Services\Favicons\IcoDecoder;

function decoderPng(int $size = 32, int $r = 20, int $g = 120, int $b = 200): string
{
    $image = imagecreatetruecolor($size, $size);
    $color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

test('it prefers a later 32-bit frame over an earlier paletted frame of the same size', function () {
    $png = decoderPng(32, 10, 180, 40);
    $badBmp = str_repeat("\0", 40);
    $offset0 = 6 + (2 * 16);
    $offset1 = $offset0 + strlen($badBmp);

    $ico = pack('vvv', 0, 1, 2)
        .pack('CCCCvvVV', 32, 32, 16, 0, 1, 4, strlen($badBmp), $offset0)
        .pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), $offset1)
        .$badBmp
        .$png;

    $image = (new IcoDecoder)->toImage($ico);

    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBe(32)
        ->and(imagesy($image))->toBe(32);

    $sampled = imagecolorat($image, 1, 1);
    expect(($sampled >> 16) & 0xFF)->toBe(10)
        ->and(($sampled >> 8) & 0xFF)->toBe(180)
        ->and($sampled & 0xFF)->toBe(40);
    imagedestroy($image);
});

test('it falls back to a smaller png frame when the largest bmp cannot be decoded', function () {
    $png = decoderPng(16, 200, 40, 40);
    $badBmp = str_repeat("\0", 40);
    $offset0 = 6 + (2 * 16);
    $offset1 = $offset0 + strlen($badBmp);

    $ico = pack('vvv', 0, 1, 2)
        .pack('CCCCvvVV', 48, 48, 16, 0, 1, 4, strlen($badBmp), $offset0)
        .pack('CCCCvvVV', 16, 16, 0, 0, 1, 32, strlen($png), $offset1)
        .$badBmp
        .$png;

    $image = (new IcoDecoder)->toImage($ico);

    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBe(16)
        ->and(imagesy($image))->toBe(16);
    imagedestroy($image);
});
