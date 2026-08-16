<?php

namespace App\Services\Favicons;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FaviconResolver
{
    public function __construct(
        private DomainNormalizer $normalizer,
        private IcoDecoder $icoDecoder,
        private FallbackIconGenerator $fallbackIconGenerator,
    ) {}

    /**
     * @return array{contents: string, content_type: string, source_url: string|null, width: int|null, height: int|null, status: string}
     */
    public function resolve(string $domain): array
    {
        $candidates = $this->discoverCandidates($domain);

        foreach ($candidates as $candidate) {
            $downloaded = $this->downloadIcon($candidate['url']);

            if ($downloaded === null) {
                continue;
            }

            $normalized = $this->normalizeImage(
                $downloaded['contents'],
                $downloaded['content_type'],
            );

            if ($normalized === null) {
                continue;
            }

            return [
                'contents' => $normalized['contents'],
                'content_type' => $normalized['content_type'],
                'source_url' => $candidate['url'],
                'width' => $normalized['width'],
                'height' => $normalized['height'],
                'status' => 'ok',
            ];
        }

        $starAvatar = $this->fetchStarAvatarFallback($domain);

        if ($starAvatar !== null) {
            return $starAvatar;
        }

        return [
            'contents' => '',
            'content_type' => 'image/png',
            'source_url' => null,
            'width' => null,
            'height' => null,
            'status' => 'fallback',
        ];
    }

    /**
     * @return array{contents: string, content_type: string, source_url: string, width: int|null, height: int|null, status: string}|null
     */
    private function fetchStarAvatarFallback(string $domain): ?array
    {
        if (! (bool) config('favicons.staravatars.enabled', true)) {
            return null;
        }

        $url = $this->starAvatarUrl($domain);
        $downloaded = $this->downloadConfiguredIcon($url);

        if ($downloaded === null) {
            return null;
        }

        $normalized = $this->normalizeImage(
            $downloaded['contents'],
            $downloaded['content_type'],
        );

        if ($normalized === null || ! str_contains($normalized['content_type'], 'png')) {
            return null;
        }

        return [
            'contents' => $normalized['contents'],
            'content_type' => $normalized['content_type'],
            'source_url' => $url,
            'width' => $normalized['width'],
            'height' => $normalized['height'],
            'status' => 'fallback',
        ];
    }

    public function starAvatarUrl(string $domain): string
    {
        $baseUrl = rtrim((string) config('favicons.staravatars.base_url'), '/');
        $size = max(
            (int) config('favicons.min_size'),
            min((int) config('favicons.max_size'), (int) config('favicons.staravatars.size')),
        );

        $query = array_filter([
            'size' => $size,
            'shape' => (string) config('favicons.staravatars.shape', 'rounded'),
            'format' => 'png',
            'initials' => $this->fallbackIconGenerator->letterFor($domain),
        ], fn ($value) => $value !== '' && $value !== null);

        return $baseUrl.'/'.rawurlencode($domain).'?'.http_build_query($query);
    }

    /**
     * Download an icon from a trusted configured URL (skips SSRF host checks).
     *
     * @return array{contents: string, content_type: string}|null
     */
    private function downloadConfiguredIcon(string $url): ?array
    {
        try {
            $response = $this->client()
                ->withOptions(['allow_redirects' => $this->redirectOptions()])
                ->get($url);

            if ($response->failed()) {
                return null;
            }

            $contents = $response->body();

            if ($contents === '') {
                return null;
            }

            $contentType = strtolower((string) ($response->header('Content-Type') ?: ''));
            $contentType = trim(explode(';', $contentType)[0]);

            if ($contentType === '' || str_contains($contentType, 'text/html')) {
                $contentType = $this->guessContentType($url, $contents);
            }

            if (! str_starts_with($contentType, 'image/') && $contentType !== 'image/svg+xml') {
                if (! $this->looksLikeImage($contents)) {
                    return null;
                }

                $contentType = $this->guessContentType($url, $contents);
            }

            return [
                'contents' => $contents,
                'content_type' => $contentType,
            ];
        } catch (ConnectionException|RequestException) {
            return null;
        }
    }

    /**
     * @return list<array{url: string, score: int}>
     */
    public function discoverCandidates(string $domain): array
    {
        $candidates = [];
        $htmlResult = $this->fetchHtml($domain);

        if ($htmlResult !== null) {
            foreach ($this->parseLinkIcons($htmlResult['body'], $htmlResult['final_url']) as $icon) {
                $candidates[] = $icon;
            }
        }

        $candidates[] = [
            'url' => 'https://'.$domain.'/favicon.ico',
            'score' => 10,
        ];

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $unique = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate['url']])) {
                continue;
            }

            if (! $this->isAllowedUrl($candidate['url'])) {
                continue;
            }

            $seen[$candidate['url']] = true;
            $unique[] = $candidate;
        }

        return $unique;
    }

    /**
     * @return array{body: string, final_url: string}|null
     */
    private function fetchHtml(string $domain): ?array
    {
        foreach (['https', 'http'] as $scheme) {
            $url = $scheme.'://'.$domain.'/';

            if (! $this->isAllowedUrl($url)) {
                continue;
            }

            try {
                $response = $this->client()
                    ->withOptions(['allow_redirects' => $this->redirectOptions()])
                    ->get($url);

                if ($response->failed()) {
                    continue;
                }

                $contentType = strtolower((string) $response->header('Content-Type'));

                if ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml')) {
                    continue;
                }

                return [
                    'body' => $response->body(),
                    'final_url' => (string) $response->effectiveUri(),
                ];
            } catch (ConnectionException|RequestException) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return list<array{url: string, score: int}>
     */
    private function parseLinkIcons(string $html, string $baseUrl): array
    {
        $icons = [];

        if (! preg_match_all('/<link\b[^>]*>/i', $html, $matches)) {
            return [];
        }

        foreach ($matches[0] as $tag) {
            $rel = $this->attribute($tag, 'rel');
            $href = $this->attribute($tag, 'href');

            if ($rel === null || $href === null || $href === '') {
                continue;
            }

            $relTokens = preg_split('/\s+/', strtolower($rel)) ?: [];

            if (! $this->isIconRel($relTokens)) {
                continue;
            }

            $absolute = $this->absolutize($href, $baseUrl);

            if ($absolute === null || ! $this->isAllowedUrl($absolute)) {
                continue;
            }

            $sizes = $this->attribute($tag, 'sizes');
            $score = $this->scoreIcon($relTokens, $sizes, $absolute);

            $icons[] = [
                'url' => $absolute,
                'score' => $score,
            ];
        }

        return $icons;
    }

    /**
     * @param  list<string>  $relTokens
     */
    private function isIconRel(array $relTokens): bool
    {
        $allowed = [
            'icon',
            'shortcut',
            'apple-touch-icon',
            'apple-touch-icon-precomposed',
            'mask-icon',
        ];

        foreach ($relTokens as $token) {
            if (in_array($token, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $relTokens
     */
    private function scoreIcon(array $relTokens, ?string $sizes, string $url): int
    {
        $score = 50;

        if (in_array('apple-touch-icon', $relTokens, true) || in_array('apple-touch-icon-precomposed', $relTokens, true)) {
            $score += 40;
        }

        if (in_array('mask-icon', $relTokens, true)) {
            $score += 5;
        }

        if (Str::endsWith(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.svg')) {
            $score += 30;
        }

        $maxSize = $this->parseMaxSize($sizes);

        if ($maxSize !== null) {
            $score += min(200, $maxSize);
        }

        return $score;
    }

    private function parseMaxSize(?string $sizes): ?int
    {
        if ($sizes === null || $sizes === '' || strtolower($sizes) === 'any') {
            return null;
        }

        $max = null;

        foreach (preg_split('/\s+/', $sizes) ?: [] as $token) {
            if (! preg_match('/^(\d+)x(\d+)$/i', $token, $match)) {
                continue;
            }

            $value = max((int) $match[1], (int) $match[2]);
            $max = $max === null ? $value : max($max, $value);
        }

        return $max;
    }

    /**
     * @return array{contents: string, content_type: string}|null
     */
    private function downloadIcon(string $url): ?array
    {
        if (! $this->isAllowedUrl($url)) {
            return null;
        }

        try {
            $response = $this->client()
                ->withOptions(['allow_redirects' => $this->redirectOptions()])
                ->get($url);

            if ($response->failed()) {
                return null;
            }

            $contents = $response->body();

            if ($contents === '') {
                return null;
            }

            $contentType = strtolower((string) ($response->header('Content-Type') ?: ''));
            $contentType = trim(explode(';', $contentType)[0]);

            if ($contentType === '' || str_contains($contentType, 'text/html')) {
                $contentType = $this->guessContentType($url, $contents);
            }

            if (! str_starts_with($contentType, 'image/') && $contentType !== 'image/svg+xml') {
                if (! $this->looksLikeImage($contents)) {
                    return null;
                }

                $contentType = $this->guessContentType($url, $contents);
            }

            return [
                'contents' => $contents,
                'content_type' => $contentType,
            ];
        } catch (ConnectionException|RequestException) {
            return null;
        }
    }

    /**
     * @return array{contents: string, content_type: string, width: int|null, height: int|null}|null
     */
    private function normalizeImage(string $contents, string $contentType): ?array
    {
        if (str_contains($contentType, 'svg') || str_starts_with(ltrim($contents), '<svg') || str_contains($contents, '<svg')) {
            return [
                'contents' => $contents,
                'content_type' => 'image/svg+xml',
                'width' => null,
                'height' => null,
            ];
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false && $this->looksLikeIco($contents)) {
            $image = $this->icoDecoder->toImage($contents);
        }

        if ($image === false) {
            if ($this->looksLikeImage($contents)) {
                return [
                    'contents' => $contents,
                    'content_type' => $contentType !== '' ? $contentType : 'image/x-icon',
                    'width' => null,
                    'height' => null,
                ];
            }

            return null;
        }

        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);

        ob_start();
        imagepng($image);
        imagedestroy($image);
        $png = (string) ob_get_clean();

        return [
            'contents' => $png,
            'content_type' => 'image/png',
            'width' => $width,
            'height' => $height,
        ];
    }

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

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            $ipv4 = gethostbyname($host);

            if ($ipv4 === $host) {
                return false;
            }

            return ! $this->isBlockedIp($ipv4);
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip) && $this->isBlockedIp($ip)) {
                return false;
            }
        }

        return true;
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

        // ::1
        if ($ip === '::1') {
            return true;
        }

        // fc00::/7 unique local, fe80::/10 link-local
        $first = ord($packed[0]);

        if ($first === 0xFC || $first === 0xFD) {
            return true;
        }

        if ($first === 0xFE && (ord($packed[1]) & 0xC0) === 0x80) {
            return true;
        }

        // IPv4-mapped IPv6
        if (str_starts_with($ip, '::ffff:')) {
            $mapped = substr($ip, 7);

            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $this->isBlockedIp($mapped);
            }
        }

        return false;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => (string) config('favicons.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ])
            ->timeout((int) config('favicons.timeout'))
            ->connectTimeout((int) config('favicons.connect_timeout'))
            ->retry(2, 100, function ($exception) {
                return $exception instanceof ConnectionException;
            });
    }

    /**
     * @return array{max: int, track_redirects: bool}
     */
    private function redirectOptions(): array
    {
        return [
            'max' => (int) config('favicons.max_redirects'),
            'track_redirects' => true,
        ];
    }

    private function attribute(string $tag, string $name): ?string
    {
        $pattern = sprintf(
            '/%s\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([^\s>]+))/i',
            preg_quote($name, '/'),
        );

        if (! preg_match($pattern, $tag, $match)) {
            return null;
        }

        return $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : $match[3]);
    }

    private function absolutize(string $href, string $baseUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, 'data:')) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$href;
        }

        if (parse_url($href, PHP_URL_SCHEME)) {
            return $href;
        }

        $base = parse_url($baseUrl);

        if ($base === false || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $origin = $base['scheme'].'://'.$base['host'];

        if (! empty($base['port'])) {
            $origin .= ':'.$base['port'];
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $path = $base['path'] ?? '/';
        $directory = str_contains($path, '/') ? substr($path, 0, strrpos($path, '/') + 1) : '/';

        return $origin.$directory.$href;
    }

    private function guessContentType(string $url, string $contents): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            str_ends_with($path, '.svg') || str_contains($contents, '<svg') => 'image/svg+xml',
            str_ends_with($path, '.png') || str_starts_with($contents, "\x89PNG") => 'image/png',
            str_ends_with($path, '.jpg'), str_ends_with($path, '.jpeg'), str_starts_with($contents, "\xff\xd8\xff") => 'image/jpeg',
            str_ends_with($path, '.gif') || str_starts_with($contents, 'GIF8') => 'image/gif',
            str_ends_with($path, '.webp') || str_contains(substr($contents, 0, 16), 'WEBP') => 'image/webp',
            str_ends_with($path, '.ico') => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }

    private function looksLikeIco(string $contents): bool
    {
        return str_starts_with($contents, "\x00\x00\x01\x00")
            || str_starts_with($contents, "\x00\x00\x02\x00");
    }

    private function looksLikeImage(string $contents): bool
    {
        return str_starts_with($contents, "\x89PNG")
            || str_starts_with($contents, "\xff\xd8\xff")
            || str_starts_with($contents, 'GIF8')
            || str_contains(substr($contents, 0, 16), 'WEBP')
            || $this->looksLikeIco($contents)
            || str_contains($contents, '<svg');
    }
}
