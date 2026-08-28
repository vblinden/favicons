<?php

namespace App\Http\Controllers;

use App\Exceptions\FetchRateLimitedException;
use App\Http\Requests\FaviconGoogleRequest;
use App\Services\Favicons\FaviconService;
use App\Services\Favicons\FaviconStore;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class FaviconGoogleController extends Controller
{
    public function __invoke(
        FaviconGoogleRequest $request,
        FaviconService $favicons,
        FaviconStore $store,
    ): Response {
        try {
            $favicon = $favicons->getOrFetch(
                $request->domain(),
                (string) $request->ip(),
                $request->theme(),
                $request->size(),
            );
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
            format: $request->imageFormat(),
        );
    }
}
