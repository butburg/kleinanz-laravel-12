<?php

use App\Jobs\AutoCropImage;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Bus::fake();
});

it('dispatches auto crop job synchronously when image is stored', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $this->actingAs($user);

    // Create test image file
    $file = UploadedFile::fake()->image('test-image.jpg', 500, 500);

    // Trigger image upload via controller using correct route
    $response = $this->post(route('ads.images.store', $ad), [
        'images' => [$file],
    ]);

    // Verify response
    $response->assertRedirect();

    Bus::assertDispatchedSync(AutoCropImage::class, function ($job) {
        return isset($job->adImage);
    });
});

it('dispatches multiple auto crop jobs synchronously for multiple images', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $this->actingAs($user);

    // Create multiple test images
    $files = [
        UploadedFile::fake()->image('image1.jpg', 500, 500),
        UploadedFile::fake()->image('image2.jpg', 500, 500),
        UploadedFile::fake()->image('image3.jpg', 500, 500),
    ];

    // Trigger image upload
    $response = $this->post(route('ads.images.store', $ad), [
        'images' => $files,
    ]);

    $response->assertRedirect();

    Bus::assertDispatchedSync(AutoCropImage::class, 3);
});

it('does not dispatch auto crop job when disabled', function (): void {
    config(['ads.auto_crop.enabled' => false]);

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $this->actingAs($user);

    // Create test image file
    $file = UploadedFile::fake()->image('test-image.jpg', 500, 500);

    // Trigger image upload
    $response = $this->post(route('ads.images.store', $ad), [
        'images' => [$file],
    ]);

    Bus::assertNotDispatched(AutoCropImage::class);
});

it('does not dispatch auto crop job when disabled for a create request', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('ads.store'), [
        '_generate' => true,
        'images' => [UploadedFile::fake()->image('test-image.jpg', 500, 500)],
        'auto_crop_enabled' => false,
    ]);

    $response->assertRedirect();

    Bus::assertNotDispatched(AutoCropImage::class);

    $adImage = $user->ads()->latest('id')->first()?->images()->first();

    expect($adImage)->not->toBeNull();
    expect($adImage?->use_cropped)->toBeFalse();
});
