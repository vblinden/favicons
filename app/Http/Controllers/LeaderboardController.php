<?php

namespace App\Http\Controllers;

use App\Models\Favicon;
use App\Services\Favicons\FaviconStore;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class LeaderboardController extends Controller
{
    public function __invoke(FaviconStore $store): View
    {
        $limit = (int) config('favicons.leaderboard_limit');

        /** @var Collection<int, object{rank: int, domain: string, request_count: int, preview: string}> $entries */
        $entries = Favicon::query()
            ->select(['id', 'domain', 'status', 'content_type', 'storage_path', 'request_count'])
            ->mostRequested()
            ->limit($limit)
            ->get()
            ->values()
            ->map(fn (Favicon $favicon, int $index) => (object) [
                'rank' => $index + 1,
                'domain' => $favicon->domain,
                'request_count' => $favicon->request_count,
                'preview' => $store->dataUri($favicon, 64),
            ]);

        return view('leaderboard', [
            'entries' => $entries,
        ]);
    }
}
