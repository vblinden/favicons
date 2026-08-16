<?php

test('the terms of service page is available', function () {
    $this->get(route('legal.terms'))
        ->assertSuccessful()
        ->assertSee('Terms of Service', false)
        ->assertSee('Acceptable Use Policy', false);
});

test('the acceptable use policy page is available', function () {
    $this->get(route('legal.acceptable-use'))
        ->assertSuccessful()
        ->assertSee('Acceptable Use Policy', false)
        ->assertSee('Prohibited use', false);
});

test('the privacy policy page is available', function () {
    $this->get(route('legal.privacy'))
        ->assertSuccessful()
        ->assertSee('Privacy Policy', false)
        ->assertSee('Information we process', false);
});

test('the home page links to legal pages', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.acceptable-use'), false)
        ->assertSee(route('legal.privacy'), false)
        ->assertSee(url('/llms.txt'), false);
});
