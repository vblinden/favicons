<?php

use App\Services\Favicons\IcoDecoder;
use App\Services\Favicons\ImageNormalizer;

function samplePngBytes(int $size = 16): string
{
    $image = imagecreatetruecolor($size, $size);
    $color = imagecolorallocate($image, 20, 80, 160);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

test('it rejects svg and html that contains svg', function () {
    $normalizer = new ImageNormalizer(new IcoDecoder);

    expect($normalizer->normalize('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'image/svg+xml'))->toBeNull()
        ->and($normalizer->normalize('<html><body><svg></svg></body></html>', 'text/html'))->toBeNull()
        ->and($normalizer->looksLikeSvg('<svg></svg>', ''))->toBeTrue();
});

test('it converts raster bytes to png', function () {
    $normalizer = new ImageNormalizer(new IcoDecoder);
    $png = samplePngBytes();

    $normalized = $normalizer->normalize($png, 'image/png');

    expect($normalized)->not->toBeNull()
        ->and($normalized['content_type'])->toBe('image/png')
        ->and($normalized['width'])->toBe(16)
        ->and($normalized['height'])->toBe(16);
});
