<?php

use App\Jobs\RefreshFaviconJob;
use App\Models\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('favicons');
    Http::preventStrayRequests();
    RateLimiter::clear('favicon-fetch:127.0.0.1');
});

function improvementsPng(int $size = 64, int $r = 10, int $g = 120, int $b = 200): string
{
    $image = imagecreatetruecolor($size, $size);
    $color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

test('it soft-serves a letter placeholder and queues a cold miss when async', function () {
    config([
        'favicons.async_cold_miss' => true,
        'queue.default' => 'database',
    ]);
    Queue::fake();

    Http::fake([
        'https://example.com/*' => Http::response('nope', 404),
        'http://example.com/*' => Http::response('nope', 404),
        'https://staravatars.com/*' => Http::response('nope', 404),
    ]);

    $response = $this->get('/i/example.com')->assertSuccessful();

    expect($response->headers->get('X-Favicon-Status'))->toBe('letter')
        ->and($response->headers->get('Content-Type'))->toStartWith('image/png');

    Queue::assertPushed(RefreshFaviconJob::class, fn (RefreshFaviconJob $job) => $job->domain === 'example.com');
});

test('it persists resized variants on disk', function () {
    $png = improvementsPng(128);

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" sizes="128x128"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    $this->get('/i/example.com?sz=32')->assertSuccessful();

    $favicon = Favicon::query()->where('domain', 'example.com')->first();
    $variantPath = 'variants/'.$favicon->id.'/'.$favicon->fetched_at->timestamp.'/32.png';

    expect(Storage::disk('favicons')->exists($variantPath))->toBeTrue();
});

test('it serves webp when format=webp', function () {
    $png = improvementsPng(64);

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    $response = $this->get('/i/example.com?format=webp')->assertSuccessful();

    expect($response->headers->get('Content-Type'))->toBe('image/webp')
        ->and(str_starts_with($response->getContent(), 'RIFF'))->toBeTrue();
});

test('it discovers icons from a web app manifest', function () {
    $png = improvementsPng(192, 40, 160, 80);

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="manifest" href="/site.webmanifest"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/site.webmanifest' => Http::response(json_encode([
            'icons' => [
                ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ],
        ]), 200, ['Content-Type' => 'application/manifest+json']),
        'https://example.com/icon-192.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    $this->get('/i/example.com')->assertSuccessful();

    expect(Favicon::query()->where('domain', 'example.com')->value('source_url'))
        ->toBe('https://example.com/icon-192.png');
});

test('it serves a google-compatible alias', function () {
    $png = improvementsPng(64);

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    $response = $this->get('/s2/favicons?domain=example.com&sz=64')->assertSuccessful();

    expect($response->headers->get('Content-Type'))->toStartWith('image/png');

    $image = imagecreatefromstring($response->getContent());
    expect($image)->not->toBeFalse()
        ->and(imagesx($image))->toBe(64);
    imagedestroy($image);
});

test('it returns batch icon urls', function () {
    Favicon::factory()->create([
        'domain' => 'example.com',
        'status' => 'ok',
        'source_url' => 'https://example.com/icon.png',
        'fetched_at' => now(),
    ]);

    $response = $this->getJson('/b?domains=example.com,laravel.com&sz=64')
        ->assertSuccessful()
        ->assertJsonPath('sz', 64);

    expect($response->json('icons'))->toHaveCount(2)
        ->and($response->json('icons.0.domain'))->toBe('example.com')
        ->and($response->json('icons.0.status'))->toBe('ok')
        ->and($response->json('icons.0.url'))->toContain('/i/example.com')
        ->and($response->json('icons.1.domain'))->toBe('laravel.com')
        ->and($response->json('icons.1.status'))->toBe('unknown');
});

test('homepage documents the api endpoints', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('Try a domain', false)
        ->assertDontSee('favicon-playground', false)
        ->assertSee('/s2/favicons', false)
        ->assertSee('/b?domains=', false);
});
