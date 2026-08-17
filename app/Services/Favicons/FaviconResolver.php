<?php

namespace App\Services\Favicons;

use App\Enums\FaviconTheme;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

class FaviconResolver
{
    public function __construct(
        private UrlGuard $urlGuard,
        private HtmlIconParser $htmlIconParser,
        private ImageNormalizer $imageNormalizer,
        private FallbackIconGenerator $fallbackIconGenerator,
    ) {}

    /**
     * @return array{contents: string, content_type: string, source_url: string|null, width: int|null, height: int|null, status: string}
     */
    public function resolve(string $domain, FaviconTheme $theme = FaviconTheme::Default): array
    {
        $candidates = $this->discoverCandidates($domain, $theme);

        foreach ($candidates as $candidate) {
            $downloaded = $this->download($candidate['url'], (int) config('favicons.max_icon_bytes'));

            if ($downloaded === null) {
                continue;
            }

            $normalized = $this->imageNormalizer->normalize(
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
     * @return list<array{url: string, score: int, theme: string}>
     */
    public function discoverCandidates(string $domain, FaviconTheme $theme = FaviconTheme::Default): array
    {
        $candidates = [];
        $htmlResult = $this->fetchHtml($domain);

        if ($htmlResult !== null) {
            foreach ($this->htmlIconParser->parse($htmlResult['body'], $htmlResult['final_url']) as $icon) {
                $candidates[] = $icon;
            }
        }

        $candidates[] = [
            'url' => 'https://'.$domain.'/favicon.ico',
            'score' => 10,
            'theme' => 'any',
        ];

        usort($candidates, function (array $a, array $b) use ($theme): int {
            $boostedA = $a['score'] + $this->themeBoost($a['theme'] ?? 'any', $theme);
            $boostedB = $b['score'] + $this->themeBoost($b['theme'] ?? 'any', $theme);

            return $boostedB <=> $boostedA;
        });

        $unique = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate['url']])) {
                continue;
            }

            if (! $this->urlGuard->isAllowedUrl($candidate['url'])) {
                continue;
            }

            $seen[$candidate['url']] = true;
            $unique[] = $candidate;
        }

        return $unique;
    }

    private function themeBoost(string $candidateTheme, FaviconTheme $requested): int
    {
        return match ($requested) {
            FaviconTheme::Dark => match ($candidateTheme) {
                'dark' => 1_000_000,
                'any' => 100_000,
                default => 0,
            },
            FaviconTheme::Light => match ($candidateTheme) {
                'light' => 1_000_000,
                'any' => 100_000,
                default => 0,
            },
            FaviconTheme::Default => match ($candidateTheme) {
                'any' => 1_000_000,
                'light' => 100_000,
                default => 0,
            },
        };
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
        $downloaded = $this->download(
            $url,
            (int) config('favicons.max_icon_bytes'),
            requireAllowedHost: false,
        );

        if ($downloaded === null) {
            return null;
        }

        $normalized = $this->imageNormalizer->normalize(
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
     * @return array{body: string, final_url: string}|null
     */
    private function fetchHtml(string $domain): ?array
    {
        foreach (['https', 'http'] as $scheme) {
            $url = $scheme.'://'.$domain.'/';

            if (! $this->urlGuard->isAllowedUrl($url)) {
                continue;
            }

            try {
                $response = $this->client($url, (int) config('favicons.max_html_bytes'))->get($url);

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
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return array{contents: string, content_type: string}|null
     */
    private function download(string $url, int $maxBytes, bool $requireAllowedHost = true): ?array
    {
        if ($requireAllowedHost && ! $this->urlGuard->isAllowedUrl($url)) {
            return null;
        }

        try {
            $response = $this->client($url, $maxBytes)->get($url);

            if ($response->failed()) {
                return null;
            }

            $contents = $response->body();

            if ($contents === '' || strlen($contents) > $maxBytes) {
                return null;
            }

            $contentType = strtolower((string) ($response->header('Content-Type') ?: ''));
            $contentType = trim(explode(';', $contentType)[0]);

            if ($contentType === '' || str_contains($contentType, 'text/html')) {
                $contentType = $this->imageNormalizer->guessContentType($url, $contents);
            }

            if ($this->imageNormalizer->looksLikeSvg($contents, $contentType)) {
                $contentType = 'image/svg+xml';
            } elseif (! str_starts_with($contentType, 'image/')) {
                if (! $this->imageNormalizer->looksLikeImage($contents)) {
                    return null;
                }

                $contentType = $this->imageNormalizer->guessContentType($url, $contents);
            }

            return [
                'contents' => $contents,
                'content_type' => $contentType,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function client(string $url, int $maxBytes): PendingRequest
    {
        $options = [
            'allow_redirects' => [
                'max' => (int) config('favicons.max_redirects'),
                'track_redirects' => true,
                'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                    if (! $this->urlGuard->isAllowedUrl((string) $uri)) {
                        throw new RuntimeException('Redirect blocked.');
                    }
                },
            ],
            'on_headers' => function (ResponseInterface $response) use ($maxBytes): void {
                $length = $response->getHeaderLine('Content-Length');

                if ($length !== '' && (int) $length > $maxBytes) {
                    throw new RuntimeException('Response too large.');
                }
            },
            'progress' => function (mixed $downloadTotal, mixed $downloadedBytes) use ($maxBytes): void {
                if ((int) $downloadedBytes > $maxBytes) {
                    throw new RuntimeException('Response too large.');
                }
            },
        ];

        $curl = $this->urlGuard->curlResolveOptions($url);

        if ($curl !== []) {
            $options['curl'] = $curl;
        }

        return Http::withHeaders([
            'User-Agent' => (string) config('favicons.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ])
            ->timeout((int) config('favicons.timeout'))
            ->connectTimeout((int) config('favicons.connect_timeout'))
            ->retry(2, 100, function (Throwable $exception) {
                return $exception instanceof ConnectionException;
            })
            ->withOptions($options);
    }
}
