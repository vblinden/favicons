<?php

use App\Enums\FaviconTheme;
use App\Jobs\RefreshFaviconJob;
use App\Services\Favicons\FaviconService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use ReflectionClass;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('favicons');
});

test('it handles jobs serialized without a targetSize property', function () {
    $this->mock(FaviconService::class, function (MockInterface $mock) {
        $mock->shouldReceive('refresh')
            ->once()
            ->with('example.com', FaviconTheme::Default, null);
    });

    $payload = (new RefreshFaviconJob('example.com', FaviconTheme::Default, 64))->__serialize();
    unset($payload['targetSize']);

    /** @var RefreshFaviconJob $job */
    $job = (new ReflectionClass(RefreshFaviconJob::class))->newInstanceWithoutConstructor();
    $job->__unserialize($payload);

    expect($job->targetSize)->toBeNull();

    $job->handle(app(FaviconService::class));
});
