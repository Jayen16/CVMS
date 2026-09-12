<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard KPI cards are not links to unauthorized pages', function () {
    $nurse = User::factory()->create([
        'role' => 'nurse',
        'roles' => ['nurse'],
        'permissions' => [],
    ]);

    $response = $this->actingAs($nurse)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertDontSee('href="'.route('reports.index').'"', false)
        ->assertDontSee('href="'.route('children.index').'"', false)
        ->assertDontSee('href="'.route('verification-queue.index').'"', false);
});
