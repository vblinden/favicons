<?php

use App\Services\Favicons\HtmlIconParser;
use App\Services\Favicons\UrlGuard;

test('it decodes html entities in icon hrefs', function () {
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('isAllowedUrl')->andReturn(true);

    $parser = new HtmlIconParser($guard);

    $icons = $parser->parse(
        '<link rel="icon" href="https://staravatars.com/demo?t=CW&amp;size=32&amp;format=png" type="image/png">',
        'https://example.com/',
    );

    expect($icons)->toHaveCount(1)
        ->and($icons[0]['url'])->toBe('https://staravatars.com/demo?t=CW&size=32&format=png');
});

test('it keeps svg icon candidates with a high score', function () {
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('isAllowedUrl')->andReturn(true);

    $parser = new HtmlIconParser($guard);

    $icons = $parser->parse(
        '<link rel="icon" href="/logo.svg" type="image/svg+xml">'
        .'<link rel="apple-touch-icon" href="/apple.png">',
        'https://example.com/',
    );

    expect($icons)->toHaveCount(2)
        ->and($icons[0]['url'])->toBe('https://example.com/logo.svg')
        ->and($icons[0]['score'])->toBeGreaterThan($icons[1]['score'])
        ->and($icons[1]['url'])->toBe('https://example.com/apple.png');
});

test('it boosts icons that meet the requested size', function () {
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('isAllowedUrl')->andReturn(true);

    $parser = new HtmlIconParser($guard);

    $icons = $parser->parse(
        '<link rel="icon" href="/small.png" sizes="16x16">'
        .'<link rel="icon" href="/large.png" sizes="128x128">',
        'https://example.com/',
        64,
    );

    $byUrl = collect($icons)->keyBy('url');

    expect($byUrl['https://example.com/large.png']['score'])
        ->toBeGreaterThan($byUrl['https://example.com/small.png']['score']);
});
