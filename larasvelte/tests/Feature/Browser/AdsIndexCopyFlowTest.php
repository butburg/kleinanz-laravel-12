<?php

use App\Models\Ad;
use App\Models\User;

it('allows copying title and description directly from ads index', function (): void {
    $user = User::factory()->create();
    Ad::factory()->for($user)->create([
        'title' => 'Copyable title',
        'description' => 'Copyable description body',
        'status' => 'Entwurf',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.index', absolute: false));

    $page->assertSee('Copyable title')
        ->click('Copy title')
        ->assertSee('Title copied.')
        ->click('Copy description')
        ->assertSee('Description copied.');
});
