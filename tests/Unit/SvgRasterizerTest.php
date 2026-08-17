<?php

use App\Services\Favicons\SvgRasterizer;
use Tests\TestCase;

uses(TestCase::class);

test('it extracts the largest embedded raster from svg', function () {
    $small = imagecreatetruecolor(8, 8);
    imagefill($small, 0, 0, imagecolorallocate($small, 255, 0, 0));
    ob_start();
    imagepng($small);
    $smallPng = (string) ob_get_clean();

    $large = imagecreatetruecolor(32, 32);
    imagefill($large, 0, 0, imagecolorallocate($large, 0, 255, 0));
    ob_start();
    imagepng($large);
    $largePng = (string) ob_get_clean();

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
        .'<image href="data:image/png;base64,'.base64_encode($smallPng).'"/>'
        .'<image xlink:href="data:image/png;base64,'.base64_encode($largePng).'"/>'
        .'</svg>';

    $out = (new SvgRasterizer)->toPng($svg);

    expect($out)->not->toBeNull();

    $image = imagecreatefromstring($out);
    expect($image)->not->toBeFalse()
        ->and(imagesx($image))->toBe(32)
        ->and(imagesy($image))->toBe(32);
});

test('it extracts multiline base64 embedded jpegs from svg', function () {
    $jpeg = imagecreatetruecolor(20, 20);
    imagefill($jpeg, 0, 0, imagecolorallocate($jpeg, 10, 20, 30));
    ob_start();
    imagejpeg($jpeg);
    $bytes = (string) ob_get_clean();
    $chunked = implode("\n", str_split(base64_encode($bytes), 16));

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
        .'<image xlink:href="data:image/jpeg;base64,'.$chunked.'"/></svg>';

    $out = (new SvgRasterizer)->toPng($svg);

    expect($out)->not->toBeNull();
    expect(@imagecreatefromstring($out))->not->toBeFalse();
});

test('it returns null for vector-only svg without a rasterizer', function () {
    $rasterizer = new SvgRasterizer;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="4"/></svg>';

    if ($rasterizer->canRasterize($svg)) {
        $this->markTestSkipped('An SVG rasterizer is available in this environment.');
    }

    expect($rasterizer->toPng($svg))->toBeNull();
});
