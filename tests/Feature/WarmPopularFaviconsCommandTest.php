<?php

use App\Jobs\RefreshFaviconJob;
use App\Models\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('it queues warm jobs for popular domains', function () {
    Queue::fake();

    Favicon::factory()->create([
        'domain' => 'popular.com',
        'request_count' => 50,
        'fetched_at' => now(),
    ]);

    Favicon::factory()->create([
        'domain' => 'quiet.com',
        'request_count' => 1,
        'fetched_at' => now(),
    ]);

    $this->artisan('favicons:warm', ['--limit' => 1])
        ->assertSuccessful();

    Queue::assertPushed(RefreshFaviconJob::class, fn (RefreshFaviconJob $job) => $job->domain === 'popular.com');
    Queue::assertNotPushed(RefreshFaviconJob::class, fn (RefreshFaviconJob $job) => $job->domain === 'quiet.com');
});
