<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaviconBatchRequest;
use App\Models\Favicon;
use Illuminate\Http\JsonResponse;

class FaviconBatchController extends Controller
{
    public function __invoke(FaviconBatchRequest $request): JsonResponse
    {
        $theme = $request->theme();
        $size = $request->size();
        $format = $request->imageFormat();
        $domains = $request->domains();

        $existing = Favicon::query()
            ->whereIn('domain', $domains)
            ->where('theme', $theme->value)
            ->get()
            ->keyBy('domain');

        $icons = [];

        foreach ($domains as $domain) {
            $favicon = $existing->get($domain);
            $query = array_filter([
                'sz' => $size,
                'theme' => $theme->value === 'default' ? null : $theme->value,
                'format' => $format->value === 'png' ? null : $format->value,
            ], fn ($value) => $value !== null);

            $icons[] = [
                'domain' => $domain,
                'url' => route('favicons.show', ['domain' => $domain] + $query),
                'status' => match (true) {
                    $favicon === null => 'unknown',
                    $favicon->isLetterFallback() => 'letter',
                    $favicon->isFallback() => 'fallback',
                    default => 'ok',
                },
            ];
        }

        return response()->json([
            'sz' => $size,
            'theme' => $theme->value,
            'format' => $format->value,
            'icons' => $icons,
        ]);
    }
}
