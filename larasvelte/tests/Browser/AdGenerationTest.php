<?php

use App\Models\User;

/**
 * Ad Generation Browser Tests
 *
 * These tests help debug the ad generation flow:
 * 1. Index → Expand Create Card → Upload Image → Click Generate
 * 2. Edit → Upload Image → Click Generate
 *
 * Checks that the button is enabled, form exists, and submission works
 */

test('can upload image via inline create form on index page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');

    if (!file_exists($imagePath)) {
        $this->markTestIncomplete("Test image not found.");
    }

    visit('/ads')
        ->assertSee('Create Ad')
        ->click('Create Ad') // Expand the details/summary
        ->pause(300)
        ->assertSee('Upload Images')
        ->attach('input[id="create-images"]', $imagePath)
        ->pause(500)
        ->assertSee('1'); // Shows image count
});

test('generate button is disabled without images on index create form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    visit('/ads')
        ->click('Create Ad') // Expand
        ->pause(300)
        ->assertSee('Generate Ad')
        ->assertButtonDisabled('Generate Ad');
});

test('generate button becomes enabled after uploading image on index create form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');

    if (!file_exists($imagePath)) {
        $this->markTestIncomplete("Test image not found.");
    }

    visit('/ads')
        ->click('Create Ad')
        ->pause(300)
        ->attach('input[id="create-images"]', $imagePath)
        ->pause(500)
        ->assertButtonEnabled('Generate Ad');
});

test('clicking generate button on index create form with image submits and creates ad', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');

    if (!file_exists($imagePath)) {
        $this->markTestIncomplete("Test image not found.");
    }

    visit('/ads')
        ->click('Create Ad')
        ->pause(300)
        ->attach('input[id="create-images"]', $imagePath)
        ->pause(500)
        ->click('Generate Ad')
        ->pause(2000)
        ->assertPathIs('/ads')
        ->assertSee('Ad created successfully');
});

test('full flow: create ad with image and prompt via index page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');

    if (!file_exists($imagePath)) {
        $this->markTestIncomplete("Test image not found.");
    }

    visit('/ads')
        ->click('Create Ad')
        ->pause(300)
        ->assertSee('Upload Images')
        ->attach('input[id="create-images"]', $imagePath)
        ->pause(500)
        ->assertSee('1')
        ->fill('textarea[id="create-prompt"]', 'Describe this vintage item')
        ->click('Generate Ad')
        ->pause(2000)
        ->assertPathIs('/ads');
});

test('can upload image on edit page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create an ad first
    $ad = $user->ads()->create([
        'title' => 'Test Ad',
        'description' => 'Test description that has sufficient length for the validation rules.',
        'price' => 100,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'status' => 'Entwurf',
    ]);

    $imagePath = base_path('no_laravel/migration_source/edit_ad_layout_example.png');

    if (!file_exists($imagePath)) {
        $this->markTestIncomplete("Test image not found.");
    }

    // Go to edit page and upload image
    visit("/ads/{$ad->id}/edit")
        ->assertSee('Edit Ad')
        ->assertSee('No images uploaded yet')
        ->attach('input[name="images[]"]', $imagePath)
        ->pause(500)
        ->click('Add selected images')
        ->pause(2000)
        ->assertSee('Image uploaded'); // Check for success message or image display
});

test('generate button is enabled on edit page when images exist', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create an ad
    $ad = $user->ads()->create([
        'title' => 'Test Ad',
        'description' => 'Test description that has sufficient length.',
        'price' => 100,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'status' => 'Entwurf',
    ]);

    // Add an image
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
        ->assertButtonEnabled('Generate with AI');
});

test('debug: check form submission when clicking generate on edit page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create an ad with image
    $ad = $user->ads()->create([
        'title' => 'Test Ad',
        'description' => 'Test description that has sufficient length.',
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

    // Check that page loaded
    $page->assertSee('Edit Ad');

    // Run JavaScript to inspect the form and log to console
    $page->script("
        // Log to browser console for debugging
        const generateForm = document.querySelector('form[action*=\"/generate\"]');
        console.log('=== Generate Form Debug ===');
        console.log('Form exists:', !!generateForm);
        if (generateForm) {
            console.log('Form action:', generateForm.action);
            console.log('Form method:', generateForm.method);
            console.log('Form inputs:', Array.from(generateForm.querySelectorAll('input')).map(i => i.name));
        }

        const generateBtn = Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Generate with AI'));
        console.log('Generate button exists:', !!generateBtn);
        console.log('Generate button disabled:', generateBtn?.disabled);

        const images = document.querySelectorAll('[data-test^=\"image-card-\"]');
        console.log('Images in DOM:', images.length);
    ");

    $page->assertSee('Generate with AI');
});

test('debug: browser logs for generate flow', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ad = $user->ads()->create([
        'title' => 'Test Ad',
        'description' => 'Test description that has sufficient length.',
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

    // Check page loaded properly
    $page->assertSee('Edit Ad')
        ->assertSee('Generate with AI');

    // Test JavaScript errors using Pest's assertion
    $page->assertNoJavaScriptErrors();

    // Check if generate form exists with JavaScript
    $formExists = $page->script("
        const form = document.querySelector('form[action*=\"/generate\"]');
        return !!form;
    ");

    expect($formExists)->toBeTrue();
});
