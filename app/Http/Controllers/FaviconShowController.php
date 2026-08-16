<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaviconDomainRequest;
use App\Services\Favicons\FaviconService;
use App\Services\Favicons\FaviconStore;
use Symfony\Component\HttpFoundation\Response;

class FaviconShowController extends Controller
{
    public function __invoke(
        FaviconDomainRequest $request,
        FaviconService $favicons,
        FaviconStore $store,
    ): Response {
        $favicon = $favicons->getOrFetch($request->domain());

        defer(fn () => $favicon->recordRequest());

        return $store->response($favicon, $request->size());
    }
}
