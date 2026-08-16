<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\Appendix;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

it('allows the configured admin to delete a user and all associated data', function (): void {
    Storage::fake('public');
    config()->set('app.admin_mail', 'admin@example.com');

    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $image = AdImage::factory()->for($ad)->create([
        'large_path' => "ads/{$ad->id}/large/image.jpg",
        'large_thumb_path' => "ads/{$ad->id}/large_thumb/image.jpg",
        'cropped_path' => "ads/{$ad->id}/cropped/image.jpg",
        'cropped_thumb_path' => "ads/{$ad->id}/cropped_thumb/image.jpg",
    ]);
    $appendix = Appendix::factory()->for($user)->create();

    Storage::disk('public')->put($image->large_path, 'large');
    Storage::disk('public')->put($image->large_thumb_path, 'large thumb');
    Storage::disk('public')->put($image->cropped_path, 'cropped');
    Storage::disk('public')->put($image->cropped_thumb_path, 'cropped thumb');

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('ads', ['id' => $ad->id]);
    $this->assertDatabaseMissing('ad_images', ['id' => $image->id]);
    $this->assertDatabaseMissing('appendices', ['id' => $appendix->id]);
    Storage::disk('public')->assertMissing("ads/{$ad->id}/large/image.jpg");
    Storage::disk('public')->assertMissing("ads/{$ad->id}/large_thumb/image.jpg");
    Storage::disk('public')->assertMissing("ads/{$ad->id}/cropped/image.jpg");
    Storage::disk('public')->assertMissing("ads/{$ad->id}/cropped_thumb/image.jpg");
});

it('forbids non-admins from deleting users', function (): void {
    config()->set('app.admin_mail', 'admin@example.com');

    $user = User::factory()->create();

    $this->actingAs(User::factory()->create(['email' => 'member@example.com']))
        ->delete(route('admin.users.destroy', $user))
        ->assertForbidden();
});

it('prevents an admin from deleting their own account from the dashboard', function (): void {
    config()->set('app.admin_mail', 'admin@example.com');

    $admin = User::factory()->create(['email' => 'admin@example.com']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertUnprocessable();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});
