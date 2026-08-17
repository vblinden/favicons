<?php

namespace App\Jobs;

use App\Enums\FaviconTheme;
use App\Services\Favicons\FaviconService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshFaviconJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 5, 10];

    public int $timeout = 45;

    public int $uniqueFor = 60;

    public function __construct(
        public string $domain,
        public FaviconTheme $theme = FaviconTheme::Default,
    ) {}

    public function uniqueId(): string
    {
        return $this->domain.'|'.$this->theme->value;
    }

    public function handle(FaviconService $favicons): void
    {
        $favicons->refresh($this->domain, $this->theme);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Favicon refresh job failed', [
            'domain' => $this->domain,
            'theme' => $this->theme->value,
            'error' => $exception?->getMessage(),
        ]);
    }
}
