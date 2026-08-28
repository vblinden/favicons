<?php

namespace App\Http\Controllers;

use App\Exceptions\FetchRateLimitedException;
use App\Http\Requests\FaviconDomainRequest;
use App\Services\Favicons\FaviconService;
use App\Services\Favicons\FaviconStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;

class FaviconMetadataController extends Controller
{
    public function __invoke(
        FaviconDomainRequest $request,
        FaviconService $favicons,
        FaviconStore $store,
    ): JsonResponse {
        try {
            $favicon = $favicons->getOrFetch(
                $request->domain(),
                (string) $request->ip(),
                $request->theme(),
                $request->size(),
            );
        } catch (FetchRateLimitedException $exception) {
            return response()->json([
                'message' => 'Too Many Requests',
                'retry_after' => $exception->retryAfter,
            ], HttpResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $query = array_filter([
            'sz' => $request->size(),
            'theme' => $request->theme()->value === 'default' ? null : $request->theme()->value,
            'format' => $request->imageFormat()->value === 'png' ? null : $request->imageFormat()->value,
        ], fn ($value) => $value !== null);

        return response()->json([
            'domain' => $favicon->domain,
            'theme' => $favicon->theme,
            'status' => $store->statusHeader($favicon),
            'source_url' => $favicon->source_url,
            'width' => $favicon->width,
            'height' => $favicon->height,
            'content_type' => $favicon->content_type,
            'fetched_at' => $favicon->fetched_at?->toIso8601String(),
            'url' => route('favicons.show', ['domain' => $favicon->domain] + $query),
        ]);
    }
}
