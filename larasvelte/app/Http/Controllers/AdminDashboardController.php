<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account from the admin dashboard.');

        $adStorageDirectories = $user->ads()
            ->pluck('id')
            ->map(fn (string $adId): string => "ads/{$adId}")
            ->all();

        DB::transaction(function () use ($user): void {
            $user->delete();
        });

        foreach ($adStorageDirectories as $directory) {
            Storage::disk('public')->deleteDirectory($directory);
        }

        return to_route('admin.dashboard')->with('success', 'User and all associated data deleted successfully.');
    }
}
