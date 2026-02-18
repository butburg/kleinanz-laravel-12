<?php

use App\Models\User;
use Database\Seeders\DefaultUserSeeder;

beforeEach(function (): void {
    $this->seed(DefaultUserSeeder::class);
});

test('guest can navigate from welcome page to login page', function (): void {
    $page = visit('/');

    $page->assertSee("Let's get started")
        ->click('Log in')
        ->assertPathIs('/login')
        ->assertSee('Log in to your account');
});

test('seeded default user can log in from the browser and reach dashboard', function (): void {
    $page = visit('/login');

    $page->fill('email', 'default@example.test')
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->assertSee('Dashboard');

    $this->assertAuthenticatedAs(User::query()->where('email', 'default@example.test')->firstOrFail());
});
