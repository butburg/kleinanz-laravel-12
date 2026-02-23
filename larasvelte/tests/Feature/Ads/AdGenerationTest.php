<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('uses a mock response when the user has no api key', function (): void {
    Storage::fake('public');
    Http::fake();

    $user = User::factory()->create(['openai_api_key' => null]);
    $ad = Ad::factory()->for($user)->create();

    $thumbPath = 'ads/mock-thumb.jpg';
    Storage::disk('public')->put($thumbPath, str_repeat('a', 128));

    AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_thumb_path' => $thumbPath,
        'cropped_thumb_path' => null,
    ]);

    $this->actingAs($user)
        ->post(route('ads.generate', $ad), ['prompt_text' => 'Extra detail'])
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    $this->assertDatabaseHas('ads', [
        'id' => $ad->id,
        'title' => 'Beispielprodukt - Test',
        'condition' => 'Neu',
        'price' => 10,
        'shipping' => 'mittel',
    ]);

    Http::assertNothingSent();
});

it('calls the responses api when the user has an api key', function (): void {
    Storage::fake('public');

    Http::fake([
        '*' => Http::response([
            'output_text' => json_encode([
                'title' => 'Generated Title',
                'description' => str_repeat('Valid description. ', 4),
                'condition' => 'Gut',
                'price' => 42,
                'shipping' => 'klein',
            ], JSON_THROW_ON_ERROR),
        ], 200),
    ]);

    $user = User::factory()->create([
        'openai_api_key' => env('OPENAI_API_KEY', 'test-key'),
    ]);
    $ad = Ad::factory()->for($user)->create();

    $thumbPath = 'ads/api-thumb.jpg';
    Storage::disk('public')->put($thumbPath, str_repeat('b', 128));

    AdImage::factory()->for($ad)->create([
        'is_title' => true,
        'large_thumb_path' => $thumbPath,
        'cropped_thumb_path' => null,
    ]);

    $this->actingAs($user)
        ->post(route('ads.generate', $ad), ['prompt_text' => 'Prompt text'])
        ->assertRedirect(route('ads.edit', $ad, absolute: false));

    $this->assertDatabaseHas('ads', [
        'id' => $ad->id,
        'title' => 'Generated Title',
        'condition' => 'Gut',
        'price' => 42,
        'shipping' => 'klein',
    ]);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return str_contains($request->url(), '/responses')
            && ($payload['model'] ?? null) === config('ads.openai.model')
            && str_contains(
                $payload['input'][1]['content'][0]['text'] ?? '',
                'Prompt text'
            );
    });
});

it('rejects generation when no images exist', function (): void {
    $user = User::factory()->create(['openai_api_key' => null]);
    $ad = Ad::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('ads.generate', $ad))
        ->assertSessionHasErrors(['generate']);
});
