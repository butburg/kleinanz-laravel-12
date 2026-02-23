<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;

it('has many ad images', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    AdImage::factory()->count(3)->create(['ad_id' => $ad->id]);

    expect($ad->images)->toHaveCount(3)
        ->and($ad->images->first())->toBeInstanceOf(AdImage::class);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    expect($ad->user)->toBeInstanceOf(User::class)
        ->and($ad->user->id)->toBe($user->id);
});

it('uses string primary keys', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['id' => 'custom-uuid-123']);

    expect($ad->id)->toBe('custom-uuid-123')
        ->and($ad->getKeyType())->toBe('string')
        ->and($ad->incrementing)->toBeFalse();
});

it('orders images by created_at when position removed', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    // Create images in specific order
    $image1 = AdImage::factory()->create([
        'ad_id' => $ad->id,
        'created_at' => now()->subMinutes(2),
    ]);
    $image2 = AdImage::factory()->create([
        'ad_id' => $ad->id,
        'created_at' => now()->subMinutes(1),
    ]);
    $image3 = AdImage::factory()->create([
        'ad_id' => $ad->id,
        'created_at' => now(),
    ]);

    $images = $ad->fresh()->images;

    expect($images->pluck('id')->toArray())->toBe([
        $image1->id,
        $image2->id,
        $image3->id,
    ]);
});

it('automatically updates last_online_at when status changes to Online', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'status' => 'Entwurf',
        'last_online_at' => null,
    ]);

    expect($ad->last_online_at)->toBeNull();

    $ad->status = 'Online';
    $ad->save();

    expect($ad->fresh()->last_online_at)->not->toBeNull()
        ->and($ad->fresh()->last_online_at->isToday())->toBeTrue();
});

it('does not update last_online_at when status stays Online', function () {
    $user = User::factory()->create();
    $oldTimestamp = now()->subDays(5);

    $ad = Ad::factory()->for($user)->create([
        'status' => 'Online',
        'last_online_at' => $oldTimestamp,
    ]);

    $ad->title = 'Updated Title';
    $ad->save();

    expect($ad->fresh()->last_online_at->toDateTimeString())
        ->toBe($oldTimestamp->toDateTimeString());
});

it('does not update last_online_at when changing from Online to Archiviert', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'status' => 'Online',
        'last_online_at' => now()->subDays(3),
    ]);

    $oldTimestamp = $ad->last_online_at;

    $ad->status = 'Archiviert';
    $ad->save();

    expect($ad->fresh()->last_online_at->toDateTimeString())
        ->toBe($oldTimestamp->toDateTimeString());
});
