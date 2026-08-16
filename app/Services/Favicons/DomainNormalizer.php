<?php

namespace App\Services\Favicons;

class DomainNormalizer
{
    public function normalize(string $domain): string
    {
        $domain = trim($domain);

        if ($domain === '') {
            return '';
        }

        if (! str_contains($domain, '://')) {
            $domain = 'https://'.$domain;
        }

        $parts = parse_url($domain);

        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        return strtolower($parts['host']);
    }

    public function isValid(string $domain): bool
    {
        $normalized = $this->normalize($domain);

        if ($normalized === '' || str_contains($normalized, '/') || str_contains($normalized, ' ')) {
            return false;
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (in_array($normalized, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $normalized)) {
            return false;
        }

        return true;
    }
}
