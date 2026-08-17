<?php

namespace App\Services\Favicons;

use Illuminate\Support\Str;

class HtmlIconParser
{
    public function __construct(
        private UrlGuard $urlGuard,
    ) {}

    /**
     * @return list<array{url: string, score: int}>
     */
    public function parse(string $html, string $baseUrl): array
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

            if (! $this->isIconRel($relTokens) || in_array('mask-icon', $relTokens, true)) {
                continue;
            }

            $absolute = $this->absolutize($href, $baseUrl);

            if ($absolute === null || ! $this->urlGuard->isAllowedUrl($absolute)) {
                continue;
            }

            $sizes = $this->attribute($tag, 'sizes');
            $score = $this->scoreIcon($relTokens, $sizes);

            // Prefer SVG icons when declared — browsers do the same. Raster
            // fallbacks (apple-touch / ico) remain as lower-scored candidates.
            if ($this->looksLikeSvg($absolute)) {
                $score = max($score, 100);
            }

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
    private function scoreIcon(array $relTokens, ?string $sizes): int
    {
        $score = 50;

        if (in_array('apple-touch-icon', $relTokens, true) || in_array('apple-touch-icon-precomposed', $relTokens, true)) {
            $score += 40;
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
