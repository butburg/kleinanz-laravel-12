<?php

use App\Models\Ad;
use App\Models\User;
use Illuminate\Support\Carbon;

it('shows expiry date and expired indicator for online ads on index', function (): void {
    Carbon::setTestNow('2026-02-18 10:00:00');

    $user = User::factory()->create();
    Ad::factory()->for($user)->create([
        'title' => 'Expired online ad',
        'status' => 'Online',
        'last_online_at' => now()->subDays(61),
    ]);

    $this->actingAs($user);

    $page = visit(route('ads.index', absolute: false));

    $page->assertSee('Expired online ad')
        ->assertSee('Expires 2026-02-17')
        ->assertSee('(Expired)');

    Carbon::setTestNow();
});
