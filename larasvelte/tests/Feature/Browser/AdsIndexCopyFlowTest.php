<?php

use App\Models\Ad;
use App\Models\User;

it('allows copying title and description directly from ads index', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'title' => 'Copyable title',
        'description' => 'Copyable description body',
        'status' => 'Entwurf',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.index', absolute: false));

    $page->assertSee('Copyable title')
        ->click('Copy title')
        ->assertAttributeContains('[data-test="copy-title-'.$ad->id.'"]', 'class', 'copy-saved')
        ->assertPresent('[data-test="copy-title-check-'.$ad->id.'"]')
        ->click('Copy description')
        ->assertAttributeContains('[data-test="copy-description-'.$ad->id.'"]', 'class', 'copy-saved')
        ->assertAttributeDoesntContain('[data-test="copy-title-'.$ad->id.'"]', 'class', 'copy-saved')
        ->assertPresent('[data-test="copy-description-check-'.$ad->id.'"]')
        ->assertDontSee('Title copied.')
        ->assertDontSee('Description copied.');
});
