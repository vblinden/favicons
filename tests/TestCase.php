<?php

namespace Tests;

use App\Services\Favicons\DomainNormalizer;
use App\Services\Favicons\UrlGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(UrlGuard::class, function ($app): UrlGuard {
            return new class($app->make(DomainNormalizer::class)) extends UrlGuard
            {
                public function firstPublicIp(string $host): ?string
                {
                    $fixtures = [
                        'example.com' => '93.184.216.34',
                        'www.example.com' => '93.184.216.34',
                        'example.org' => '93.184.216.34',
                        'example.net' => '93.184.216.34',
                        'laravel.com' => '76.76.21.21',
                        'staravatars.com' => '104.21.0.1',
                        'popular.com' => '93.184.216.34',
                        'quiet.com' => '93.184.216.34',
                    ];

                    if (isset($fixtures[$host])) {
                        return $fixtures[$host];
                    }

                    return parent::firstPublicIp($host);
                }
            };
        });
    }
}
