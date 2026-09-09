<?php

namespace App\Services\Favicons;

use App\Enums\FaviconTheme;
use App\Exceptions\FetchRateLimitedException;
use App\Jobs\RefreshFaviconJob;
use App\Models\Favicon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class FaviconService
{
    public function __construct(
        private FaviconResolver $resolver,
        private FaviconStore $store,
    ) {}

    public function getOrFetch(string $domain, string $clientIp, FaviconTheme $theme = FaviconTheme::Default, ?int $targetSize = null): Favicon
    {
        $existing = $this->store->find($domain, $theme);

        if ($this->isFresh($existing)) {
            return $existing;
        }

        if ($this->store->canRasterize($existing)) {
            RefreshFaviconJob::dispatch($domain, $theme, $targetSize);

            return $existing;
        }

        $this->assertCanFetch($clientIp);

        if ($existing === null && $this->usesAsyncColdMiss()) {
            $placeholder = $this->store->persistPlaceholder($domain, $theme);
            RefreshFaviconJob::dispatch($domain, $theme, $targetSize);

            return $placeholder;
        }

        return $this->fetchAndPersist($domain, $theme, force: false, targetSize: $targetSize);
    }

    public function refresh(string $domain, FaviconTheme $theme = FaviconTheme::Default, ?int $targetSize = null): Favicon
    {
        return $this->fetchAndPersist($domain, $theme, force: true, targetSize: $targetSize);
    }

    private function fetchAndPersist(string $domain, FaviconTheme $theme, bool $force, ?int $targetSize = null): Favicon
    {
        $wait = max(1, (int) config('favicons.fetch_lock_wait_seconds', 5));

        $lock = Cache::lock(
            'favicon:fetch:'.$domain.':'.$theme->value,
            (int) config('favicons.fetch_lock_seconds'),
        );

        try {
            return $lock->block($wait, function () use ($domain, $theme, $force, $targetSize) {
                if (! $force) {
                    $existing = $this->store->find($domain, $theme);

                    if ($this->isFresh($existing)) {
                        return $existing;
                    }
                }

                $resolved = $this->resolver->resolve($domain, $theme, $targetSize);

                return $this->store->persist($domain, $resolved, $theme);
            });
        } catch (LockTimeoutException) {
            throw new FetchRateLimitedException($wait);
        }
    }

    private function usesAsyncColdMiss(): bool
    {
        return (bool) config('favicons.async_cold_miss')
            && config('queue.default') !== 'sync';
    }

    private function isFresh(?Favicon $favicon): bool
    {
        if ($favicon === null || ! $this->store->canRasterize($favicon)) {
            return false;
        }

        $ttl = match (true) {
            $favicon->isLetterFallback() => (int) config('favicons.letter_fallback_ttl_seconds'),
            $favicon->isFallback() => (int) config('favicons.fallback_ttl_seconds'),
            default => (int) config('favicons.ttl_seconds'),
        };

        if ($ttl <= 0) {
            return true;
        }

        return $favicon->fetched_at !== null
            && $favicon->fetched_at->gt(now()->subSeconds($ttl));
    }

    private function assertCanFetch(string $clientIp): void
    {
        $key = 'favicon-fetch:'.$clientIp;
        $maxAttempts = (int) config('favicons.fetch_max_attempts');
        $decay = (int) config('favicons.fetch_decay_seconds');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new FetchRateLimitedException(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, $decay);
    }
}
