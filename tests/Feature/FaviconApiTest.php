<?php

use App\Models\Favicon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('favicons');
    RateLimiter::clear('favicon-refresh:127.0.0.1:example.com');
    RateLimiter::clear('favicon-refresh:127.0.0.1:www.example.com');
});

function samplePng(int $size = 32, int $r = 220, int $g = 40, int $b = 40): string
{
    $image = imagecreatetruecolor($size, $size);
    $color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

function fakeExampleSite(?string $html = null, ?string $iconBody = null): void
{
    $iconBody ??= samplePng(64, 10, 120, 200);
    $html ??= <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head>
            <link rel="icon" href="/icon.png" sizes="64x64">
        </head>
        <body>Example</body>
        </html>
        HTML;

    Http::fake([
        'https://example.com/' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        'https://example.com/icon.png' => Http::response($iconBody, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response($iconBody, 200, ['Content-Type' => 'image/png']),
        'https://www.example.com/' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        'https://www.example.com/icon.png' => Http::response($iconBody, 200, ['Content-Type' => 'image/png']),
        'https://www.example.com/favicon.ico' => Http::response($iconBody, 200, ['Content-Type' => 'image/png']),
    ]);
}

test('it returns an image for a domain and caches subsequent hits', function () {
    fakeExampleSite();

    $first = $this->get('/i/example.com');

    $first->assertSuccessful();
    expect($first->headers->get('Content-Type'))->toStartWith('image/');

    Http::fake([
        '*' => Http::response('should-not-be-called', 500),
    ]);

    $second = $this->get('/i/example.com');

    $second->assertSuccessful();
    expect($second->headers->get('Content-Type'))->toStartWith('image/');
    expect(Favicon::query()->where('domain', 'example.com')->count())->toBe(1);
});

test('it discovers an icon from html link rel', function () {
    $png = samplePng(96, 0, 180, 90);

    Http::fake(function (Request $request) use ($png) {
        if ($request->url() === 'https://example.com/') {
            return Http::response(
                '<html><head><link rel="apple-touch-icon" sizes="180x180" href="/apple.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            );
        }

        if ($request->url() === 'https://example.com/apple.png') {
            return Http::response($png, 200, ['Content-Type' => 'image/png']);
        }

        return Http::response('missing', 404);
    });

    $response = $this->get('/i/example.com');

    $response->assertSuccessful();
    expect(Favicon::query()->where('domain', 'example.com')->value('source_url'))
        ->toBe('https://example.com/apple.png')
        ->and(Favicon::query()->where('domain', 'example.com')->value('status'))
        ->toBe('ok');
});

test('it falls back to favicon.ico when html has no icons', function () {
    $png = samplePng(32, 90, 90, 200);

    Http::fake([
        'https://example.com/' => Http::response('<html><head><title>x</title></head></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/favicon.ico' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $this->get('/i/example.com')->assertSuccessful();

    expect(Favicon::query()->where('domain', 'example.com')->value('source_url'))
        ->toBe('https://example.com/favicon.ico');
});

test('it falls back to staravatars png when the site has no icon', function () {
    $png = samplePng(64, 30, 80, 200);

    Http::fake([
        'https://example.com/*' => Http::response('nope', 404),
        'http://example.com/*' => Http::response('nope', 404),
        'https://staravatars.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get('/i/example.com');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toStartWith('image/png');

    $favicon = Favicon::query()->where('domain', 'example.com')->first();

    expect($favicon)->not->toBeNull()
        ->and($favicon->status)->toBe('fallback')
        ->and($favicon->source_url)->toContain('staravatars.com')
        ->and($favicon->source_url)->toContain('format=png')
        ->and($favicon->source_url)->toContain('initials=E')
        ->and($favicon->source_url)->not->toContain('text-size')
        ->and($favicon->content_type)->toBe('image/png');
});

test('it resizes a staravatars fallback with the sz query parameter', function () {
    $png = samplePng(64, 30, 80, 200);

    Http::fake([
        'https://example.com/*' => Http::response('nope', 404),
        'http://example.com/*' => Http::response('nope', 404),
        'https://staravatars.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get('/i/example.com?sz=32');

    $response->assertSuccessful();

    $image = imagecreatefromstring($response->getContent());
    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBe(32)
        ->and(imagesy($image))->toBe(32);
    imagedestroy($image);
});

test('it serves a letter fallback when site and staravatars both fail', function () {
    Http::fake([
        'https://example.com/*' => Http::response('nope', 404),
        'http://example.com/*' => Http::response('nope', 404),
        'https://staravatars.com/*' => Http::response('nope', 404),
    ]);

    $response = $this->get('/i/example.com');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toStartWith('image/png');
    expect(Favicon::query()->where('domain', 'example.com')->value('status'))->toBe('fallback')
        ->and(Favicon::query()->where('domain', 'example.com')->value('source_url'))->toBeNull();
});

test('it resizes with the sz query parameter', function () {
    fakeExampleSite(iconBody: samplePng(128));

    $response = $this->get('/i/example.com?sz=64');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toStartWith('image/png');

    $image = imagecreatefromstring($response->getContent());
    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBe(64)
        ->and(imagesy($image))->toBe(64);
    imagedestroy($image);
});

test('it resizes ico masters without falling back to a letter tile', function () {
    $png = samplePng(32, 20, 120, 200);
    $ico = pack('vvv', 0, 1, 1)
        .pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22)
        .$png;

    Http::fake([
        'https://example.com/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/favicon.ico' => Http::response($ico, 200, ['Content-Type' => 'image/x-icon']),
    ]);

    $response = $this->get('/i/example.com?sz=64');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toStartWith('image/png');

    $image = imagecreatefromstring($response->getContent());
    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBe(64);
    imagedestroy($image);

    expect(Favicon::query()->where('domain', 'example.com')->value('status'))->toBe('ok')
        ->and(Favicon::query()->where('domain', 'example.com')->value('content_type'))->toBe('image/png');
});

test('it refreshes a favicon and rate limits after five attempts per week', function () {
    fakeExampleSite();

    $this->get('/i/example.com')->assertSuccessful();

    for ($i = 0; $i < 5; $i++) {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><head><link rel="icon" href="/icon-'.$i.'.png" sizes="64x64"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://example.com/icon-'.$i.'.png' => Http::response(samplePng(48, $i * 20, 100, 150), 200, ['Content-Type' => 'image/png']),
            'https://example.com/favicon.ico' => Http::response(samplePng(16), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->delete('/r/example.com')->assertSuccessful();
    }

    $this->delete('/r/example.com')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

test('favicon responses revalidate and change etag after refresh', function () {
    fakeExampleSite(iconBody: samplePng(128, 10, 120, 200));

    $master = $this->get('/i/example.com')->assertSuccessful();
    $sized = $this->get('/i/example.com?sz=64')->assertSuccessful();

    $masterEtag = $master->headers->get('ETag');
    $sizedEtag = $sized->headers->get('ETag');

    expect($masterEtag)->not->toBeEmpty()
        ->and($sizedEtag)->not->toBeEmpty()
        ->and($masterEtag)->not->toBe($sizedEtag);

    expect($master->headers->get('Cache-Control'))
        ->toContain('max-age=0')
        ->toContain('must-revalidate');
    expect($sized->headers->get('Cache-Control'))
        ->toContain('max-age=0')
        ->toContain('must-revalidate');
    expect($master->headers->get('Last-Modified'))->not->toBeEmpty();
    expect($sized->headers->get('Last-Modified'))->not->toBeEmpty();

    $this->get('/i/example.com', ['If-None-Match' => $masterEtag])->assertNotModified();
    $this->get('/i/example.com?sz=64', ['If-None-Match' => $sizedEtag])->assertNotModified();

    $this->travel(1)->second();

    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon-new.png" sizes="64x64"></head></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://example.com/icon-new.png' => Http::response(samplePng(96, 200, 40, 40), 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response(samplePng(16), 200, ['Content-Type' => 'image/png']),
    ]);

    $this->delete('/r/example.com')->assertSuccessful();

    $masterAfter = $this->get('/i/example.com')->assertSuccessful();
    $sizedAfter = $this->get('/i/example.com?sz=64')->assertSuccessful();

    expect($masterAfter->headers->get('ETag'))->not->toBe($masterEtag)
        ->and($sizedAfter->headers->get('ETag'))->not->toBe($sizedEtag);
});

test('favicon refresh is excluded from request forgery protection', function () {
    $excluded = app(PreventRequestForgery::class)
        ->getExcludedPaths();

    expect($excluded)->toContain('r/*');
});

test('it rejects invalid domains and ssrf targets', function (string $domain) {
    $this->get('/i/'.$domain)->assertStatus(422);
})->with([
    'localhost',
    '127.0.0.1',
    '192.168.1.1',
    'invalid',
]);

test('it keeps www and bare domains as distinct cache keys', function () {
    fakeExampleSite();

    $this->get('/i/example.com')->assertSuccessful();
    $this->get('/i/www.example.com')->assertSuccessful();

    expect(Favicon::query()->count())->toBe(2);
});
