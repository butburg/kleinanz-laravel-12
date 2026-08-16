<?php

use App\Models\Ad;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->legacyImportDirectory = sys_get_temp_dir().'/legacy-ads-import-'.uniqid();
    $this->legacySourceDirectory = "{$this->legacyImportDirectory}/source";
    $this->legacyStagingDirectory = "{$this->legacyImportDirectory}/staging";
    File::ensureDirectoryExists($this->legacySourceDirectory);
    Storage::fake('public');
});

afterEach(function (): void {
    File::deleteDirectory($this->legacyImportDirectory);
});

it('stages SQL exports and imports every ad for the configured owner', function (): void {
    $legacyId = '2026-08-16T18-00-00+00-00-test01';
    $original = 'legacy-original-image';
    $thumbnail = 'legacy-thumbnail-image';
    $payload = [
        'uuid' => $legacyId,
        'title' => 'Legacy title',
        'description' => 'A legacy description that is long enough for the current application.',
        'price' => 42,
        'user_owner' => 'legacy@example.test',
        'condition' => 'Gut',
        'shipping' => 'klein',
        'status' => 'Online',
        'images' => ['legacy.jpg'],
        'metadata' => [
            'created_at' => '2026-08-01T10:00:00+00:00',
            'last_online_at' => '2026-08-10T12:00:00+00:00',
        ],
        'prompt_text' => 'legacy prompt',
    ];
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $sqlJson = str_replace(['\\', "'"], ['\\\\', "\\'"], $json);
    File::put("{$this->legacySourceDirectory}/ads.sql", "INSERT INTO `ads` (`id`, `ad_json`) VALUES\n('{$legacyId}', '{$sqlJson}');\n");
    File::put("{$this->legacySourceDirectory}/ad_images.sql", "INSERT INTO `ad_images` (`id`, `ad_id`, `filename`, `image`, `thumbnail`) VALUES\n(9, '{$legacyId}', 'legacy.jpg', 0x".bin2hex($original).', 0x'.bin2hex($thumbnail).");\n");

    $this->artisan('legacy-ads:stage', [
        '--ads-file' => "{$this->legacySourceDirectory}/ads.sql",
        '--images-file' => "{$this->legacySourceDirectory}/ad_images.sql",
        '--output' => $this->legacyStagingDirectory,
    ])->assertExitCode(0);

    $owner = User::factory()->create(['email' => 'owner@example.test']);

    $this->artisan('legacy-ads:import', [
        '--manifest' => "{$this->legacyStagingDirectory}/manifest.jsonl",
        '--owner' => $owner->email,
    ])->expectsOutput('Validated 1 ads; skipped 0 existing ads.')->assertExitCode(0);

    expect(Ad::query()->count())->toBe(0);

    $this->artisan('legacy-ads:import', [
        '--manifest' => "{$this->legacyStagingDirectory}/manifest.jsonl",
        '--owner' => $owner->email,
        '--execute' => true,
    ])->expectsOutput('Imported 1 ads; skipped 0 existing ads.')->assertExitCode(0);

    $ad = Ad::query()->with('images')->findOrFail($legacyId);
    expect($ad->user_id)->toBe($owner->id)
        ->and($ad->last_online_at?->toIso8601String())->toBe('2026-08-10T12:00:00+00:00')
        ->and($ad->images)->toHaveCount(1)
        ->and($ad->images->first()?->is_title)->toBeTrue()
        ->and($ad->images->first()?->use_cropped)->toBeFalse()
        ->and($ad->metadata['legacy_user_owner'])->toBe('legacy@example.test');

    Storage::disk('public')->assertExists("ads/{$legacyId}/large/legacy.jpg");
    Storage::disk('public')->assertExists("ads/{$legacyId}/large_thumb/legacy.jpg");
    expect(Storage::disk('public')->get("ads/{$legacyId}/large/legacy.jpg"))->toBe($original)
        ->and(Storage::disk('public')->get("ads/{$legacyId}/large_thumb/legacy.jpg"))->toBe($thumbnail);

    $this->artisan('legacy-ads:import', [
        '--manifest' => "{$this->legacyStagingDirectory}/manifest.jsonl",
        '--owner' => $owner->email,
        '--execute' => true,
    ])->expectsOutput('Imported 0 ads; skipped 1 existing ads.')->assertExitCode(0);

    expect(Ad::query()->count())->toBe(1);
});

it('validates SQL exports without leaving a staging directory', function (): void {
    $payload = ['title' => 'Dry run', 'description' => 'Dry run description is deliberately long enough for validation.', 'price' => 1, 'condition' => 'Neu', 'shipping' => 'klein', 'status' => 'Entwurf'];
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $sqlJson = str_replace(['\\', "'"], ['\\\\', "\\'"], $json);
    File::put("{$this->legacySourceDirectory}/ads.sql", "INSERT INTO `ads` (`id`, `ad_json`) VALUES ('dry-run-id', '{$sqlJson}');\n");
    File::put("{$this->legacySourceDirectory}/ad_images.sql", "INSERT INTO `ad_images` (`id`, `ad_id`, `filename`, `image`, `thumbnail`) VALUES (1, 'dry-run-id', 'dry.jpg', 0x61, NULL);\n");

    $this->artisan('legacy-ads:stage', [
        '--ads-file' => "{$this->legacySourceDirectory}/ads.sql",
        '--images-file' => "{$this->legacySourceDirectory}/ad_images.sql",
        '--output' => $this->legacyStagingDirectory,
        '--dry-run' => true,
    ])->expectsOutput('Validated 1 ads and 1 images.')->assertExitCode(0);

    expect(File::exists($this->legacyStagingDirectory))->toBeFalse();
});
