<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateApiKeyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    /**
     * Show the API key settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $maskedApiKey = null;

        if ($user->openai_api_key) {
            $maskedApiKey = $this->maskApiKey($user->openai_api_key);
        }

        return Inertia::render('settings/ApiKey', [
            'maskedApiKey' => $maskedApiKey,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's OpenAI API key.
     */
    public function update(UpdateApiKeyRequest $request): RedirectResponse
    {
        $request->user()->update([
            'openai_api_key' => $request->validated('openai_api_key'),
        ]);

        return to_route('api-key.edit')
            ->with('status', 'API key saved successfully.');
    }

    /**
     * Remove the user's OpenAI API key.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->update([
            'openai_api_key' => null,
        ]);

        return to_route('api-key.edit')
            ->with('status', 'API key removed successfully.');
    }

    /**
     * Mask the API key for display.
     * Shows first 5 and last 4 characters (sk-XX).
     */
    private function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 9) {
            return '••••••••';
        }

        $first = substr($apiKey, 0, 5);  // sk-XX
        $last = substr($apiKey, -4);

        return "{$first}•••••••{$last}";
    }
}
