<?php

use App\Jobs\AutoCropImage;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
});

it('dispatches auto crop job when image is stored', function (): void {
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

    // Verify job was queued for each image
    Queue::assertPushed(AutoCropImage::class, function ($job) {
        return isset($job->adImage);
    });
});

it('dispatches multiple auto crop jobs for multiple images', function (): void {
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

    // Verify correct number of jobs were queued
    Queue::assertPushed(AutoCropImage::class, 3);
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

    // Verify job was NOT queued
    Queue::assertNotPushed(AutoCropImage::class);
});
