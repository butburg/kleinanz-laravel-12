<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('shows users and their activity to the configured admin', function (): void {
    config()->set('app.admin_mail', 'admin@example.com');

    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'created_at' => now()->subDays(3),
    ]);
    $firstAd = Ad::factory()->for($user)->create(['platform' => 'Kleinanzeigen']);
    $secondAd = Ad::factory()->for($user)->create(['platform' => 'eBay']);
    AdImage::factory()->for($firstAd)->count(2)->create();
    AdImage::factory()->for($secondAd)->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('AdminDashboard')
                ->where('auth.user.is_admin', true)
                ->where('users', fn (Collection $users): bool => $users->contains(
                    fn (array $dashboardUser): bool => $dashboardUser['id'] === $user->id
                        && $dashboardUser['name'] === 'Jane Doe'
                        && $dashboardUser['email'] === 'jane@example.com'
                        && $dashboardUser['ads_count'] === 2
                        && $dashboardUser['images_count'] === 3
                        && $dashboardUser['platforms'] === ['Kleinanzeigen', 'eBay']
                        && $dashboardUser['created_at'] === $user->created_at->toIso8601String()
                ))
        );
});

it('forbids users whose email is not configured as admin', function (): void {
    config()->set('app.admin_mail', 'admin@example.com');

    $this->actingAs(User::factory()->create(['email' => 'member@example.com']))
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
