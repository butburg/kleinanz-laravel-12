<?php

use App\Models\User;

it('allows an authenticated user to create an ad in browser flow', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/ads/create');

    $page->assertPathIs('/ads/create')
        ->assertSee('Create Ad')
        ->fill('title', 'Browser Created Ad')
        ->fill('description', str_repeat('Browser description text ', 3))
        ->fill('price', '99')
        ->select('condition', 'Gut')
        ->select('shipping', 'mittel')
        ->select('status', 'Entwurf')
        ->click('Save Ad')
        ->assertPathIs('/ads')
        ->assertSee('Browser Created Ad');
});
