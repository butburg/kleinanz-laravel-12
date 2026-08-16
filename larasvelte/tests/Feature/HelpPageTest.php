<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected away from the help page', function (): void {
    $this->get(route('help'))->assertRedirect(route('login'));
});

test('authenticated users can view the help page', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('help'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Help'));
});
