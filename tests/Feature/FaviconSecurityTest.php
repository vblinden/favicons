<?php

use App\Jobs\RefreshFaviconJob;
use App\Models\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

function securityPng(int $size = 32, int $r = 220, int $g = 40, int $b = 40): string
{
    $image = imagecreatetruecolor($size, $size);
    $color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

test('it does not request private icon urls discovered in html', function () {
    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="http://127.0.0.1/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
        'https://staravatars.com/*' => Http::response(securityPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $this->get('/i/example.com')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
});

test('it does not serve svg payloads from favicon.ico', function () {
    $payload = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

    Http::fake([
        'https://example.com/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/favicon.ico' => Http::response($payload, 200, ['Content-Type' => 'image/svg+xml']),
        'https://staravatars.com/*' => Http::response(securityPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get('/i/example.com');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toStartWith('image/png')
        ->and($response->getContent())->not->toContain('<script>')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('it rate limits cold fetches per ip', function () {
    config(['favicons.fetch_max_attempts' => 2, 'favicons.fetch_decay_seconds' => 60]);

    $png = securityPng();

    Http::fake(function (Request $request) use ($png) {
        if (str_contains($request->url(), 'staravatars.com')) {
            return Http::response('missing', 404);
        }

        if (str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '.png')
            || str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '.ico')) {
            return Http::response($png, 200, ['Content-Type' => 'image/png']);
        }

        return Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        );
    });

    $this->get('/i/example.com')->assertSuccessful();
    $this->get('/i/example.org')->assertSuccessful();
    $this->get('/i/example.net')->assertTooManyRequests();
});

test('it serves a stale icon and queues a refresh after ttl', function () {
    Queue::fake();

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response(securityPng(), 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
    ]);

    $this->get('/i/example.com')->assertSuccessful();

    $this->travel((int) config('favicons.ttl_seconds') + 10)->seconds();

    $this->get('/i/example.com')->assertSuccessful();

    Queue::assertPushed(RefreshFaviconJob::class, fn (RefreshFaviconJob $job) => $job->domain === 'example.com');
});

test('image routes do not start a session', function () {
    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response(securityPng(), 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
    ]);

    $response = $this->get('/i/example.com');

    $response->assertSuccessful();
    expect($response->headers->get('Set-Cookie'))->toBeNull();
});

test('it defaults to the configured square size', function () {
    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" sizes="64x64"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response(securityPng(64), 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
    ]);

    $response = $this->get('/i/example.com');

    $response->assertSuccessful();

    $image = imagecreatefromstring($response->getContent());
    expect($image)->not->toBeFalse()
        ->and(imagesx($image))->toBe((int) config('favicons.default_size'));
    imagedestroy($image);
});

test('a 304 response does not increment request count', function () {
    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon.png' => Http::response(securityPng(), 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response('missing', 404),
    ]);

    $first = $this->get('/i/example.com')->assertSuccessful();
    $etag = $first->headers->get('ETag');

    $this->get('/i/example.com', ['If-None-Match' => $etag])->assertNotModified();

    expect(Favicon::query()->where('domain', 'example.com')->value('request_count'))->toBe(1);
});
