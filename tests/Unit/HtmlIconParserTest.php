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

test('it skips svg icon candidates', function () {
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('isAllowedUrl')->never();

    $parser = new HtmlIconParser($guard);

    $icons = $parser->parse(
        '<link rel="icon" href="/logo.svg" type="image/svg+xml">',
        'https://example.com/',
    );

    expect($icons)->toBeEmpty();
});
