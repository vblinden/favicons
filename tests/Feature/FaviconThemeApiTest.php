<?php

use App\Enums\FaviconTheme;
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
    RateLimiter::clear('favicon-refresh:127.0.0.1:example.com:default');
    RateLimiter::clear('favicon-refresh:127.0.0.1:example.com:dark');
});

function themedSamplePng(int $r, int $g, int $b, int $size = 64): string
{
    $image = imagecreatetruecolor($size, $size);
    $color = imagecolorallocate($image, $r, $g, $b);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

function fakeThemedExampleSite(): array
{
    $lightPng = themedSamplePng(240, 240, 240);
    $darkPng = themedSamplePng(20, 20, 20);
    $anyPng = themedSamplePng(10, 120, 200);

    $html = <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head>
            <link rel="icon" href="/any.png" sizes="64x64">
            <link rel="icon" href="/light.png" sizes="64x64" media="(prefers-color-scheme: light)">
            <link rel="icon" href="/dark.png" sizes="64x64" media="(prefers-color-scheme: dark)">
        </head>
        <body>Example</body>
        </html>
        HTML;

    Http::fake([
        'https://example.com/' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        'https://example.com/any.png' => Http::response($anyPng, 200, ['Content-Type' => 'image/png']),
        'https://example.com/light.png' => Http::response($lightPng, 200, ['Content-Type' => 'image/png']),
        'https://example.com/dark.png' => Http::response($darkPng, 200, ['Content-Type' => 'image/png']),
        'https://example.com/favicon.ico' => Http::response($anyPng, 200, ['Content-Type' => 'image/png']),
        'https://staravatars.com/*' => Http::response('missing', 404),
    ]);

    return compact('lightPng', 'darkPng', 'anyPng');
}

test('it prefers dark media icons when theme=dark', function () {
    fakeThemedExampleSite();

    $this->get('/i/example.com?theme=dark')->assertSuccessful();

    expect(Favicon::query()->where('domain', 'example.com')->where('theme', 'dark')->value('source_url'))
        ->toBe('https://example.com/dark.png');
});

test('it prefers light media icons when theme=light', function () {
    fakeThemedExampleSite();

    $this->get('/i/example.com?theme=light')->assertSuccessful();

    expect(Favicon::query()->where('domain', 'example.com')->where('theme', 'light')->value('source_url'))
        ->toBe('https://example.com/light.png');
});

test('it prefers theme-agnostic icons by default', function () {
    fakeThemedExampleSite();

    $this->get('/i/example.com')->assertSuccessful();

    expect(Favicon::query()->where('domain', 'example.com')->where('theme', 'default')->value('source_url'))
        ->toBe('https://example.com/any.png');
});

test('it caches dark and default themes separately', function () {
    fakeThemedExampleSite();

    $this->get('/i/example.com')->assertSuccessful();
    $this->get('/i/example.com?theme=dark')->assertSuccessful();

    expect(Favicon::query()->where('domain', 'example.com')->count())->toBe(2)
        ->and(Favicon::query()->where('theme', FaviconTheme::Default->value)->value('source_url'))
        ->toBe('https://example.com/any.png')
        ->and(Favicon::query()->where('theme', FaviconTheme::Dark->value)->value('source_url'))
        ->toBe('https://example.com/dark.png');
});

test('it rejects invalid theme values', function () {
    $this->get('/i/example.com?theme=sepia')->assertStatus(422);
});

test('the leaderboard aggregates request counts across themes', function () {
    Favicon::factory()->requested(4)->create(['domain' => 'shared.example']);
    Favicon::factory()->dark()->requested(6)->create(['domain' => 'shared.example']);
    Favicon::factory()->requested(3)->create(['domain' => 'other.example']);

    $this->get(route('leaderboard'))
        ->assertSuccessful()
        ->assertSeeInOrder([
            'shared.example',
            'other.example',
        ], false)
        ->assertSee('10', false)
        ->assertSee('3', false);
});
