<?php

use App\Models\User;

it('validates required ad fields on create', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), []);

    $response->assertSessionHasErrors([
        'title',
        'description',
        'price',
        'condition',
        'shipping',
    ]);
});

it('validates allowed enum values and description length', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), [
        'title' => 'X',
        'description' => 'too short',
        'price' => -1,
        'condition' => 'Invalid',
        'shipping' => 'huge',
    ]);

    $response->assertSessionHasErrors([
        'description',
        'price',
        'condition',
        'shipping',
    ]);
});
