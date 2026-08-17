<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaviconDomainRequest;
use App\Services\Favicons\FaviconService;
use App\Services\Favicons\FaviconStore;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class FaviconRefreshController extends Controller
{
    public function __invoke(
        FaviconDomainRequest $request,
        FaviconService $favicons,
        FaviconStore $store,
    ): Response {
        $domain = $request->domain();
        $theme = $request->theme();
        $key = 'favicon-refresh:'.$request->ip().':'.$domain.':'.$theme->value;

        $response = RateLimiter::attempt(
            $key,
            (int) config('favicons.refresh_max_attempts'),
            function () use ($favicons, $store, $domain, $theme, $request) {
                $favicon = $favicons->refresh($domain, $theme);

                return $store->response(
                    $favicon,
                    $request->size(),
                    $request->headers->get('If-None-Match'),
                );
            },
            (int) config('favicons.refresh_decay_seconds'),
        );

        if ($response === false) {
            return response('Too Many Requests', HttpResponse::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) RateLimiter::availableIn($key),
            ]);
        }

        return $response;
    }
}
