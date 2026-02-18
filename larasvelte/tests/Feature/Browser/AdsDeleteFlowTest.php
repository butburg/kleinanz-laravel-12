<?php

use App\Models\Ad;
use App\Models\User;

it('deletes an ad from the edit page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'title' => 'Delete Candidate',
        'description' => str_repeat('Delete candidate description. ', 3),
        'price' => 20,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'status' => 'Entwurf',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.edit', $ad, absolute: false));

    $page->assertSee('Edit Ad')
        ->click('Delete Ad')
        ->assertPathIs(route('ads.index', absolute: false))
        ->assertDontSee('Delete Candidate')
        ->assertSee('Ad deleted successfully.');
});
