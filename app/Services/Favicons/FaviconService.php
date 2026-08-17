<?php

namespace App\Services\Favicons;

use App\Exceptions\FetchRateLimitedException;
use App\Jobs\RefreshFaviconJob;
use App\Models\Favicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class FaviconService
{
    public function __construct(
        private FaviconResolver $resolver,
        private FaviconStore $store,
    ) {}

    public function getOrFetch(string $domain, string $clientIp): Favicon
    {
        $existing = $this->store->find($domain);

        if ($this->isFresh($existing)) {
            return $existing;
        }

        if ($this->store->hasStoredFile($existing)) {
            RefreshFaviconJob::dispatch($domain);

            return $existing;
        }

        $this->assertCanFetch($clientIp);

        return $this->fetchAndPersist($domain, force: false);
    }

    public function refresh(string $domain): Favicon
    {
        return $this->fetchAndPersist($domain, force: true);
    }

    private function fetchAndPersist(string $domain, bool $force): Favicon
    {
        $lock = Cache::lock('favicon:fetch:'.$domain, (int) config('favicons.fetch_lock_seconds'));

        return $lock->block((int) config('favicons.fetch_lock_seconds'), function () use ($domain, $force) {
            if (! $force) {
                $existing = $this->store->find($domain);

                if ($this->isFresh($existing)) {
                    return $existing;
                }
            }

            $resolved = $this->resolver->resolve($domain);

            return $this->store->persist($domain, $resolved);
        });
    }

    private function isFresh(?Favicon $favicon): bool
    {
        if ($favicon === null || ! $this->store->hasStoredFile($favicon)) {
            return false;
        }

        $ttl = (int) config('favicons.ttl_seconds');

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
