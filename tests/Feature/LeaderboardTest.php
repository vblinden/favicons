<?php

use App\Models\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('the leaderboard ranks domains by request count', function () {
    Favicon::factory()->requested(3)->create(['domain' => 'bronze.example']);
    Favicon::factory()->requested(10)->create(['domain' => 'gold.example']);
    Favicon::factory()->requested(5)->create(['domain' => 'silver.example']);
    Favicon::factory()->requested(0)->create(['domain' => 'unused.example']);

    $response = $this->get(route('leaderboard'));

    $response->assertSuccessful()
        ->assertSeeInOrder([
            'gold.example',
            'silver.example',
            'bronze.example',
        ], false)
        ->assertSee('/i/gold.example', false)
        ->assertDontSee('unused.example', false)
        ->assertSee('10', false)
        ->assertSee('5', false)
        ->assertSee('3', false);
});

test('the leaderboard shows an empty state when nothing has been requested', function () {
    Favicon::factory()->create(['domain' => 'unused.example', 'request_count' => 0]);

    $this->get(route('leaderboard'))
        ->assertSuccessful()
        ->assertSee('No requests yet', false)
        ->assertDontSee('unused.example', false);
});
