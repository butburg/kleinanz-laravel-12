<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdRequest;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('ads/Index', [
            'ads' => Ad::query()
                ->whereBelongsTo($request->user())
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ads/Create');
    }

    public function store(StoreAdRequest $request): RedirectResponse
    {
        $request->user()->ads()->create([
            ...$request->validated(),
            'status' => $request->validated('status') ?? config('ads.status.default'),
        ]);

        return to_route('ads.index');
    }

    public function edit(Ad $ad): Response
    {
        $this->authorize('update', $ad);

        return Inertia::render('ads/Edit', ['ad' => $ad]);
    }

    public function update(UpdateAdRequest $request, Ad $ad): RedirectResponse
    {
        $this->authorize('update', $ad);
        $ad->update($request->validated());

        return to_route('ads.index');
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $this->authorize('delete', $ad);
        $ad->delete();

        return to_route('ads.index');
    }
}
