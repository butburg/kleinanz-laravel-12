<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportLegacyAds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy-ads:import
        {--manifest= : Path to a staging manifest.jsonl file}
        {--owner= : Email address that will own every imported ad}
        {--execute : Write records and files; omit for a validation-only run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a staged legacy ads package into the current application';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $manifest = $this->option('manifest') ?: storage_path('app/private/legacy-ads-staging/manifest.jsonl');

        if (! is_file($manifest)) {
            $this->error("Manifest not found: {$manifest}");

            return self::FAILURE;
        }

        $ownerEmail = $this->option('owner') ?: $this->ask('Email address that will own every imported ad');

        if (! is_string($ownerEmail) || filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('A valid owner email address is required.');

            return self::FAILURE;
        }

        $owner = User::query()->where('email', $ownerEmail)->first();

        if ($owner === null) {
            $this->error("Owner not found: {$ownerEmail}");

            return self::FAILURE;
        }

        $stagingDirectory = dirname($manifest);
        $created = 0;
        $skipped = 0;

        try {
            foreach (file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $ad = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $this->validateAd($ad, $stagingDirectory);

                if (Ad::query()->whereKey($ad['legacy_id'])->exists()) {
                    $skipped++;

                    continue;
                }

                if (! $this->option('execute')) {
                    $created++;

                    continue;
                }

                $this->importAd($ad, $owner, $stagingDirectory);
                $created++;
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $mode = $this->option('execute') ? 'Imported' : 'Validated';
        $this->info("{$mode} {$created} ads; skipped {$skipped} existing ads.");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $payload */
    private function validateAd(array $payload, string $stagingDirectory): void
    {
        foreach (['legacy_id', 'title', 'description', 'price', 'condition', 'shipping', 'status', 'images'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new RuntimeException("Manifest ad is missing {$key}.");
            }
        }

        if (! is_string($payload['legacy_id']) || strlen($payload['legacy_id']) > 64) {
            throw new RuntimeException('Legacy ad id is invalid.');
        }

        if (! is_string($payload['title']) || mb_strlen($payload['title']) > 80) {
            throw new RuntimeException("Ad {$payload['legacy_id']} has an invalid title.");
        }

        if (! in_array($payload['condition'], config('ads.validation.conditions'), true)
            || ! in_array($payload['shipping'], config('ads.validation.shipping_options'), true)
            || ! in_array($payload['status'], config('ads.status.options'), true)) {
            throw new RuntimeException("Ad {$payload['legacy_id']} has unsupported option values.");
        }

        if (! is_array($payload['images']) || count($payload['images']) > config('ads.image.max_files')) {
            throw new RuntimeException("Ad {$payload['legacy_id']} has an invalid number of images.");
        }

        foreach ($payload['images'] as $image) {
            if (! is_array($image) || ! isset($image['original_path'], $image['thumbnail_path'])) {
                throw new RuntimeException("Ad {$payload['legacy_id']} has an invalid image manifest entry.");
            }

            foreach (['original_path', 'thumbnail_path'] as $pathKey) {
                $path = "{$stagingDirectory}/{$image[$pathKey]}";

                if (! is_file($path)) {
                    throw new RuntimeException("Staged image file is missing: {$path}");
                }

                $checksumKey = $pathKey === 'original_path' ? 'original_sha256' : 'thumbnail_sha256';

                if (! isset($image[$checksumKey]) || hash_file('sha256', $path) !== $image[$checksumKey]) {
                    throw new RuntimeException("Staged image checksum does not match: {$path}");
                }
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function importAd(array $payload, User $owner, string $stagingDirectory): void
    {
        $adId = $payload['legacy_id'];
        $storageDirectory = "ads/{$adId}";

        try {
            foreach ($payload['images'] as $image) {
                Storage::disk('public')->put("{$storageDirectory}/large/{$image['original_name']}", file_get_contents("{$stagingDirectory}/{$image['original_path']}"));
                Storage::disk('public')->put("{$storageDirectory}/large_thumb/{$image['original_name']}", file_get_contents("{$stagingDirectory}/{$image['thumbnail_path']}"));
            }

            DB::transaction(function () use ($payload, $owner, $storageDirectory): void {
                $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
                $ad = $owner->ads()->create([
                    'id' => $payload['legacy_id'],
                    'title' => $payload['title'],
                    'description' => $payload['description'],
                    'price' => (int) $payload['price'],
                    'condition' => $payload['condition'],
                    'shipping' => $payload['shipping'],
                    'status' => $payload['status'],
                    'last_online_at' => isset($metadata['last_online_at']) ? Carbon::parse($metadata['last_online_at']) : null,
                    'prompt_text' => $payload['prompt_text'] ?? null,
                    'metadata' => [...$metadata, 'legacy_id' => $payload['legacy_id'], 'legacy_user_owner' => $payload['user_owner'] ?? null],
                ]);

                if (isset($metadata['created_at'])) {
                    $ad->created_at = Carbon::parse($metadata['created_at']);
                    $ad->updated_at = $ad->created_at;
                    $ad->save();
                }

                foreach ($payload['images'] as $index => $image) {
                    $ad->images()->create([
                        'large_path' => "{$storageDirectory}/large/{$image['original_name']}",
                        'large_thumb_path' => "{$storageDirectory}/large_thumb/{$image['original_name']}",
                        'original_name' => $image['original_name'],
                        'is_title' => $index === 0,
                        'use_cropped' => false,
                        'metadata' => [
                            'legacy_image_id' => $image['legacy_image_id'],
                            'original_sha256' => $image['original_sha256'],
                            'thumbnail_sha256' => $image['thumbnail_sha256'],
                        ],
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->deleteDirectory($storageDirectory);

            throw new RuntimeException("Could not import ad {$adId}: {$exception->getMessage()}", previous: $exception);
        }
    }
}
