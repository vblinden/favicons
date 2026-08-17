<?php

use App\Models\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('favicons');
    Http::preventStrayRequests();
    RateLimiter::clear('favicon-fetch:127.0.0.1');
});

function qualitySamplePng(int $size = 32, int $r = 20, int $g = 160, int $b = 80): string
{
    $image = imagecreatetruecolor($size, $size);
    $color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

test('it decodes amp entities when fetching html icon urls', function () {
    $png = qualitySamplePng(32, 10, 20, 30);
    $requested = [];

    Http::fake(function (Request $request) use ($png, &$requested) {
        $requested[] = $request->url();

        return match ($request->url()) {
            'https://example.com/' => Http::response(
                '<html><head><link rel="icon" href="https://example.com/icon?t=A&amp;size=32&amp;format=png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://example.com/icon?t=A&size=32&format=png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            default => Http::response('missing', 404),
        };
    });

    $this->get('/i/example.com')->assertSuccessful();

    expect($requested)->toContain('https://example.com/icon?t=A&size=32&format=png')
        ->and($requested)->not->toContain('https://example.com/icon?t=A&amp;size=32&amp;format=png')
        ->and(Favicon::query()->where('domain', 'example.com')->value('source_url'))
        ->toBe('https://example.com/icon?t=A&size=32&format=png');
});

test('it skips the laravel skeleton favicon and uses staravatars instead', function () {
    $stockIco = file_get_contents(base_path('tests/Fixtures/laravel-skeleton-favicon.ico'));
    $star = qualitySamplePng(64, 200, 40, 180);

    expect($stockIco)->not->toBeFalse();

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/logo.svg" type="image/svg+xml"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/logo.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 200, ['Content-Type' => 'image/svg+xml']),
        'https://example.com/favicon.ico' => Http::response($stockIco, 200, ['Content-Type' => 'image/x-icon']),
        'https://staravatars.com/*' => Http::response($star, 200, ['Content-Type' => 'image/png']),
    ]);

    $this->get('/i/example.com')->assertSuccessful();

    $favicon = Favicon::query()->where('domain', 'example.com')->first();

    expect($favicon)->not->toBeNull()
        ->and($favicon->status)->toBe('fallback')
        ->and($favicon->source_url)->toStartWith('https://staravatars.com/')
        ->and(sha1(Storage::disk('favicons')->get($favicon->storage_path)))
        ->not->toBe('4ef41ff73f045d319c84db457971e316d2c5360e');
});

test('it refetches a cached laravel skeleton master on the next request', function () {
    $stockPng = file_get_contents(base_path('tests/Fixtures/laravel-skeleton-favicon.png'));
    $star = qualitySamplePng(64, 30, 140, 220);

    expect($stockPng)->not->toBeFalse();

    $path = hash('sha256', 'example.com').'.png';
    Storage::disk('favicons')->put($path, $stockPng);

    Favicon::factory()->create([
        'domain' => 'example.com',
        'source_url' => 'https://example.com/favicon.ico',
        'storage_path' => $path,
        'content_type' => 'image/png',
        'width' => 32,
        'height' => 32,
        'status' => 'ok',
        'fetched_at' => now(),
        'request_count' => 3,
    ]);

    Http::fake([
        'https://example.com/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/favicon.ico' => Http::response($stockPng, 200, ['Content-Type' => 'image/png']),
        'https://staravatars.com/*' => Http::response($star, 200, ['Content-Type' => 'image/png']),
    ]);

    $this->get('/i/example.com')->assertSuccessful();

    $favicon = Favicon::query()->where('domain', 'example.com')->first();

    expect($favicon->status)->toBe('fallback')
        ->and($favicon->source_url)->toStartWith('https://staravatars.com/');
});

test('it retries fallback masters after the fallback ttl', function () {
    config(['favicons.fallback_ttl_seconds' => 60]);

    $path = hash('sha256', 'example.com').'.png';
    $old = qualitySamplePng(64, 1, 1, 1);
    $fresh = qualitySamplePng(48, 9, 9, 9);

    Storage::disk('favicons')->put($path, $old);

    Favicon::factory()->create([
        'domain' => 'example.com',
        'source_url' => 'https://staravatars.com/example.com?size=64',
        'storage_path' => $path,
        'content_type' => 'image/png',
        'width' => 64,
        'height' => 64,
        'status' => 'fallback',
        'fetched_at' => now()->subMinutes(5),
        'request_count' => 1,
    ]);

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response($fresh, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    $this->get('/i/example.com')->assertSuccessful();

    $favicon = Favicon::query()->where('domain', 'example.com')->first();

    expect($favicon->status)->toBe('ok')
        ->and($favicon->source_url)->toBe('https://example.com/icon.png');
});
