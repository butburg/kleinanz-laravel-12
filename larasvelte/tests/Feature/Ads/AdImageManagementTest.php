<?php

use App\Jobs\AutoCropImage;
use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Bus::fake();
});

it('stores up to ten images and marks the first image as title on create', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'Ad with images',
        'description' => str_repeat('Description for image ad. ', 3),
        'price' => 12,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'images' => [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
            UploadedFile::fake()->image('third.jpg'),
        ],
    ]);

    $response->assertRedirect(route('ads.index', absolute: false));

    $ad = Ad::query()->where('user_id', $user->id)->latest()->firstOrFail();

    expect(AdImage::query()->where('ad_id', $ad->id)->count())->toBe(3);
    expect(AdImage::query()->where('ad_id', $ad->id)->where('is_title', true)->count())->toBe(1);
    expect(AdImage::query()->where('ad_id', $ad->id)->oldest()->first()?->is_title)->toBeTrue();

    $storedImage = AdImage::query()->where('ad_id', $ad->id)->firstOrFail();

    expect($storedImage->large_path)->not->toBeNull();
    expect($storedImage->large_thumb_path)->not->toBeNull();
    expect($storedImage->original_name)->not->toBe('');
});

it('accepts avif uploads and stores variant paths', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'AVIF Ad',
        'description' => str_repeat('AVIF image description text. ', 3),
        'price' => 19,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'images' => [
            UploadedFile::fake()->create('sample.avif', 120, 'image/avif'),
        ],
    ]);

    $response->assertRedirect(route('ads.index', absolute: false));

    $ad = Ad::query()->where('user_id', $user->id)->latest()->firstOrFail();
    $image = AdImage::query()->where('ad_id', $ad->id)->firstOrFail();

    expect($image->original_name)->toBe('sample.avif');
    expect($image->large_path)->toContain('.avif');
    expect($image->large_thumb_path)->toContain('.avif');
});

it('rejects creating ads with more than ten images', function (): void {
    $user = User::factory()->create();

    $images = collect(range(1, 11))
        ->map(fn(int $i): UploadedFile => UploadedFile::fake()->image("file-{$i}.jpg"))
        ->all();

    $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'Too many images ad',
        'description' => str_repeat('Description for max check. ', 3),
        'price' => 9,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'images' => $images,
    ])->assertSessionHasErrors('images');
});

it('uses the first uploaded image as title image when no title index is provided', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'Default title image ad',
        'description' => str_repeat('Description for default title image. ', 3),
        'price' => 13,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'images' => [
            UploadedFile::fake()->image('default-first.jpg'),
            UploadedFile::fake()->image('default-second.jpg'),
        ],
    ])->assertRedirect(route('ads.index', absolute: false));

    $ad = Ad::query()->where('user_id', $user->id)->latest()->firstOrFail();

    expect(AdImage::query()->where('ad_id', $ad->id)->oldest()->first()?->is_title)->toBeTrue();
    expect(AdImage::query()->where('ad_id', $ad->id)->where('is_title', true)->count())->toBe(1);
});

it('uses the selected title image index during quick generate create flow', function (): void {
    Storage::fake('public');

    $user = User::factory()->create(['openai_api_key' => null]);

    $this->actingAs($user)->post(route('ads.store'), [
        '_generate' => true,
        'prompt_text' => 'A blue jacket',
        'images' => [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
            UploadedFile::fake()->image('third.jpg'),
        ],
        'title_image_index' => 1,
    ])->assertRedirect(route('ads.index', absolute: false));

    $ad = Ad::query()->where('user_id', $user->id)->latest()->firstOrFail();
    $images = AdImage::query()->where('ad_id', $ad->id)->oldest()->get();

    expect($images)->toHaveCount(3);
    expect($images[0]->is_title)->toBeFalse();
    expect($images[1]->is_title)->toBeTrue();
    expect($images[2]->is_title)->toBeFalse();
});

it('rejects unsupported image formats on create', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'Unsupported format ad',
        'description' => str_repeat('Description for unsupported format. ', 3),
        'price' => 11,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'images' => [
            UploadedFile::fake()->create('animated.gif', 120, 'image/gif'),
        ],
    ])->assertSessionHasErrors('images.0');
});

it('allows adding more images during edit without changing existing title image', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $title = AdImage::factory()->for($ad)->create(['is_title' => true]);
    AdImage::factory()->for($ad)->create(['is_title' => false]);

    $this->actingAs($user)->post(route('ads.images.store', $ad), [
        'images' => [
            UploadedFile::fake()->image('new-first.jpg'),
            UploadedFile::fake()->image('new-second.jpg'),
        ],
    ])->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect(AdImage::query()->where('ad_id', $ad->id)->count())->toBe(4);
    expect($title->fresh()?->is_title)->toBeTrue();
    expect(AdImage::query()->where('ad_id', $ad->id)->count())->toBe(4);
});

it('rejects adding images that exceed the total maximum for an ad', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    AdImage::factory()->for($ad)->create(['is_title' => true]);
    AdImage::factory()->for($ad)->count(8)->create(['is_title' => false]);

    $this->actingAs($user)->post(route('ads.images.store', $ad), [
        'images' => [
            UploadedFile::fake()->image('extra-first.jpg'),
            UploadedFile::fake()->image('extra-second.jpg'),
        ],
    ])
        ->assertSessionHasErrors('images')
        ->assertSessionHas('error', 'Maximum image count exceeded.');

    expect(AdImage::query()->where('ad_id', $ad->id)->count())->toBe(9);
});

it('allows selecting a different title image and keeps exactly one title image', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $first = AdImage::factory()->for($ad)->create(['is_title' => true]);
    $second = AdImage::factory()->for($ad)->create(['is_title' => false]);

    $this->actingAs($user)
        ->patch(route('ads.images.set-title', [$ad, $second]))
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect($first->fresh()?->is_title)->toBeFalse();
    expect($second->fresh()?->is_title)->toBeTrue();
    expect(AdImage::query()->where('ad_id', $ad->id)->where('is_title', true)->count())->toBe(1);
});

it('promotes another image as title when deleting the current title image', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $title = AdImage::factory()->for($ad)->create(['is_title' => true]);
    $fallback = AdImage::factory()->for($ad)->create(['is_title' => false]);

    $this->actingAs($user)
        ->delete(route('ads.images.destroy', [$ad, $title]))
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect(AdImage::query()->whereKey($title->id)->exists())->toBeFalse();
    expect($fallback->fresh()?->is_title)->toBeTrue();
});

it('allows owner to download an ad image', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/downloadable-large.jpg',
        'original_name' => 'downloadable.jpg',
    ]);

    Storage::disk('public')->put('ads/downloadable-large.jpg', 'image-content');

    $response = $this->actingAs($user)->get(route('ads.images.download', [$ad, $image]));

    $response->assertOk();
    $response->assertHeader('content-disposition', 'attachment; filename=downloadable.jpg');
});

it('forbids downloading an image from another users ad', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ad = Ad::factory()->for($owner)->create();
    $image = AdImage::factory()->for($ad)->create();

    $this->actingAs($intruder)
        ->get(route('ads.images.download', [$ad, $image]))
        ->assertForbidden();
});

it('allows owner to toggle cropped preference per image', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/crop-toggle-large.jpg',
        'large_thumb_path' => 'ads/crop-toggle-large-thumb.jpg',
        'cropped_path' => 'ads/crop-toggle-cropped.jpg',
        'cropped_thumb_path' => 'ads/crop-toggle-cropped-thumb.jpg',
        'use_cropped' => true,
    ]);

    Storage::disk('public')->put('ads/crop-toggle-large.jpg', 'large-content');
    Storage::disk('public')->put('ads/crop-toggle-large-thumb.jpg', 'large-thumb-content');
    Storage::disk('public')->put('ads/crop-toggle-cropped.jpg', 'cropped-content');
    Storage::disk('public')->put('ads/crop-toggle-cropped-thumb.jpg', 'cropped-thumb-content');

    $this->actingAs($user)
        ->patch(route('ads.images.crop-preference', [$ad, $image]), ['use_cropped' => false])
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect($image->fresh()?->use_cropped)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('ads.images.crop-preference', [$ad, $image]), ['use_cropped' => true])
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect($image->fresh()?->use_cropped)->toBeTrue();
});

it('toggles between cropped and original via the crop toggle endpoint when cropped files exist', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/toggle-large.jpg',
        'large_thumb_path' => 'ads/toggle-large-thumb.jpg',
        'cropped_path' => 'ads/toggle-cropped.jpg',
        'cropped_thumb_path' => 'ads/toggle-cropped-thumb.jpg',
        'use_cropped' => true,
    ]);

    Storage::disk('public')->put('ads/toggle-large.jpg', 'large-content');
    Storage::disk('public')->put('ads/toggle-large-thumb.jpg', 'large-thumb-content');
    Storage::disk('public')->put('ads/toggle-cropped.jpg', 'cropped-content');
    Storage::disk('public')->put('ads/toggle-cropped-thumb.jpg', 'cropped-thumb-content');

    $this->actingAs($user)
        ->post(route('ads.images.toggle-crop', [$ad, $image]))
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect($image->fresh()?->use_cropped)->toBeFalse();

    $this->actingAs($user)
        ->post(route('ads.images.toggle-crop', [$ad, $image]))
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect($image->fresh()?->use_cropped)->toBeTrue();
});

it('starts manual crop with zero threshold via crop toggle endpoint when no cropped files exist', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/manual-large.jpg',
        'large_thumb_path' => 'ads/manual-large-thumb.jpg',
        'cropped_path' => null,
        'cropped_thumb_path' => null,
        'use_cropped' => false,
    ]);

    Storage::disk('public')->put('ads/manual-large.jpg', 'large-content');
    Storage::disk('public')->put('ads/manual-large-thumb.jpg', 'large-thumb-content');

    $this->actingAs($user)
        ->post(route('ads.images.toggle-crop', [$ad, $image]))
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    expect($image->fresh()?->use_cropped)->toBeTrue();

    Bus::assertDispatchedSync(AutoCropImage::class, function (AutoCropImage $job) use ($image): bool {
        return $job->adImage->id === $image->id
            && $job->detectionThreshold === 0.0
            && $job->closeupThreshold === 1.0;
    });
});

it('returns refreshed image status payload for the ad edit screen', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/status-large.jpg',
        'large_thumb_path' => 'ads/status-large-thumb.jpg',
        'cropped_path' => 'ads/status-cropped.jpg',
        'cropped_thumb_path' => 'ads/status-cropped-thumb.jpg',
        'use_cropped' => true,
        'metadata' => [
            'crop_status' => 'completed',
            'cropped_size' => [673, 843],
            'cropped_at' => now()->toIso8601String(),
        ],
    ]);

    Storage::disk('public')->put('ads/status-large.jpg', 'large-content');
    Storage::disk('public')->put('ads/status-large-thumb.jpg', 'large-thumb-content');
    Storage::disk('public')->put('ads/status-cropped.jpg', 'cropped-content');
    Storage::disk('public')->put('ads/status-cropped-thumb.jpg', 'cropped-thumb-content');

    $response = $this->actingAs($user)->get(route('ads.images.status', $ad));

    $response->assertOk()
        ->assertJsonPath('images.0.id', $image->id)
        ->assertJsonPath('images.0.is_cropped', true)
        ->assertJsonPath('images.0.use_cropped', true)
        ->assertJsonPath('images.0.crop_metadata.crop_status', 'completed')
        ->assertJsonPath('images.0.crop_metadata.cropped_size.0', 673)
        ->assertJsonPath('images.0.crop_metadata.cropped_size.1', 843);
});

it('downloads original image when use_cropped is disabled even if cropped path exists', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => 'ads/download-choice-large.jpg',
        'cropped_path' => 'ads/download-choice-cropped.jpg',
        'original_name' => 'choice.jpg',
        'use_cropped' => false,
    ]);

    Storage::disk('public')->put('ads/download-choice-large.jpg', 'large-content');

    $response = $this->actingAs($user)->get(route('ads.images.download', [$ad, $image]));

    $response->assertOk();
    $response->assertHeader('content-disposition', 'attachment; filename=choice.jpg');
});
