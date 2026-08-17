<?php

namespace App\Http\Controllers;

use App\Exceptions\FetchRateLimitedException;
use App\Http\Requests\FaviconDomainRequest;
use App\Services\Favicons\FaviconService;
use App\Services\Favicons\FaviconStore;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class FaviconShowController extends Controller
{
    public function __invoke(
        FaviconDomainRequest $request,
        FaviconService $favicons,
        FaviconStore $store,
    ): Response {
        try {
            $favicon = $favicons->getOrFetch($request->domain(), (string) $request->ip());
        } catch (FetchRateLimitedException $exception) {
            return response('Too Many Requests', HttpResponse::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $exception->retryAfter,
            ]);
        }

        return $store->response(
            $favicon,
            $request->size(),
            $request->headers->get('If-None-Match'),
            recordRequest: true,
        );
    }
}
