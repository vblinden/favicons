<?php

namespace App\Services\Favicons;

use Illuminate\Support\Str;

class ManifestIconParser
{
    public function __construct(
        private UrlGuard $urlGuard,
    ) {}

    /**
     * @return list<array{url: string, score: int, theme: string}>
     */
    public function parse(string $json, string $baseUrl): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['icons']) || ! is_array($decoded['icons'])) {
            return [];
        }

        $icons = [];

        foreach ($decoded['icons'] as $icon) {
            if (! is_array($icon)) {
                continue;
            }

            $src = $icon['src'] ?? null;

            if (! is_string($src) || $src === '') {
                continue;
            }

            $absolute = $this->absolutize($src, $baseUrl);

            if ($absolute === null || ! $this->urlGuard->isAllowedUrl($absolute)) {
                continue;
            }

            $sizes = is_string($icon['sizes'] ?? null) ? $icon['sizes'] : null;
            $score = 60 + $this->sizeBonus($sizes);

            if ($this->looksLikeSvg($absolute) || str_contains(strtolower((string) ($icon['type'] ?? '')), 'svg')) {
                $score = max($score, 100);
            }

            $icons[] = [
                'url' => $absolute,
                'score' => $score,
                'theme' => 'any',
                'size' => $this->parseMaxSize($sizes),
            ];
        }

        return $icons;
    }

    /**
     * @return list<string>
     */
    public function manifestUrlsFromHtml(string $html, string $baseUrl): array
    {
        if (! preg_match_all('/<link\b[^>]*>/i', $html, $matches)) {
            return [];
        }

        $urls = [];

        foreach ($matches[0] as $tag) {
            $rel = $this->attribute($tag, 'rel');
            $href = $this->attribute($tag, 'href');

            if ($rel === null || $href === null || $href === '') {
                continue;
            }

            $relTokens = preg_split('/\s+/', strtolower($rel)) ?: [];

            if (! in_array('manifest', $relTokens, true)) {
                continue;
            }

            $absolute = $this->absolutize($href, $baseUrl);

            if ($absolute === null || ! $this->urlGuard->isAllowedUrl($absolute)) {
                continue;
            }

            $urls[] = $absolute;
        }

        return array_values(array_unique($urls));
    }

    private function sizeBonus(?string $sizes): int
    {
        $max = $this->parseMaxSize($sizes);

        return $max === null ? 0 : min(200, $max);
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

    private function attribute(string $tag, string $name): ?string
    {
        $pattern = sprintf(
            '/%s\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([^\s>]+))/i',
            preg_quote($name, '/'),
        );

        if (! preg_match($pattern, $tag, $match)) {
            return null;
        }

        $value = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : $match[3]);

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

    private function looksLikeSvg(string $url): bool
    {
        return Str::endsWith(strtolower((string) parse_url($url, PHP_URL_PATH)), '.svg');
    }
}
