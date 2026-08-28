<?php

namespace App\Console\Commands;

use App\Enums\FaviconTheme;
use App\Jobs\RefreshFaviconJob;
use App\Models\Favicon;
use Illuminate\Console\Command;

class WarmPopularFaviconsCommand extends Command
{
    protected $signature = 'favicons:warm {--limit= : Number of leaderboard domains to warm}';

    protected $description = 'Queue refresh jobs for the most-requested favicon domains';

    public function handle(): int
    {
        $limit = max(1, (int) ($this->option('limit') ?: config('favicons.warm_limit')));

        $domains = Favicon::query()
            ->mostRequested()
            ->limit($limit)
            ->pluck('domain');

        foreach ($domains as $domain) {
            RefreshFaviconJob::dispatch((string) $domain, FaviconTheme::Default);
        }

        $this->info('Queued '.$domains->count().' favicon warm jobs.');

        return self::SUCCESS;
    }
}
