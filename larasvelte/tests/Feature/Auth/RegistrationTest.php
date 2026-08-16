<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    Notification::assertSentTo(User::query()->where('email', 'test@example.com')->firstOrFail(), VerifyEmail::class);
});

test('failed verification mail does not leave a registered user', function () {
    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('Mail delivery failed'));

    $response = $this->post('/register', [
        'name' => 'Failed User',
        'email' => 'failed@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertServerError();
    $this->assertDatabaseMissing('users', ['email' => 'failed@example.com']);
});