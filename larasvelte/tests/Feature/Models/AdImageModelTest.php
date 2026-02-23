<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;

it('belongs to an ad', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->create(['ad_id' => $ad->id]);

    expect($image->ad)->toBeInstanceOf(Ad::class)
        ->and($image->ad->id)->toBe($ad->id);
});

it('can be marked as title image', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->create([
        'ad_id' => $ad->id,
        'is_title' => true,
    ]);

    expect($image->is_title)->toBeTrue()
        ->and($image->is_title)->toBeBool();
});
b8b1dd27-c528-40b8-bf3e-1e9796df7cb1
it('stores image paths correctly', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->create([
        'ad_id' => $ad->id,
        'large_path' => 'ads/ad-123/large/image.jpg',
        'large_thumb_path' => 'ads/ad-123/large_thumb/thumb_image.jpg',
        'cropped_path' => 'ads/ad-123/cropped/cropped_image.jpg',
        'cropped_thumb_path' => 'ads/ad-123/cropped_thumb/cropped_thumb_image.jpg',
    ]);

    expect($image->large_path)->toBe('ads/ad-123/large/image.jpg')
        ->and($image->large_thumb_path)->toBe('ads/ad-123/large_thumb/thumb_image.jpg')
        ->and($image->cropped_path)->toBe('ads/ad-123/cropped/cropped_image.jpg')
        ->and($image->cropped_thumb_path)->toBe('ads/ad-123/cropped_thumb/cropped_thumb_image.jpg');
});

it('handles nullable cropped paths', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->create([
        'ad_id' => $ad->id,
        'cropped_path' => null,
        'cropped_thumb_path' => null,
    ]);

    expect($image->cropped_path)->toBeNull()
        ->and($image->cropped_thumb_path)->toBeNull();
});
