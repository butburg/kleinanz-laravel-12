<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        $users = User::query()
            ->withCount(['ads', 'images'])
            ->with(['ads' => fn ($query) => $query
                ->select(['id', 'user_id', 'platform'])
                ->whereNotNull('platform')])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'ads_count' => $user->ads_count,
                'images_count' => $user->images_count,
                'platforms' => $user->ads
                    ->pluck('platform')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
                'created_at' => $user->created_at->toIso8601String(),
            ])
            ->all();

        return Inertia::render('AdminDashboard', [
            'users' => $users,
        ]);
    }
}
