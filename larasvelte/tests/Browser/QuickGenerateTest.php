<?php

use App\Models\User;

/**
 * Quick Ad Generation Tests
 * Focused tests to verify the generate button in the new inline create flow
 */

test('generate button is disabled without images on inline create form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    visit('/ads')
        ->click('Create Ad') // Expand the create card
        ->pause(300)
        ->assertSee('Generate Ad')
        ->assertButtonDisabled('Generate Ad');
});

test('can upload image and enable generate button on inline create form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');

    if (!file_exists($imagePath)) {
        $this->markTestSkipped('Test image not found.');
    }

    visit('/ads')
        ->click('Create Ad')
        ->pause(300)
        ->attach('input[id="create-images"]', $imagePath)
        ->pause(500)
        ->assertButtonEnabled('Generate Ad');
});

test('generate button works on edit page with images', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create ad with image
    $ad = $user->ads()->create([
        'title' => 'Test Ad',
        'description' => 'Test description with sufficient length for validation.',
        'price' => 100,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'status' => 'Entwurf',
    ]);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');
    if (file_exists($imagePath)) {
        $storagePath = 'ads/' . $ad->id . '/large/test.png';
        \Storage::disk('public')->put($storagePath, file_get_contents($imagePath));

        $ad->images()->create([
            'large_path' => $storagePath,
            'large_thumb_path' => 'ads/' . $ad->id . '/large_thumb/test.png',
            'original_name' => 'test.png',
            'is_title' => true,
        ]);
    }

    visit("/ads/{$ad->id}/edit")
        ->assertSee('Edit Ad')
        ->assertSee('Generate Again')
        ->assertButtonEnabled('Generate Again');
});

test('generate form exists on edit page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ad = $user->ads()->create([
        'title' => 'Test Ad',
        'description' => 'Test description with sufficient length.',
        'price' => 100,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'status' => 'Entwurf',
    ]);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');
    if (file_exists($imagePath)) {
        $storagePath = 'ads/' . $ad->id . '/large/test.png';
        \Storage::disk('public')->put($storagePath, file_get_contents($imagePath));

        $ad->images()->create([
            'large_path' => $storagePath,
            'large_thumb_path' => 'ads/' . $ad->id . '/large_thumb/test.png',
            'original_name' => 'test.png',
            'is_title' => true,
        ]);
    }

    $page = visit("/ads/{$ad->id}/edit");

    $page->assertSee('Edit Ad');

    // Check that generate form exists using assertScript
    $page->assertScript("document.querySelector('form[action*=\"/generate\"]') !== null", true);
});
