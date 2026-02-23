<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;

it('shows per-image download actions on ads index', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create(['title' => 'Download list ad']);
    AdImage::factory()->for($ad)->count(2)->create();

    $this->actingAs($user);

    $page = visit(route('ads.index', absolute: false));

    $page->assertSee('Download list ad')
        ->assertSee('Download all images');
});
