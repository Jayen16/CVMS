<?php

use Illuminate\Support\Facades\Route;

test('expired links use the branded error page without exception details', function () {
    $response = $this->get(route('password.phone.link', ['token' => 'invalid-token']));

    $response->assertStatus(410)
        ->assertSee('That link is no longer valid')
        ->assertDontSee('Exception trace')
        ->assertDontSee('vendor/laravel');
});

test('unexpected web errors use a generic page without exception details', function () {
    Route::get('/test-unexpected-error', fn () => throw new RuntimeException('secret database query details'));

    $response = $this->get('/test-unexpected-error');

    $response->assertStatus(500)
        ->assertSee('Something went wrong on our side')
        ->assertDontSee('secret database query details')
        ->assertDontSee('Exception trace')
        ->assertDontSee('vendor/laravel');
});
