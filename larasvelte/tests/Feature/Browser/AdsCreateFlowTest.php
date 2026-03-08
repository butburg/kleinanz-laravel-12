<?php

use App\Models\User;

it('allows an authenticated user to create an ad via inline form on index page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');

    if (!file_exists($imagePath)) {
        $this->markTestSkipped('Test image not found.');
    }

    $page = visit('/ads');

    // Expand the Create Ad card
    $page->assertPathIs('/ads')
        ->assertSee('Create Ad')
        ->click('Create Ad') // Click the details/summary to expand
        ->pause(300)
        ->assertSee('Upload Images')
        ->assertSee('Prompt (optional)')
        ->attach('input[id="create-images"]', $imagePath)
        ->pause(500)
        ->fill('textarea[id="create-prompt"]', 'A nice item')
        ->click('Generate Ad')
        ->pause(2000)
        ->assertPathIs('/ads')
        ->assertSee('Ad created successfully');
});
