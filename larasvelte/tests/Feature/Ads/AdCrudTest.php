<?php

use App\Models\Ad;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from ads pages', function (): void {
    $this->get(route('ads.index'))->assertRedirect(route('login'));
});

it('lets authenticated users create an ad', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'Vintage Denim Jacket',
        'description' => str_repeat('Great condition. ', 5),
        'price' => 45,
        'condition' => 'Gut',
        'shipping' => 'klein',
    ]);

    $response->assertRedirect(route('ads.index', absolute: false));
    $response->assertSessionHas('success', 'Ad created successfully.');

    $this->assertDatabaseHas('ads', [
        'user_id' => $user->id,
        'title' => 'Vintage Denim Jacket',
        'price' => 45,
        'status' => 'Draft',
    ]);
});

it('renders dedicated ads pages for authenticated users', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->has('ads.data', 1)
                ->has('options') // Index page now includes options for inline create
        );

    $this->actingAs($user)
        ->get(route('ads.edit', $ad))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Edit')
                ->where('ad.id', $ad->id)
        );
});

it('lists only ads that belong to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Ad::factory()->for($user)->create();
    Ad::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->has('ads.data', 1));
});

it('paginates ads list for authenticated users', function (): void {
    $user = User::factory()->create();
    Ad::factory()->count(101)->for($user)->create();

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->has('ads.data', 10)
                ->where('perPage', '10')
                ->where('ads.current_page', 1)
                ->where('ads.last_page', 11)
        );

    $this->actingAs($user)
        ->get(route('ads.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->has('ads.data', 10)
                ->where('ads.current_page', 2)
        );

    foreach ([20, 50, 100] as $perPage) {
        $this->actingAs($user)
            ->get(route('ads.index', ['per_page' => $perPage]))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('ads/Index')
                    ->has('ads.data', $perPage)
                    ->where('perPage', (string) $perPage)
            );
    }

    $this->actingAs($user)
        ->get(route('ads.index', ['per_page' => 'all']))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->has('ads.data', 101)
                ->where('perPage', 'all')
                ->where('ads.last_page', 1)
        );
});

it('filters ads by the selected status', function (): void {
    $user = User::factory()->create();
    $soldAd = Ad::factory()->for($user)->create(['status' => 'Sold']);
    Ad::factory()->for($user)->create(['status' => 'Draft']);

    $this->actingAs($user)
        ->get(route('ads.index', ['status' => 'Sold', 'per_page' => 20]))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('ads/Index')
                ->where('statusFilter', 'Sold')
                ->where('perPage', '20')
                ->has('ads.data', 1)
                ->where('ads.data.0.id', $soldAd->id)
        );
});

it('lets owners update and delete their ad', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'title' => 'Old title',
    ]);

    $updateResponse = $this->actingAs($user)
        ->from(route('ads.edit', $ad)) // Set referrer so back() works correctly
        ->patch(route('ads.update', $ad), [
            'title' => 'Updated title',
            'description' => str_repeat('Updated description. ', 4),
            'price' => 55,
            'condition' => 'Sehr gut',
            'shipping' => 'mittel',
            'status' => 'Online',
        ]);

    $updateResponse->assertRedirect(route('ads.edit', $ad)); // back() redirects to edit page
    $updateResponse->assertSessionHas('success', 'Ad updated successfully.');
    $this->assertDatabaseHas('ads', [
        'id' => $ad->id,
        'title' => 'Updated title',
        'status' => 'Online',
    ]);

    $deleteResponse = $this->actingAs($user)->delete(route('ads.destroy', $ad));

    $deleteResponse->assertRedirect(route('ads.index', absolute: false));
    $deleteResponse->assertSessionHas('success', 'Ad deleted successfully.');
    $this->assertDatabaseMissing('ads', [
        'id' => $ad->id,
    ]);
});

it('forbids modifying ads owned by another user', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ad = Ad::factory()->for($owner)->create();

    $this->actingAs($intruder)->patch(route('ads.update', $ad), [
        'title' => 'Intruder title',
        'description' => str_repeat('Forbidden edit. ', 4),
        'price' => 5,
        'condition' => 'Defekt',
        'shipping' => 'klein',
        'status' => 'Archived',
    ])->assertForbidden();

    $this->actingAs($intruder)->delete(route('ads.destroy', $ad))->assertForbidden();
});

it('tracks last_online_at when creating ad directly as Online', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'Online from create',
        'description' => str_repeat('Created online description. ', 3),
        'price' => 33,
        'condition' => 'Gut',
        'shipping' => 'klein',
        'status' => 'Online',
    ])->assertRedirect(route('ads.index', absolute: false));

    $ad = Ad::query()->where('user_id', $user->id)->latest()->firstOrFail();

    expect($ad->status)->toBe('Online');
    expect($ad->last_online_at)->not->toBeNull();
});

it('tracks last_online_at when status transitions from non-online to Online', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'status' => 'Draft',
        'last_online_at' => null,
    ]);

    $this->actingAs($user)
        ->from(route('ads.edit', $ad))
        ->patch(route('ads.update', $ad), [
            'title' => $ad->title,
            'description' => $ad->description,
            'price' => $ad->price,
            'condition' => $ad->condition,
            'shipping' => $ad->shipping,
            'status' => 'Online',
        ])->assertRedirect(route('ads.edit', $ad));

    expect($ad->fresh()?->last_online_at)->not->toBeNull();
});

it('does not change last_online_at when ad remains Online', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'status' => 'Online',
        'last_online_at' => now()->subDay(),
    ]);
    $initialLastOnlineAt = $ad->last_online_at;

    $this->actingAs($user)
        ->from(route('ads.edit', $ad))
        ->patch(route('ads.update', $ad), [
            'title' => 'Updated while online',
            'description' => $ad->description,
            'price' => $ad->price + 1,
            'condition' => $ad->condition,
            'shipping' => $ad->shipping,
            'status' => 'Online',
        ])->assertRedirect(route('ads.edit', $ad));

    expect($ad->fresh()?->last_online_at?->toDateTimeString())->toBe($initialLastOnlineAt?->toDateTimeString());
});

it('allows updating ad status from the dedicated status endpoint', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'status' => 'Draft',
        'last_online_at' => null,
    ]);
    $listUrl = route('ads.index', [
        'page' => 2,
        'per_page' => 50,
        'status' => 'Draft',
    ]);

    $this->actingAs($user)
        ->from($listUrl)
        ->patch(route('ads.status.update', $ad), [
            'status' => 'Online',
        ])
        ->assertRedirect($listUrl)
        ->assertSessionHas('success', 'Ad status updated successfully.');

    expect($ad->fresh()?->status)->toBe('Online');
    expect($ad->fresh()?->last_online_at)->not()->toBeNull();
});
