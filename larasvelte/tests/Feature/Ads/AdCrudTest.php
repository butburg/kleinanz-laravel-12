<?php

use App\Models\Ad;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from ads pages', function (): void {
    $this->get(route('ads.index'))->assertRedirect(route('login'));
    $this->get(route('ads.create'))->assertRedirect(route('login'));
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

    $this->assertDatabaseHas('ads', [
        'user_id' => $user->id,
        'title' => 'Vintage Denim Jacket',
        'price' => 45,
        'status' => 'Entwurf',
    ]);
});

it('renders dedicated ads pages for authenticated users', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ads/Index')
            ->has('ads', 1)
        );

    $this->actingAs($user)
        ->get(route('ads.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ads/Create')
        );

    $this->actingAs($user)
        ->get(route('ads.edit', $ad))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
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
        ->assertInertia(fn (Assert $page) => $page->has('ads', 1));
});

it('lets owners update and delete their ad', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create([
        'title' => 'Old title',
    ]);

    $updateResponse = $this->actingAs($user)->patch(route('ads.update', $ad), [
        'title' => 'Updated title',
        'description' => str_repeat('Updated description. ', 4),
        'price' => 55,
        'condition' => 'Sehr gut',
        'shipping' => 'mittel',
        'status' => 'Online',
    ]);

    $updateResponse->assertRedirect(route('ads.index', absolute: false));
    $this->assertDatabaseHas('ads', [
        'id' => $ad->id,
        'title' => 'Updated title',
        'status' => 'Online',
    ]);

    $deleteResponse = $this->actingAs($user)->delete(route('ads.destroy', $ad));

    $deleteResponse->assertRedirect(route('ads.index', absolute: false));
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
        'status' => 'Archiviert',
    ])->assertForbidden();

    $this->actingAs($intruder)->delete(route('ads.destroy', $ad))->assertForbidden();
});
