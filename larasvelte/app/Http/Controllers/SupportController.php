<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportMessageRequest;
use App\Mail\SupportMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Support');
    }

    public function store(StoreSupportMessageRequest $request): RedirectResponse
    {
        $user = $request->user();
        $key = 'support-message:'.$user->id.':'.now()->toDateString();
        $decaySeconds = now()->diffInSeconds(now()->endOfDay()->addSecond());
        $supportAddress = config('mail.support.address');

        if (! is_string($supportAddress) || $supportAddress === '') {
            throw ValidationException::withMessages([
                'message' => 'Support email is not configured. Please try again later.',
            ]);
        }

        $sent = RateLimiter::attempt($key, 5, function () use ($supportAddress, $user, $request): void {
            Mail::to($supportAddress)->send(new SupportMessage($user, $request->validated('message')));
        }, $decaySeconds);

        if (! $sent) {
            throw ValidationException::withMessages([
                'message' => 'You can send a maximum of 5 support messages per day. Please try again tomorrow.',
            ]);
        }

        return to_route('support.create')->with('success', 'Your support message has been sent.');
    }
}
