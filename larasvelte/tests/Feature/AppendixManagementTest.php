<?php

use App\Models\Appendix;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lets a user manage up to four platform appendices', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('appendices.store'), [
            'platform' => 'Kleinanzeigen',
            'content' => 'Privatverkauf. Keine Rücknahme.',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('appendices', [
        'user_id' => $user->id,
        'platform' => 'Kleinanzeigen',
        'content' => 'Privatverkauf. Keine Rücknahme.',
    ]);

    Appendix::factory()->count(3)->for($user)->create();

    $this->actingAs($user)
        ->post(route('appendices.store'), [
            'platform' => 'Vinted',
            'content' => 'Versand möglich.',
        ])
        ->assertSessionHasErrors('platform');
});

it('only shows the authenticated users platform appendices', function (): void {
    $user = User::factory()->create();
    Appendix::factory()->for($user)->create([
        'platform' => 'Kleinanzeigen',
        'content' => 'Privatverkauf.',
    ]);
    Appendix::factory()->create();

    $this->actingAs($user)
        ->get(route('appendices.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Appendices')
            ->has('appendices', 1)
            ->where('appendices.0.platform', 'Kleinanzeigen')
        );
});

it('allows a platform with an empty appendix', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('appendices.store'), [
            'platform' => 'Facebook Marketplace',
            'content' => '',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('appendices', [
        'user_id' => $user->id,
        'platform' => 'Facebook Marketplace',
        'content' => '',
    ]);
});

it('allows users to delete their own appendix', function (): void {
    $user = User::factory()->create();
    $appendix = Appendix::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('appendices.destroy', $appendix))
        ->assertRedirect();

    $this->assertDatabaseMissing('appendices', ['id' => $appendix->id]);
});

it('prevents users from updating another users appendix', function (): void {
    $appendix = Appendix::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('appendices.update', $appendix), [
            'platform' => 'Changed',
            'content' => 'Changed content.',
        ])
        ->assertForbidden();
});
