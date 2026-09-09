<?php

namespace App\Http\Controllers;

use App\Enums\FaviconTheme;
use App\Http\Requests\FaviconBatchRequest;
use App\Jobs\RefreshFaviconJob;
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

        $this->warmMissing($domains, $existing->keys()->all(), $theme, $size);

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
            'invalid' => $request->invalidDomains(),
        ]);
    }

    /**
     * Queue a resolve for valid-but-unknown domains so the image URLs handed
     * out above are warm by the time clients request them. Skipped on the
     * sync queue driver, where dispatching would crawl inline inside this
     * request. RefreshFaviconJob is unique per domain, so repeat batch calls
     * do not pile up duplicate jobs.
     *
     * @param  list<string>  $domains
     * @param  list<string>  $known
     */
    private function warmMissing(array $domains, array $known, FaviconTheme $theme, int $size): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        $known = array_flip($known);

        foreach ($domains as $domain) {
            if (! isset($known[$domain])) {
                RefreshFaviconJob::dispatch($domain, $theme, $size);
            }
        }
    }
}
