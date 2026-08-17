<?php

namespace App\Services\Favicons;

class UrlGuard
{
    public function __construct(
        private DomainNormalizer $normalizer,
    ) {}

    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isBlockedIp($host);
        }

        if (! $this->normalizer->isValid($host) && ! preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', $host)) {
            return false;
        }

        return $this->firstPublicIp($host) !== null;
    }

    /**
     * @return array<int, mixed>
     */
    public function curlResolveOptions(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return [];
        }

        $host = strtolower($parts['host']);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

        $ip = $this->firstPublicIp($host);

        if ($ip === null) {
            return [];
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $port = $parts['port'] ?? ($scheme === 'http' ? 80 : 443);

        return [
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
        ];
    }

    public function firstPublicIp(string $host): ?string
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            $ipv4 = gethostbyname($host);

            if ($ipv4 === $host || $this->isBlockedIp($ipv4)) {
                return null;
            }

            return $ipv4;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($ip) || $this->isBlockedIp($ip)) {
                return null;
            }
        }

        $first = $records[0]['ip'] ?? $records[0]['ipv6'] ?? null;

        return is_string($first) ? $first : null;
    }

    public function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return true;
        }

        if ($ip === '::1') {
            return true;
        }

        $first = ord($packed[0]);

        if ($first === 0xFC || $first === 0xFD) {
            return true;
        }

        if ($first === 0xFE && (ord($packed[1]) & 0xC0) === 0x80) {
            return true;
        }

        if (str_starts_with($ip, '::ffff:')) {
            $mapped = substr($ip, 7);

            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $this->isBlockedIp($mapped);
            }
        }

        return false;
    }
}
