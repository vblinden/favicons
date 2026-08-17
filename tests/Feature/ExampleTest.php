<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('Favicons', false)
        ->assertSee('/i/github.com', false)
        ->assertSee('/r/github.com', false)
        ->assertSee('/leaderboard', false)
        ->assertSee('default 32', false)
        ->assertSee('?theme=dark', false)
        ->assertSee('text-danger', false)
        ->assertSee('bg-mesh', false);
});
