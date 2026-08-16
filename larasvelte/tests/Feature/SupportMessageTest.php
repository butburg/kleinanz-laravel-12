<?php

use App\Mail\SupportMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['mail.support.address' => 'support@example.com']);
    RateLimiter::clear('support-message:1:'.now()->toDateString());
});

test('authenticated users can send a support message', function () {
    Mail::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('support.store'), [
        'message' => 'I need help with my ad.',
    ]);

    $response->assertRedirect(route('support.create', absolute: false));
    $response->assertSessionHas('success', 'Your support message has been sent.');

    Mail::assertSent(SupportMessage::class, function (SupportMessage $mail) use ($user): bool {
        return $mail->hasTo('support@example.com')
            && $mail->user->is($user)
            && $mail->supportMessage === 'I need help with my ad.';
    });
});

test('authenticated users can view the support page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('support.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Support'));
});

test('users cannot send more than five support messages per day', function () {
    Mail::fake();
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)->post(route('support.store'), [
            'message' => "Support message {$attempt}",
        ])->assertRedirect(route('support.create', absolute: false));
    }

    $this->actingAs($user)->post(route('support.store'), [
        'message' => 'This should be blocked.',
    ])->assertSessionHasErrors('message');

    Mail::assertSent(SupportMessage::class, 5);
});

test('support messages require text', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('support.store'), [
        'message' => '',
    ])->assertSessionHasErrors('message');
});
