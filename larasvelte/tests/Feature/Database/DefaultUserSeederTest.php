<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('seeds a deterministic default testing user', function (): void {
    Artisan::call('db:seed');

    $user = User::query()->where('email', 'default@example.test')->first();

    expect($user)->not->toBeNull();
    expect($user?->name)->toBe('Default Test User');
    expect(Hash::check('password', $user?->password ?? ''))->toBeTrue();
});

it('does not create duplicate default users when seeding twice', function (): void {
    Artisan::call('db:seed');
    Artisan::call('db:seed');

    expect(User::query()->where('email', 'default@example.test')->count())->toBe(1);
});
