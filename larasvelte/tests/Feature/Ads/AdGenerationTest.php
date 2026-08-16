<?php

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\Appendix;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('passes the prompt through first generation and saves every generated detail', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'openai_api_key' => 'test-key',
    ]);
    Appendix::factory()->for($user)->create([
        'platform' => 'Kleinanzeigen',
        'content' => 'Privatverkauf. Keine Rücknahme.',
    ]);

    Http::fake([
        '*' => Http::response([
            'output_text' => json_encode([
                'title' => 'First Generated Title',
                'description' => str_repeat('First generated description. ', 4),
                'condition' => 'Sehr gut',
                'price' => 64,
                'shipping' => 'mittel',
            ], JSON_THROW_ON_ERROR),
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('ads.store'), [
            '_generate' => true,
            'prompt_text' => 'Blue wool coat with a loose fit',
            'platform' => 'Kleinanzeigen',
            'auto_crop_enabled' => false,
            'images' => [UploadedFile::fake()->image('coat.jpg')],
        ])
        ->assertRedirect(route('ads.index', absolute: false));

    $this->assertDatabaseHas('ads', [
        'user_id' => $user->id,
        'title' => 'First Generated Title',
        'description' => trim(str_repeat('First generated description. ', 4))."\n\nPrivatverkauf. Keine Rücknahme.",
        'price' => 64,
        'condition' => 'Sehr gut',
        'shipping' => 'mittel',
        'prompt_text' => 'Blue wool coat with a loose fit',
        'platform' => 'Kleinanzeigen',
    ]);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->data()['input'][1]['content'][0]['text'] ?? '',
        'Blue wool coat with a loose fit'
    ));
});

it('uses a mock response when the user has no api key', function (): void {
    Storage::fake('public');
    Http::fake();

    $user = User::factory()->create(['openai_api_key' => null]);
    Appendix::factory()->for($user)->create([
        'platform' => 'Kleinanzeigen',
        'content' => 'Privatverkauf. Keine Rücknahme.',
    ]);
    $ad = Ad::factory()->for($user)->create(['platform' => 'Kleinanzeigen']);

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
    Appendix::factory()->for($user)->create([
        'platform' => 'Kleinanzeigen',
        'content' => 'Privatverkauf. Keine Rücknahme.',
    ]);
    $ad = Ad::factory()->for($user)->create(['platform' => 'Kleinanzeigen']);

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
        'description' => trim(str_repeat('Valid description. ', 4))."\n\nPrivatverkauf. Keine Rücknahme.",
        'condition' => 'Gut',
        'price' => 42,
        'shipping' => 'klein',
        'prompt_text' => 'Prompt text',
    ]);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return str_contains($request->url(), '/responses')
            && ($payload['model'] ?? null) === config('ads.openai.model')
            && ($payload['text']['format'] ?? null) === [
                'type' => 'json_schema',
                'name' => 'generated_ad',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'condition' => [
                            'type' => 'string',
                            'enum' => config('ads.validation.conditions'),
                        ],
                        'price' => ['type' => 'integer'],
                        'shipping' => [
                            'type' => 'string',
                            'enum' => config('ads.validation.shipping_options'),
                        ],
                    ],
                    'required' => ['title', 'description', 'condition', 'price', 'shipping'],
                    'additionalProperties' => false,
                ],
            ]
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
