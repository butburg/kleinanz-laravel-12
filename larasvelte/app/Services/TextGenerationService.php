<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TextGenerationService
{
    private const MIN_IMAGE_BYTES = 64;

    private string $systemPrompt;
    private string $adExamples;

    public function __construct()
    {
        $this->systemPrompt = $this->readPrompt('system_prompt.txt');
        $this->adExamples = trim($this->readPrompt('ad_examples.txt'));
    }

    /**
     * @return array{title: string, description: string, condition: string, price: int, shipping: string}
     */
    public function generateForAd(Ad $ad, User $user, ?string $promptText = null): array
    {
        $imagePath = $this->resolveTitleImagePath($ad);
        if ($imagePath === null) {
            throw new TextGenerationException('No images available to generate from.');
        }

        if (! Storage::disk('public')->exists($imagePath)) {
            throw new TextGenerationException('Title image was not found on disk.');
        }

        $imageBytes = Storage::disk('public')->get($imagePath);
        if ($imageBytes === null || strlen($imageBytes) < self::MIN_IMAGE_BYTES) {
            throw new TextGenerationException('Title image payload is too small to generate from.');
        }

        $imageBase64 = base64_encode($imageBytes);

        if (! $user->openai_api_key) {
            return $this->loadMockResponse();
        }

        $responsePayload = $this->callOpenAi($user->openai_api_key, $imageBase64, $promptText);
        $outputText = $this->extractOutputText($responsePayload);
        $decoded = $this->decodeJson($outputText);

        return $this->validatePayload($decoded);
    }

    private function readPrompt(string $fileName): string
    {
        $path = base_path('resources/prompts/ads/' . $fileName);
        if (! is_file($path)) {
            throw new TextGenerationException("Missing prompt file: {$fileName}");
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw new TextGenerationException("Prompt file is empty: {$fileName}");
        }

        return $contents;
    }

    private function resolveTitleImagePath(Ad $ad): ?string
    {
        $image = $ad->images()->where('is_title', true)->first() ?? $ad->images()->first();

        return $image?->cropped_thumb_path ?? $image?->large_thumb_path;
    }

    /**
     * @return array<string, mixed>
     */
    private function callOpenAi(string $apiKey, string $imageBase64, ?string $promptText): array
    {
        $payload = [
            'model' => config('ads.openai.model'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $this->buildUserInstruction($promptText),
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:image/jpeg;base64,{$imageBase64}",
                            ],
                        ],
                    ],
                ],
            ],
            'temperature' => config('ads.openai.temperature'),
            'max_output_tokens' => config('ads.openai.max_tokens'),
            'store' => false,
        ];

        $response = Http::timeout(config('ads.openai.timeout'))
            ->withToken($apiKey)
            ->post($this->openAiUrl('/responses'), $payload);

        if (! $response->successful()) {
            throw new TextGenerationException(
                'OpenAI request failed: ' . $response->status() . ' ' . $response->body()
            );
        }

        return $response->json();
    }

    private function openAiUrl(string $suffix): string
    {
        $baseUrl = rtrim((string) config('services.openai.url'), '/');

        return $baseUrl . $suffix;
    }

    private function buildUserInstruction(?string $promptText): string
    {
        $parts = [
            "Hier sind Beispiel-Titel und -Beschreibung im gewuenschten Stil:\n\n{$this->adExamples}\n\n",
            "Bitte erstelle eine neue Anzeige fuer den folgenden Gegenstand im Bild.\n\n",
        ];

        if ($promptText && trim($promptText) !== '') {
            $cleanPrompt = trim($promptText);
            $parts[] = "Zusatzinformationen des Nutzers (weil z.B. nicht erkennbar im Bild):\n\n\"{$cleanPrompt}\"";
        }

        return implode('', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $rawText): array
    {
        $rawText = trim($rawText);

        $decoded = json_decode($rawText, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $rawText, $matches) === 1) {
            $candidate = $matches[0];
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new TextGenerationException('OpenAI response did not contain valid JSON.');
    }

    /**
     * @return array{title: string, description: string, condition: string, price: int, shipping: string}
     */
    private function validatePayload(array $payload): array
    {
        $payload['price'] = is_numeric($payload['price'] ?? null) ? (int) $payload['price'] : null;

        $validator = Validator::make($payload, [
            'title' => ['required', 'string', 'max:' . config('ads.validation.title_max_length')],
            'description' => [
                'required',
                'string',
                'min:' . config('ads.validation.description_min_length'),
                'max:' . config('ads.validation.description_max_length'),
            ],
            'condition' => ['required', 'string', 'in:' . implode(',', config('ads.validation.conditions'))],
            'price' => ['required', 'integer', 'min:0'],
            'shipping' => ['required', 'string', 'in:' . implode(',', config('ads.validation.shipping_options'))],
        ]);

        if ($validator->fails()) {
            throw new TextGenerationException('OpenAI response failed validation: ' . $validator->errors()->first());
        }

        /** @var array{title: string, description: string, condition: string, price: int, shipping: string} $data */
        $data = $validator->validated();

        return $data;
    }

    /**
     * @return array{title: string, description: string, condition: string, price: int, shipping: string}
     */
    private function loadMockResponse(): array
    {
        return [
            'title' => 'Beispielprodukt - Test',
            'description' => 'Dies ist nur ein Beispiel, da nicht automatisch erstellt wurde.\n\nWeitere Angebote sind ueber mein Profil einsehbar.',
            'condition' => 'Neu',
            'price' => 10,
            'shipping' => 'mittel',
        ];
    }

    private function extractOutputText(array $responsePayload): string
    {
        if (isset($responsePayload['output_text']) && is_string($responsePayload['output_text'])) {
            return $responsePayload['output_text'];
        }

        $output = $responsePayload['output'] ?? [];
        $collected = '';

        if (! is_array($output)) {
            throw new TextGenerationException('OpenAI response missing output content.');
        }

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            $content = $item['content'] ?? [];
            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $part) {
                if (! is_array($part)) {
                    continue;
                }

                if (($part['type'] ?? null) === 'output_text' && isset($part['text'])) {
                    $collected .= (string) $part['text'];
                } elseif (isset($part['text']) && is_string($part['text'])) {
                    $collected .= $part['text'];
                }
            }
        }

        $collected = trim($collected);

        if ($collected === '') {
            throw new TextGenerationException('OpenAI response contained no usable text.');
        }

        return $collected;
    }
}
