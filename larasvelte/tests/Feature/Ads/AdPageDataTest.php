<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('provides select options to the index page for inline ad creation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('options.conditions', config('ads.validation.conditions'))
                ->where('options.shipping', config('ads.validation.shipping_options'))
                ->where('options.statuses', config('ads.status.options'))
                ->where('options.limits.prompt', config('ads.validation.prompt_max_length'))
        );
});

it('provides ad and options to the edit page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ads.edit', $ad))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Edit')
                ->where('ad.id', $ad->id)
                ->where('options.statuses', config('ads.status.options'))
                ->where('options.limits.prompt', config('ads.validation.prompt_max_length'))
        );
});

it('provides previous and next ads for edit-page navigation', function (): void {
    $user = User::factory()->create();
    $oldestAd = Ad::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    $currentAd = Ad::factory()->for($user)->create(['created_at' => now()->subDay()]);
    $newestAd = Ad::factory()->for($user)->create(['created_at' => now()]);
    Ad::factory()->create(['created_at' => now()->addDay()]);

    $this->actingAs($user)
        ->get(route('ads.edit', $currentAd))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->where('navigation.previousAdId', $newestAd->id)
                ->where('navigation.nextAdId', $oldestAd->id)
        );
});

it('uses the large thumbnail as the preview url in edit page image payload', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/preview-large.jpg',
        'large_thumb_path' => 'ads/preview-large-thumb.jpg',
        'cropped_path' => null,
        'cropped_thumb_path' => null,
    ]);

    $this->actingAs($user)
        ->get(route('ads.edit', $ad))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Edit')
                ->where('ad.images.0.id', $image->id)
                ->where('ad.images.0.url', Storage::disk('public')->url('ads/preview-large-thumb.jpg'))
                ->where('ad.images.0.variants.large', Storage::disk('public')->url('ads/preview-large.jpg'))
                ->where('ad.images.0.variants.large_thumb', Storage::disk('public')->url('ads/preview-large-thumb.jpg'))
                ->where('ad.images.0.variants.cropped', null)
                ->where('ad.images.0.variants.cropped_thumb', null)
        );
});

it('uses the cropped thumbnail as the preview url when available', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/cropped-large.jpg',
        'large_thumb_path' => 'ads/cropped-large-thumb.jpg',
        'cropped_path' => 'ads/cropped.jpg',
        'cropped_thumb_path' => 'ads/cropped-thumb.jpg',
    ]);

    $this->actingAs($user)
        ->get(route('ads.edit', $ad))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Edit')
                ->where('ad.images.0.id', $image->id)
                ->where('ad.images.0.url', Storage::disk('public')->url('ads/cropped-thumb.jpg'))
                ->where('ad.images.0.variants.cropped', Storage::disk('public')->url('ads/cropped.jpg'))
                ->where('ad.images.0.variants.cropped_thumb', Storage::disk('public')->url('ads/cropped-thumb.jpg'))
        );
});

it('includes title image thumbnail url for each ad on the index page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_thumb_path' => 'ads/list-title-thumb.jpg',
        'cropped_thumb_path' => null,
    ]);

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('ads.data.0.id', $ad->id)
                ->where('ads.data.0.thumbnail_url', Storage::disk('public')->url('ads/list-title-thumb.jpg'))
        );
});

it('prefers cropped thumbnail url for ad list item when available', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    AdImage::factory()->for($ad)->create([
        'is_title' => true,

        'large_thumb_path' => 'ads/list-large-thumb.jpg',
        'cropped_thumb_path' => 'ads/list-cropped-thumb.jpg',
    ]);

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('ads.data.0.id', $ad->id)
                ->where('ads.data.0.thumbnail_url', Storage::disk('public')->url('ads/list-cropped-thumb.jpg'))
        );
});

it('includes status color indicator for each ad on index page', function (): void {
    $user = User::factory()->create();
    $draft = Ad::factory()->for($user)->create(['status' => 'Entwurf']);
    $online = Ad::factory()->for($user)->create(['status' => 'Online']);
    $archived = Ad::factory()->for($user)->create(['status' => 'Archiviert']);

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('ads.data', fn($ads): bool => collect($ads)->contains(fn(array $ad): bool => $ad['id'] === $archived->id && $ad['status_color'] === 'zinc'))
                ->where('ads.data', fn($ads): bool => collect($ads)->contains(fn(array $ad): bool => $ad['id'] === $online->id && $ad['status_color'] === 'green'))
                ->where('ads.data', fn($ads): bool => collect($ads)->contains(fn(array $ad): bool => $ad['id'] === $draft->id && $ad['status_color'] === 'amber'))
        );
});

it('includes title and description text for copy actions on index page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'title' => 'Copy source title',
        'description' => 'Copy source description',
    ]);

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('ads.data.0.id', $ad->id)
                ->where('ads.data.0.title', 'Copy source title')
                ->where('ads.data.0.description', 'Copy source description')
        );
});

it('includes per-image download urls for list view', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $firstImage = AdImage::factory()->for($ad)->create(['original_name' => 'first-download.jpg']);
    $secondImage = AdImage::factory()->for($ad)->create(['original_name' => 'second-download.jpg']);

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('ads.data.0.id', $ad->id)
                ->where('ads.data.0.images.0.id', $firstImage->id)
                ->where('ads.data.0.images.0.download_url', route('ads.images.download', [$ad, $firstImage], absolute: false))
                ->where('ads.data.0.images.1.id', $secondImage->id)
                ->where('ads.data.0.images.1.download_url', route('ads.images.download', [$ad, $secondImage], absolute: false))
        );
});

it('computes expiry indicator fields for online ads based on last_online_at', function (): void {
    Carbon::setTestNow('2026-02-18 10:00:00');

    $user = User::factory()->create();
    $activeAd = Ad::factory()->for($user)->create([
        'status' => 'Online',
        'last_online_at' => now()->subDays(30),
    ]);
    $expiredAd = Ad::factory()->for($user)->create([
        'status' => 'Online',
        'last_online_at' => now()->subDays(61),
    ]);

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('ads.data', fn($ads): bool => collect($ads)->contains(fn(array $ad): bool => $ad['id'] === $activeAd->id && $ad['is_expired'] === false && $ad['days_to_expiry'] === 30))
                ->where('ads.data', fn($ads): bool => collect($ads)->contains(fn(array $ad): bool => $ad['id'] === $expiredAd->id && $ad['is_expired'] === true && $ad['days_to_expiry'] === -1))
        );

    Carbon::setTestNow();
});
