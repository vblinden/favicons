<?php

use App\Services\Favicons\IcoDecoder;
use App\Services\Favicons\ImageNormalizer;
use App\Services\Favicons\SvgRasterizer;
use Tests\TestCase;

uses(TestCase::class);

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

function normalizer(): ImageNormalizer
{
    return new ImageNormalizer(new IcoDecoder, new SvgRasterizer);
}

test('it rejects empty svg that cannot be rasterized', function () {
    $normalizer = normalizer();

    expect($normalizer->normalize('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'image/svg+xml'))->toBeNull()
        ->and($normalizer->normalize('<html><body><svg></svg></body></html>', 'text/html'))->toBeNull()
        ->and($normalizer->looksLikeSvg('<svg></svg>', ''))->toBeTrue();
});

test('it converts svg with embedded png to raster png', function () {
    $png = samplePngBytes(24);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
        .'<image xlink:href="data:image/png;base64,'.base64_encode($png).'"/></svg>';

    $normalized = normalizer()->normalize($svg, 'image/svg+xml');

    expect($normalized)->not->toBeNull()
        ->and($normalized['content_type'])->toBe('image/png')
        ->and($normalized['width'])->toBe(24)
        ->and($normalized['height'])->toBe(24);
});

test('it converts raster bytes to png', function () {
    $png = samplePngBytes();

    $normalized = normalizer()->normalize($png, 'image/png');

    expect($normalized)->not->toBeNull()
        ->and($normalized['content_type'])->toBe('image/png')
        ->and($normalized['width'])->toBe(16)
        ->and($normalized['height'])->toBe(16);
});

test('it rejects configured stock icon digests', function () {
    $png = samplePngBytes();
    config(['favicons.rejected_content_sha1' => [sha1($png)]]);

    expect(normalizer()->isRejectedContent($png))->toBeTrue()
        ->and(normalizer()->normalize($png, 'image/png'))->toBeNull();
});
