<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;

it('lets user switch the title image from the edit page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['title' => 'Image title switch']);
    $first = AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_path' => 'ads/sample-first.jpg',
        'large_thumb_path' => 'ads/sample-first-thumb.jpg',
        'original_name' => 'first.jpg',
    ]);
    $second = AdImage::factory()->for($ad)->create([
        'is_title' => false,
        'large_path' => 'ads/sample-second.jpg',
        'large_thumb_path' => 'ads/sample-second-thumb.jpg',
        'original_name' => 'second.jpg',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.edit', $ad, absolute: false));
    $setSecondTitleSelector = sprintf('form[action$="/ads/%d/images/%d/title"] button', $ad->id, $second->id);

    $page->assertSee('Edit Ad')
        ->assertSee('first.jpg')
        ->assertSee('second.jpg')
        ->click($setSecondTitleSelector)
        ->assertPathIs(route('ads.edit', $ad, absolute: false))
        ->assertSee('Title image');

    expect($first->fresh()?->is_title)->toBeFalse();
    expect($second->fresh()?->is_title)->toBeTrue();
});

it('lets user change the title image multiple times from the edit page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['title' => 'Multiple title switches']);
    $first = AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_path' => 'ads/switch-first.jpg',
        'large_thumb_path' => 'ads/switch-first-thumb.jpg',
        'original_name' => 'switch-first.jpg',
    ]);
    $second = AdImage::factory()->for($ad)->create([
        'is_title' => false,
        'large_path' => 'ads/switch-second.jpg',
        'large_thumb_path' => 'ads/switch-second-thumb.jpg',
        'original_name' => 'switch-second.jpg',
    ]);
    $third = AdImage::factory()->for($ad)->create([
        'is_title' => false,
        'large_path' => 'ads/switch-third.jpg',
        'large_thumb_path' => 'ads/switch-third-thumb.jpg',
        'original_name' => 'switch-third.jpg',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.edit', $ad, absolute: false));
    $setSecondTitleSelector = sprintf('form[action$="/ads/%d/images/%d/title"] button', $ad->id, $second->id);
    $setFirstTitleSelector = sprintf('form[action$="/ads/%d/images/%d/title"] button', $ad->id, $first->id);

    $page->assertSee('Edit Ad')
        ->assertSee('switch-first.jpg')
        ->assertSee('switch-second.jpg')
        ->assertSee('switch-third.jpg')
        ->click($setSecondTitleSelector)
        ->assertPathIs(route('ads.edit', $ad, absolute: false));

    expect($first->fresh()?->is_title)->toBeFalse();
    expect($second->fresh()?->is_title)->toBeTrue();
    expect($third->fresh()?->is_title)->toBeFalse();

    $page->click($setFirstTitleSelector)
        ->assertPathIs(route('ads.edit', $ad, absolute: false))
        ->assertSee('Title image');

    expect($first->fresh()?->is_title)->toBeTrue();
    expect($second->fresh()?->is_title)->toBeFalse();
    expect($third->fresh()?->is_title)->toBeFalse();
});

it('lets user delete a non-title image from the edit page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['title' => 'Delete non-title image']);
    $title = AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_path' => 'ads/delete-title.jpg',
        'large_thumb_path' => 'ads/delete-title-thumb.jpg',
        'original_name' => 'delete-title.jpg',
    ]);
    $toDelete = AdImage::factory()->for($ad)->create([
        'is_title' => false,
        'large_path' => 'ads/delete-me.jpg',
        'large_thumb_path' => 'ads/delete-me-thumb.jpg',
        'original_name' => 'delete-me.jpg',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.edit', $ad, absolute: false));
    $deleteSelector = sprintf('form[action$="/ads/%d/images/%d"] button', $ad->id, $toDelete->id);

    $page->assertSee('delete-title.jpg')
        ->assertSee('delete-me.jpg')
        ->click($deleteSelector)
        ->assertPathIs(route('ads.edit', $ad, absolute: false))
        ->assertDontSee('delete-me.jpg')
        ->assertSee('delete-title.jpg')
        ->assertSee('Title image');

    expect(AdImage::query()->whereKey($toDelete->id)->exists())->toBeFalse();
    expect($title->fresh()?->is_title)->toBeTrue();
});

it('promotes another image as title when deleting current title image from edit page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['title' => 'Delete title image']);
    $title = AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_path' => 'ads/old-title.jpg',
        'large_thumb_path' => 'ads/old-title-thumb.jpg',
        'original_name' => 'old-title.jpg',
    ]);
    $fallback = AdImage::factory()->for($ad)->create([
        'is_title' => false,
        'large_path' => 'ads/new-title.jpg',
        'large_thumb_path' => 'ads/new-title-thumb.jpg',
        'original_name' => 'new-title.jpg',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.edit', $ad, absolute: false));
    $deleteSelector = sprintf('form[action$="/ads/%d/images/%d"] button', $ad->id, $title->id);

    $page->assertSee('old-title.jpg')
        ->assertSee('new-title.jpg')
        ->click($deleteSelector)
        ->assertPathIs(route('ads.edit', $ad, absolute: false))
        ->assertDontSee('old-title.jpg')
        ->assertSee('new-title.jpg')
        ->assertSee('Title image');

    expect(AdImage::query()->whereKey($title->id)->exists())->toBeFalse();
    expect($fallback->fresh()?->is_title)->toBeTrue();
});

it('renders an image preview card for each ad image on edit page', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['title' => 'Preview grid']);
    $first = AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_path' => 'ads/grid-first.jpg',
        'large_thumb_path' => 'ads/grid-first-thumb.jpg',
        'original_name' => 'grid-first.jpg',
    ]);
    $second = AdImage::factory()->for($ad)->create([
        'is_title' => false,
        'large_path' => 'ads/grid-second.jpg',
        'large_thumb_path' => 'ads/grid-second-thumb.jpg',
        'original_name' => 'grid-second.jpg',
    ]);
    $third = AdImage::factory()->for($ad)->create([
        'is_title' => false,
        'large_path' => 'ads/grid-third.jpg',
        'large_thumb_path' => 'ads/grid-third-thumb.jpg',
        'original_name' => 'grid-third.jpg',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.edit', $ad, absolute: false));
    $firstPreviewCount = $page->script("document.querySelectorAll('img[alt=\"grid-first.jpg\"]').length");
    $secondPreviewCount = $page->script("document.querySelectorAll('img[alt=\"grid-second.jpg\"]').length");
    $thirdPreviewCount = $page->script("document.querySelectorAll('img[alt=\"grid-third.jpg\"]').length");

    $page->assertSee('grid-first.jpg')
        ->assertSee('grid-second.jpg')
        ->assertSee('grid-third.jpg');

    expect((int) $firstPreviewCount)->toBe(1);
    expect((int) $secondPreviewCount)->toBe(1);
    expect((int) $thirdPreviewCount)->toBe(1);
});
