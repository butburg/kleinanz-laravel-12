<?php

use App\Models\Ad;
use App\Models\User;

it('uses single column list layout on ads index', function (): void {
    $user = User::factory()->create();
    Ad::factory()->for($user)->create(['title' => 'Responsive grid ad']);

    $this->actingAs($user);

    $page = visit(route('ads.index', absolute: false));
    $listClassName = (string) $page->script("document.querySelector('[data-test=\"ads-list\"]')?.className ?? ''");
    $listTagName = (string) $page->script("document.querySelector('[data-test=\"ads-list\"]')?.tagName ?? ''");

    $page->assertSee('Responsive grid ad');

    expect($listTagName)->toBe('UL');
    expect($listClassName)->not()->toContain('grid');
});
