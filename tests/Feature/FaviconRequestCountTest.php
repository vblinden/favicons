<?php

use App\Models\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('favicons');
    RateLimiter::clear('favicon-fetch:127.0.0.1');
});

test('serving a favicon increments its request count', function () {
    $png = (function () {
        $image = imagecreatetruecolor(32, 32);
        $color = imagecolorallocate($image, 10, 120, 200);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    })();

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    $this->get('/i/example.com')->assertSuccessful();
    $this->get('/i/example.com')->assertSuccessful();
    $this->get('/i/example.com')->assertSuccessful();

    expect(Favicon::query()->where('domain', 'example.com')->value('request_count'))->toBe(3);
});
