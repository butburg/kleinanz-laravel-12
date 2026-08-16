<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppendixRequest;
use App\Http\Requests\UpdateAppendixRequest;
use App\Models\Appendix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppendixController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Appendices', [
            'appendices' => $request->user()
                ->appendices()
                ->orderBy('platform')
                ->get(['id', 'platform', 'content'])
                ->all(),
            'limit' => 4,
        ]);
    }

    public function store(StoreAppendixRequest $request): RedirectResponse
    {
        $request->user()->appendices()->create($request->validated());

        return back()->with('success', 'Platform appendix saved.');
    }

    public function update(UpdateAppendixRequest $request, Appendix $appendix): RedirectResponse
    {
        $appendix->update($request->validated());

        return back()->with('success', 'Platform appendix updated.');
    }

    public function destroy(Request $request, Appendix $appendix): RedirectResponse
    {
        $this->authorize('delete', $appendix);

        $appendix->delete();

        return back()->with('success', 'Platform appendix deleted.');
    }
}
