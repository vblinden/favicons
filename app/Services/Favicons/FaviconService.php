<?php

namespace App\Services\Favicons;

use App\Models\Favicon;
use Illuminate\Support\Facades\Cache;

class FaviconService
{
    public function __construct(
        private FaviconResolver $resolver,
        private FaviconStore $store,
    ) {}

    public function getOrFetch(string $domain): Favicon
    {
        $existing = $this->store->find($domain);

        if ($this->isUsable($existing)) {
            return $existing;
        }

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

                if ($this->isUsable($existing)) {
                    return $existing;
                }
            }

            $resolved = $this->resolver->resolve($domain);

            return $this->store->persist($domain, $resolved);
        });
    }

    private function isUsable(?Favicon $favicon): bool
    {
        return $favicon !== null
            && $favicon->storage_path
            && $this->store->disk()->exists($favicon->storage_path);
    }
}
