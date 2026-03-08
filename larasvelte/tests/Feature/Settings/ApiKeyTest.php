<?php

use App\Models\User;

describe('API Key Settings', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'openai_api_key' => null,
        ]);

        $this->actingAs($this->user);
    });

    describe('GET /settings/api-key', function () {
        it('displays the api key settings page', function () {
            $response = $this->get(route('api-key.edit'));

            $response->assertStatus(200);
            $response->assertInertia(
                fn($page) => $page
                    ->component('settings/ApiKey')
                    ->has('maskedApiKey', null)
            );
        });

        it('masks the api key when one exists', function () {
            $this->user->update([
                'openai_api_key' => 'sk-1234567890abcdef9999',
            ]);

            $response = $this->get(route('api-key.edit'));

            $response->assertInertia(
                fn($page) => $page
                    ->where('maskedApiKey', 'sk-12•••••••9999')
            );
        });
    });

    describe('POST /settings/api-key', function () {
        it('saves a valid openai api key', function () {
            $apiKey = 'sk-proj-validkeyformat12345';

            $response = $this->post(route('api-key.update'), [
                'openai_api_key' => $apiKey,
            ]);

            $response->assertRedirect(route('api-key.edit'));
            $response->assertSessionHas('status', 'API key saved successfully.');

            $this->user->refresh();

            // Check that the key is stored (Laravel auto-encrypts via cast)
            expect($this->user->openai_api_key)->toBe($apiKey);
        });

        it('updates an existing api key', function () {
            $oldKey = 'sk-proj-oldkeyformat1234567';
            $newKey = 'sk-proj-newkeyformat7654321';

            $this->user->update(['openai_api_key' => $oldKey]);

            $response = $this->post(route('api-key.update'), [
                'openai_api_key' => $newKey,
            ]);

            $response->assertRedirect(route('api-key.edit'));

            $this->user->refresh();
            expect($this->user->openai_api_key)->toBe($newKey);
        });

        it('rejects api keys that are too short', function () {
            $response = $this->post(route('api-key.update'), [
                'openai_api_key' => 'short',  // Only 5 chars, min is 10
            ]);

            $response->assertSessionHasErrors('openai_api_key');
        });

        it('accepts various api key formats', function () {
            $response = $this->post(route('api-key.update'), [
                'openai_api_key' => 'org-1234567890abcdef9999',  // org prefix works now
            ]);

            $response->assertRedirect(route('api-key.edit'));
            $this->user->refresh();
            expect($this->user->openai_api_key)->toBe('org-1234567890abcdef9999');
        });

        it('requires the api key field', function () {
            $response = $this->post(route('api-key.update'), []);

            $response->assertSessionHasErrors('openai_api_key');
        });
    });

    describe('DELETE /settings/api-key', function () {
        it('removes the api key', function () {
            $this->user->update([
                'openai_api_key' => 'sk-proj-existingkeyformat123',
            ]);

            $response = $this->delete(route('api-key.destroy'));

            $response->assertRedirect(route('api-key.edit'));
            $response->assertSessionHas('status', 'API key removed successfully.');

            $this->user->refresh();
            expect($this->user->openai_api_key)->toBeNull();
        });

        it('works even when no key is set', function () {
            $response = $this->delete(route('api-key.destroy'));

            $response->assertRedirect(route('api-key.edit'));
            expect($this->user->openai_api_key)->toBeNull();
        });
    });

    describe('Masking Logic', function () {
        it('masks short keys correctly', function () {
            $this->user->update(['openai_api_key' => 'sk-12']);

            $response = $this->get(route('api-key.edit'));

            // Short keys should be fully masked
            $response->assertInertia(
                fn($page) => $page
                    ->where('maskedApiKey', '••••••••')
            );
        });

        it('masks standard length keys', function () {
            $this->user->update(['openai_api_key' => 'sk-1234567890abcdef1234']);

            $response = $this->get(route('api-key.edit'));

            // Should show: sk-12 + •••••• + 1234
            $response->assertInertia(
                fn($page) => $page
                    ->where('maskedApiKey', 'sk-12•••••••1234')
            );
        });
    });

    describe('Session Status Messages', function () {
        it('includes status message on redirect after save', function () {
            $response = $this->post(route('api-key.update'), [
                'openai_api_key' => 'sk-proj-validfor12345678901',
            ]);

            expect($response->getSession()->get('status'))->toBe('API key saved successfully.');
        });

        it('includes status message on redirect after destroy', function () {
            $this->user->update(['openai_api_key' => 'sk-proj-validfor12345678901']);

            $response = $this->delete(route('api-key.destroy'));

            expect($response->getSession()->get('status'))->toBe('API key removed successfully.');
        });
    });

    describe('PATCH /settings/profile (Test Mode)', function () {
        it('can enable test mode', function () {
            $this->user->update(['use_test_mode' => false]);

            $response = $this->patch(route('profile.update'), [
                'use_test_mode' => true,
            ]);

            $response->assertRedirect();

            $this->user->refresh();
            expect($this->user->use_test_mode)->toBeTrue();
        });

        it('can disable test mode', function () {
            $this->user->update(['use_test_mode' => true]);

            $response = $this->patch(route('profile.update'), [
                'use_test_mode' => false,
            ]);

            $response->assertRedirect();

            $this->user->refresh();
            expect($this->user->use_test_mode)->toBeFalse();
        });

        it('preserves existing value when not provided in request', function () {
            $this->user->update(['use_test_mode' => true]);

            $response = $this->patch(route('profile.update'), [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]);

            $response->assertRedirect();

            $this->user->refresh();
            expect($this->user->use_test_mode)->toBeTrue();
        });

        it('passes use_test_mode to the view', function () {
            $this->user->update(['use_test_mode' => true]);

            $response = $this->get(route('api-key.edit'));

            $response->assertInertia(
                fn($page) => $page
                    ->where('useTestMode', true)
            );
        });

        it('persists across page refreshes', function () {
            $this->patch(route('profile.update'), ['use_test_mode' => true]);

            $response = $this->get(route('api-key.edit'));

            $response->assertInertia(
                fn($page) => $page
                    ->where('useTestMode', true)
            );

            $this->patch(route('profile.update'), ['use_test_mode' => false]);

            $response = $this->get(route('api-key.edit'));

            $response->assertInertia(
                fn($page) => $page
                    ->where('useTestMode', false)
            );
        });
    });
});
