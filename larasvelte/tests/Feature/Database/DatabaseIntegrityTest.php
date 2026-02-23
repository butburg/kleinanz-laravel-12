<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use Illuminate\Database\QueryException;

it('enforces foreign key constraint on ad_images.ad_id', function () {
    expect(fn() => AdImage::create([
        'ad_id' => 'non-existent-uuid',
        'large_path' => 'test.jpg',
        'large_thumb_path' => 'thumb.jpg',
        'original_name' => 'test.jpg',
        'is_title' => false,
    ]))->toThrow(QueryException::class);
});

it('cascades delete from ad to ad_images', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image1 = AdImage::factory()->create(['ad_id' => $ad->id]);
    $image2 = AdImage::factory()->create(['ad_id' => $ad->id]);

    expect(AdImage::count())->toBe(2);

    $ad->delete();

    expect(AdImage::count())->toBe(0);
});

it('enforces required fields on ads table', function () {
    $user = User::factory()->create();

    expect(fn() => Ad::create([
        'user_id' => $user->id,
        // Missing required fields
    ]))->toThrow(QueryException::class);
});

it('enforces required fields on ad_images table', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    expect(fn() => AdImage::create([
        'ad_id' => $ad->id,
        // Missing required paths
    ]))->toThrow(QueryException::class);
});

it('allows nullable fields to be null', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'prompt_text' => null,
        'last_online_at' => null,
    ]);

    expect($ad->prompt_text)->toBeNull()
        ->and($ad->last_online_at)->toBeNull();
});

it('ensures ad status has default value from factory', function () {
    $user = User::factory()->create();

    // Create ad without specifying status (factory provides default)
    $ad = Ad::factory()->for($user)->create();

    expect($ad->status)->toBe(config('ads.status.default'));
});

it('stores and retrieves metadata as JSON', function () {
    $user = User::factory()->create();
    $metadata = [
        'created_at' => '2025-01-01T12:00:00',
        'source' => 'old_system',
        'notes' => 'Imported from legacy',
    ];

    $ad = Ad::factory()->for($user)->create([
        'metadata' => json_encode($metadata),
    ]);

    expect($ad->fresh()->metadata)->toBe(json_encode($metadata));
});

it('handles string IDs without auto-increment', function () {
    $user = User::factory()->create();

    $ad1 = Ad::factory()->for($user)->create(['id' => 'custom-001']);
    $ad2 = Ad::factory()->for($user)->create(['id' => 'custom-002']);

    expect(Ad::find('custom-001'))->not->toBeNull()
        ->and(Ad::find('custom-002'))->not->toBeNull()
        ->and($ad1->id)->toBe('custom-001')
        ->and($ad2->id)->toBe('custom-002');
});

it('maintains referential integrity across user cascade', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    AdImage::factory()->count(3)->create(['ad_id' => $ad->id]);

    expect(Ad::count())->toBe(1)
        ->and(AdImage::count())->toBe(3);

    // Deleting user should cascade to ads, which cascades to images
    $user->delete();

    expect(Ad::count())->toBe(0)
        ->and(AdImage::count())->toBe(0);
});
