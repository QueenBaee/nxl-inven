<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('root route redirects unauthenticated user to filament login url', function () {
    $response = $this->get('/');

    $response->assertStatus(302)
        ->assertRedirect(Filament::getLoginUrl());
});

test('authenticated user accessing root route is redirected to filament panel', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(302)
        ->assertRedirect(Filament::getLoginUrl());

    // Following redirect to login url when authenticated leads to dashboard
    $followResponse = $this->actingAs($user)->get(Filament::getLoginUrl());
    $followResponse->assertRedirect('/admin');
});
