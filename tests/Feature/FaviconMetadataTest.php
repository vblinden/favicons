<?php

use App\Models\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('favicons');
    Http::preventStrayRequests();
    RateLimiter::clear('favicon-fetch:127.0.0.1');
});

test('it returns favicon metadata as json', function () {
    $image = imagecreatetruecolor(48, 48);
    $color = imagecolorallocate($image, 12, 34, 56);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);
    $png = (string) ob_get_clean();

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" sizes="48x48"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    $this->getJson('/m/example.com')
        ->assertSuccessful()
        ->assertJsonPath('domain', 'example.com')
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('source_url', 'https://example.com/icon.png')
        ->assertJsonPath('width', 48)
        ->assertJsonPath('height', 48);

    expect(Favicon::query()->where('domain', 'example.com')->exists())->toBeTrue();
});
