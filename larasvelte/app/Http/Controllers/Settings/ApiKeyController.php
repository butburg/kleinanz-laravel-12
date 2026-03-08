<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateApiKeyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
            'useTestMode' => $user->use_test_mode,
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
     * Test the user's OpenAI API key.
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $apiKey = $user->openai_api_key;

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured',
            ], 400);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->get('https://api.openai.com/v1/models', [
                    'limit' => 1,
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'API key is valid! ✓',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'API key validation failed: ' . ($response->json('error.message') ?? 'Unknown error'),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ], 500);
        }
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
