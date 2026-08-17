<?php

use App\Services\Favicons\DomainNormalizer;
use App\Services\Favicons\UrlGuard;

$guard = fn () => new UrlGuard(new DomainNormalizer);

test('it blocks private, loopback, and link-local addresses', function (string $url) use ($guard) {
    expect($guard()->isAllowedUrl($url))->toBeFalse();
})->with([
    'http://127.0.0.1/icon.png',
    'http://localhost/icon.png',
    'http://10.0.0.1/icon.png',
    'http://192.168.1.1/icon.png',
    'http://169.254.169.254/latest/meta-data/',
    'http://[::1]/icon.png',
    'http://[::ffff:127.0.0.1]/icon.png',
    'ftp://example.com/icon.png',
]);

test('it allows public literal ip addresses', function () use ($guard) {
    expect($guard()->isAllowedUrl('https://1.1.1.1/favicon.ico'))->toBeTrue();
});

test('it reports blocked ips', function (string $ip, bool $blocked) use ($guard) {
    expect($guard()->isBlockedIp($ip))->toBe($blocked);
})->with([
    ['127.0.0.1', true],
    ['10.1.1.1', true],
    ['169.254.169.254', true],
    ['::1', true],
    ['fc00::1', true],
    ['fe80::1', true],
    ['1.1.1.1', false],
    ['8.8.8.8', false],
]);
