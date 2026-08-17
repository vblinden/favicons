<?php

use App\Services\Favicons\HtmlIconParser;
use App\Services\Favicons\UrlGuard;

test('it classifies prefers-color-scheme media on icon links', function () {
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('isAllowedUrl')->andReturn(true);

    $parser = new HtmlIconParser($guard);

    $icons = $parser->parse(
        '<link rel="icon" href="/light.png" media="(prefers-color-scheme: light)">'
        .'<link rel="icon" href="/dark.png" media="(prefers-color-scheme: dark)">'
        .'<link rel="icon" href="/any.png">',
        'https://example.com/',
    );

    expect($icons)->toHaveCount(3)
        ->and(collect($icons)->firstWhere('url', 'https://example.com/light.png')['theme'])->toBe('light')
        ->and(collect($icons)->firstWhere('url', 'https://example.com/dark.png')['theme'])->toBe('dark')
        ->and(collect($icons)->firstWhere('url', 'https://example.com/any.png')['theme'])->toBe('any');
});

test('it treats unknown or mixed color-scheme media as any', function (string $media, string $expected) {
    $parser = new HtmlIconParser(Mockery::mock(UrlGuard::class));

    expect($parser->colorSchemeFromMedia($media))->toBe($expected);
})->with([
    'empty' => ['', 'any'],
    'print only' => ['print', 'any'],
    'both schemes' => ['(prefers-color-scheme: light), (prefers-color-scheme: dark)', 'any'],
    'dark spaced' => ['(prefers-color-scheme : dark)', 'dark'],
]);
