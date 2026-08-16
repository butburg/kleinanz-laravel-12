<?php

use App\Models\User;
use Database\Seeders\DefaultUserSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(DefaultUserSeeder::class);
});

it('routes guests to login from the home page', function (): void {
    $this->get(route('home'))
        ->assertRedirect(route('login', absolute: false));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('auth/Login'));
});

it('routes authenticated users to their ad listing from the home page', function (): void {
    $response = $this->post(route('login'), [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('ads.index', absolute: false));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('ads/Index'));
});
