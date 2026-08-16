<?php

use App\Models\User;

test('it deletes only unverified users older than the retention period', function () {
    $expired = User::factory()->unverified()->create([
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);
    $recent = User::factory()->unverified()->create([
        'created_at' => now()->subHours(23),
        'updated_at' => now()->subHours(23),
    ]);
    $verified = User::factory()->create([
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);

    $this->artisan('users:prune-unverified')
        ->assertExitCode(0);

    expect(User::query()->find($expired->id))->toBeNull()
        ->and(User::query()->find($recent->id))->not->toBeNull()
        ->and(User::query()->find($verified->id))->not->toBeNull();
});
