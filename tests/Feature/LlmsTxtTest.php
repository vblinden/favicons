<?php

test('llms.txt is available as plain text markdown', function () {
    $this->get(route('llms.txt'))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('# Favicons', false)
        ->assertSee('GET /i/{domain}', false)
        ->assertSee('theme=dark|light', false)
        ->assertSee(url('/terms'), false)
        ->assertSee(url('/acceptable-use'), false)
        ->assertSee(url('/privacy'), false)
        ->assertSee(url('/leaderboard'), false);
});
