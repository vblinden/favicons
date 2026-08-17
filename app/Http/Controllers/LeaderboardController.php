<?php

namespace App\Http\Controllers;

use App\Models\Favicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LeaderboardController extends Controller
{
    public function __invoke(): View
    {
        $limit = (int) config('favicons.leaderboard_limit');

        /** @var Collection<int, object{rank: int, domain: string, request_count: int, preview: string}> $entries */
        $entries = collect(Cache::remember(
            'favicons:leaderboard:'.$limit,
            60,
            function () use ($limit) {
                return Favicon::query()
                    ->mostRequested()
                    ->limit($limit)
                    ->get()
                    ->values()
                    ->map(fn (Favicon $favicon, int $index) => [
                        'rank' => $index + 1,
                        'domain' => $favicon->domain,
                        'request_count' => (int) $favicon->request_count,
                        'preview' => route('favicons.show', ['domain' => $favicon->domain, 'sz' => 32]),
                    ])
                    ->all();
            },
        ))->map(fn (array $entry) => (object) $entry);

        return view('leaderboard', [
            'entries' => $entries,
        ]);
    }
}
