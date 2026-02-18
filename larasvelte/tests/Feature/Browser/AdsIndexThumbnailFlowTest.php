<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;

it('renders gallery thumbnail for ad list item', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['title' => 'Thumbnail list ad']);
    AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'position' => 0,
        'large_thumb_path' => 'ads/list-thumb.jpg',
        'cropped_thumb_path' => null,
        'original_name' => 'list-thumb.jpg',
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.index', absolute: false));
    $thumbnailSelector = 'img[alt="Thumbnail for Thumbnail list ad"]';

    $page->assertSee('Thumbnail list ad')->wait(1);

    $thumbnailCount = $page->script(sprintf("document.querySelectorAll('%s').length", $thumbnailSelector));
    $thumbnailSrc = $page->script(sprintf("document.querySelector('%s')?.getAttribute('src')", $thumbnailSelector));

    expect((int) $thumbnailCount)->toBe(1);
    expect((string) $thumbnailSrc)->toContain('/storage/ads/list-thumb.jpg');
});
