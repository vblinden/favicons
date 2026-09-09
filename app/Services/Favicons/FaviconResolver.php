<?php

namespace App\Services\Favicons;

use App\Enums\FaviconTheme;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        private ManifestIconParser $manifestIconParser,
        private ImageNormalizer $imageNormalizer,
        private FallbackIconGenerator $fallbackIconGenerator,
    ) {}

    /**
     * @return array{contents: string, content_type: string, source_url: string|null, width: int|null, height: int|null, status: string}
     */
    public function resolve(string $domain, FaviconTheme $theme = FaviconTheme::Default, ?int $targetSize = null): array
    {
        $targetSize ??= (int) config('favicons.default_size');
        $candidates = $this->discoverCandidates($domain, $theme, $targetSize);

        $downloaded = $this->downloadBestCandidate($candidates);

        if ($downloaded !== null) {
            $normalized = $this->imageNormalizer->normalize(
                $downloaded['contents'],
                $downloaded['content_type'],
            );

            if ($normalized !== null) {
                return [
                    'contents' => $normalized['contents'],
                    'content_type' => $normalized['content_type'],
                    'source_url' => $downloaded['url'],
                    'width' => $normalized['width'],
                    'height' => $normalized['height'],
                    'status' => 'ok',
                ];
            }
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
     * @return list<array{url: string, score: int, theme: string, size: int|null}>
     */
    public function discoverCandidates(string $domain, FaviconTheme $theme = FaviconTheme::Default, ?int $targetSize = null): array
    {
        $targetSize ??= (int) config('favicons.default_size');
        $candidates = [];
        $htmlResult = $this->fetchHtml($domain);

        if ($htmlResult !== null) {
            foreach ($this->htmlIconParser->parse($htmlResult['body'], $htmlResult['final_url'], $targetSize) as $icon) {
                $candidates[] = $icon;
            }

            foreach ($this->manifestIconParser->manifestUrlsFromHtml($htmlResult['body'], $htmlResult['final_url']) as $manifestUrl) {
                $manifestBody = $this->downloadText($manifestUrl, (int) config('favicons.max_html_bytes'));

                if ($manifestBody === null) {
                    continue;
                }

                foreach ($this->manifestIconParser->parse($manifestBody, $manifestUrl) as $icon) {
                    $candidates[] = $icon;
                }
            }

            $candidates[] = [
                'url' => 'https://'.$domain.'/favicon.ico',
                'score' => 10,
                'theme' => 'any',
                'size' => null,
            ];
        } else {
            foreach ($this->wellKnownCandidates($domain) as $candidate) {
                $candidates[] = $candidate;
            }
        }

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

    /**
     * @return list<array{url: string, score: int, theme: string, size: int|null}>
     */
    private function wellKnownCandidates(string $domain): array
    {
        return [
            [
                'url' => 'https://'.$domain.'/apple-touch-icon.png',
                'score' => 30,
                'theme' => 'any',
                'size' => 180,
            ],
            [
                'url' => 'https://'.$domain.'/apple-touch-icon-precomposed.png',
                'score' => 20,
                'theme' => 'any',
                'size' => 180,
            ],
            [
                'url' => 'https://'.$domain.'/favicon.ico',
                'score' => 10,
                'theme' => 'any',
                'size' => null,
            ],
        ];
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
     * @param  list<array{url: string, score: int, theme: string, size: int|null}>  $candidates
     * @return array{contents: string, content_type: string, url: string}|null
     */
    private function downloadBestCandidate(array $candidates): ?array
    {
        $maxBytes = (int) config('favicons.max_icon_bytes');
        $parallel = max(1, (int) config('favicons.parallel_candidates'));

        // Http::pool does not reliably honor per-request CURLOPT_RESOLVE pinning
        // under Http::fake(); keep sequential downloads for correctness.
        if ($parallel <= 1 || app()->runningUnitTests()) {
            foreach ($candidates as $candidate) {
                $downloaded = $this->download($candidate['url'], $maxBytes);

                if ($downloaded !== null) {
                    return [
                        'contents' => $downloaded['contents'],
                        'content_type' => $downloaded['content_type'],
                        'url' => $candidate['url'],
                    ];
                }
            }

            return null;
        }

        $offset = 0;

        while ($offset < count($candidates)) {
            $batch = array_slice($candidates, $offset, $parallel);
            $offset += $parallel;

            $responses = Http::pool(fn (Pool $pool) => collect($batch)
                ->mapWithKeys(fn (array $candidate, int $index) => [
                    (string) $index => $this->configureClient(
                        $pool->as((string) $index),
                        $candidate['url'],
                        $maxBytes,
                    )->get($candidate['url']),
                ])
                ->all());

            foreach ($batch as $index => $candidate) {
                $response = $responses[(string) $index] ?? null;

                if (! $response instanceof Response || $response->failed()) {
                    continue;
                }

                $parsed = $this->parseImageResponse($response, $candidate['url'], $maxBytes);

                if ($parsed !== null) {
                    return [
                        'contents' => $parsed['contents'],
                        'content_type' => $parsed['content_type'],
                        'url' => $candidate['url'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array{contents: string, content_type: string}|null
     */
    private function download(string $url, int $maxBytes): ?array
    {
        if (! $this->urlGuard->isAllowedUrl($url)) {
            return null;
        }

        try {
            $response = $this->client($url, $maxBytes)->get($url);

            if ($response->failed()) {
                return null;
            }

            return $this->parseImageResponse($response, $url, $maxBytes);
        } catch (Throwable $exception) {
            Log::debug('Favicon candidate download failed.', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
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
        $downloaded = $this->downloadStarAvatar($url);

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
                    Log::debug('Favicon html fetch failed.', [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);

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
            } catch (Throwable $exception) {
                Log::debug('Favicon html fetch failed.', [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }
        }

        return null;
    }

    private function downloadText(string $url, int $maxBytes): ?string
    {
        if (! $this->urlGuard->isAllowedUrl($url)) {
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

            return $contents;
        } catch (Throwable $exception) {
            Log::debug('Favicon manifest download failed.', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{contents: string, content_type: string}|null
     */
    private function downloadStarAvatar(string $url): ?array
    {
        $maxBytes = (int) config('favicons.max_icon_bytes');

        try {
            $response = $this->starAvatarClient($url, $maxBytes)->get($url);

            if ($response->failed()) {
                return null;
            }

            return $this->parseImageResponse($response, $url, $maxBytes);
        } catch (Throwable $exception) {
            Log::debug('Star Avatars fallback download failed.', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function starAvatarClient(string $url, int $maxBytes): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => (string) config('favicons.user_agent'),
            'Accept' => 'image/avif,image/webp,image/apng,image/png,image/*,*/*;q=0.8',
        ])
            ->timeout((int) config('favicons.timeout'))
            ->connectTimeout((int) config('favicons.connect_timeout'))
            ->retry(3, 250, function (Throwable $exception, PendingRequest $request, ?ResponseInterface $response) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if ($response === null) {
                    return false;
                }

                return in_array($response->getStatusCode(), [429, 502, 503, 504], true);
            })
            ->withOptions($this->downloadOptions($url, $maxBytes, requireAllowedHost: false));
    }

    /**
     * @return array{contents: string, content_type: string}|null
     */
    private function parseImageResponse(Response $response, string $url, int $maxBytes): ?array
    {
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
    }

    private function client(string $url, int $maxBytes): PendingRequest
    {
        return $this->configureClient(
            Http::withHeaders([
                'User-Agent' => (string) config('favicons.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ])
                ->timeout((int) config('favicons.timeout'))
                ->connectTimeout((int) config('favicons.connect_timeout'))
                ->retry(2, 100, function (Throwable $exception) {
                    return $exception instanceof ConnectionException;
                }),
            $url,
            $maxBytes,
        );
    }

    private function configureClient(PendingRequest $request, string $url, int $maxBytes): PendingRequest
    {
        return $request
            ->withHeaders([
                'User-Agent' => (string) config('favicons.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml,application/manifest+json,image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ])
            ->timeout((int) config('favicons.timeout'))
            ->connectTimeout((int) config('favicons.connect_timeout'))
            ->withOptions($this->downloadOptions($url, $maxBytes));
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadOptions(string $url, int $maxBytes, bool $requireAllowedHost = true): array
    {
        $options = [
            'allow_redirects' => [
                'max' => (int) config('favicons.max_redirects'),
                'track_redirects' => true,
                'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri) use ($requireAllowedHost): void {
                    if ($requireAllowedHost && ! $this->urlGuard->isAllowedUrl((string) $uri)) {
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

        return $options;
    }
}
