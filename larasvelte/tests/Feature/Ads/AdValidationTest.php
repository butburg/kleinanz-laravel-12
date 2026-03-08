<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

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

it('does not require manual ad fields when using quick generate flow', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), [
        '_generate' => true,
        'images' => [UploadedFile::fake()->image('shirt.jpg')],
    ]);

    $response->assertSessionDoesntHaveErrors(['title', 'description', 'price', 'condition', 'shipping']);
});

it('requires images when using quick generate flow', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), [
        '_generate' => true,
    ]);

    $response->assertSessionHasErrors(['images']);
});

it('accepts quick generate payload even when manual fields are present as empty strings', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ads.store'), [
        '_generate' => true,
        'title' => '',
        'description' => '',
        'price' => '',
        'condition' => '',
        'shipping' => '',
        'images' => [UploadedFile::fake()->image('shirt.jpg')],
    ]);

    $response->assertSessionDoesntHaveErrors(['title', 'description', 'price', 'condition', 'shipping']);
});
