<?php

use App\Services\Favicons\ManifestIconParser;
use App\Services\Favicons\UrlGuard;

test('it parses manifest icon entries', function () {
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('isAllowedUrl')->andReturn(true);

    $parser = new ManifestIconParser($guard);

    $icons = $parser->parse(json_encode([
        'icons' => [
            ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/logo.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
        ],
    ]), 'https://example.com/site.webmanifest');

    expect($icons)->toHaveCount(2)
        ->and($icons[0]['url'])->toBe('https://example.com/icon-192.png')
        ->and($icons[0]['size'])->toBe(192)
        ->and($icons[1]['url'])->toBe('https://example.com/logo.svg')
        ->and($icons[1]['score'])->toBeGreaterThanOrEqual(100);
});

test('it finds manifest urls in html', function () {
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('isAllowedUrl')->andReturn(true);

    $parser = new ManifestIconParser($guard);

    $urls = $parser->manifestUrlsFromHtml(
        '<link rel="manifest" href="/app.webmanifest">',
        'https://example.com/',
    );

    expect($urls)->toBe(['https://example.com/app.webmanifest']);
});
