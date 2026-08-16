<?php

namespace App\Console\Commands;

use App\Services\LegacyAdsSqlReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class StageLegacyAds extends Command
{
    private int $orphanedImages = 0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy-ads:stage
        {--ads-file= : Path to the legacy ads SQL export}
        {--images-file= : Path to the legacy ad_images SQL export}
        {--output= : Directory for the manifest and decoded image files}
        {--dry-run : Validate the exports without writing a staging package}
        {--overwrite : Replace an existing staging package}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decode legacy SQL exports into an inspectable manifest and image files';

    /**
     * Execute the console command.
     */
    public function handle(LegacyAdsSqlReader $reader): int
    {
        $sourceDirectory = base_path('../no_laravel/import_legacy_ads/old ad data');
        $adsFile = $this->option('ads-file') ?: "{$sourceDirectory}/ads.sql";
        $imagesFile = $this->option('images-file') ?: "{$sourceDirectory}/ad_images.sql";
        $output = $this->option('output') ?: storage_path('app/private/legacy-ads-staging');

        foreach ([$adsFile, $imagesFile] as $file) {
            if (! is_file($file)) {
                $this->error("SQL export not found: {$file}");

                return self::FAILURE;
            }
        }

        if (File::exists($output) && ! $this->option('overwrite')) {
            $this->error("Staging directory already exists: {$output}. Use --overwrite to replace it.");

            return self::FAILURE;
        }

        try {
            $ads = $this->readAds($reader, $adsFile);

            if (! $this->option('dry-run')) {
                File::deleteDirectory($output);
                File::ensureDirectoryExists($output);
            }

            $imageCount = $this->stageImages($reader, $imagesFile, $ads, $output, (bool) $this->option('dry-run'));
            $this->writeManifest($ads, $output, (bool) $this->option('dry-run'));
        } catch (\Throwable $exception) {
            if (! $this->option('dry-run')) {
                File::deleteDirectory($output);
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $mode = $this->option('dry-run') ? 'Validated' : 'Staged';
        $orphanedImages = $this->orphanedImages === 0 ? '' : "; skipped {$this->orphanedImages} orphaned images";
        $this->info("{$mode} ".count($ads)." ads and {$imageCount} images{$orphanedImages}.");

        return self::SUCCESS;
    }

    /** @return array<string, array<string, mixed>> */
    private function readAds(LegacyAdsSqlReader $reader, string $adsFile): array
    {
        $ads = [];

        foreach ($reader->rows($adsFile, 'ads') as $row) {
            if (count($row) !== 2 || $row[0] === null || $row[1] === null) {
                throw new RuntimeException('An ads row does not contain an id and JSON payload.');
            }

            $payload = json_decode($row[1], true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                throw new RuntimeException("Ad {$row[0]} does not contain a JSON object.");
            }

            $defaults = [
                'price' => 0,
                'condition' => config('ads.validation.conditions.0'),
                'shipping' => config('ads.validation.shipping_options.0'),
                'status' => config('ads.status.default'),
            ];
            $appliedDefaults = [];

            foreach ($defaults as $key => $default) {
                if (! array_key_exists($key, $payload) || $payload[$key] === null) {
                    $payload[$key] = $default;
                    $appliedDefaults[$key] = $default;
                }
            }

            if ($appliedDefaults !== []) {
                $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
                $payload['metadata'] = [...$metadata, 'legacy_import_defaults' => $appliedDefaults];
            }

            $payload['legacy_id'] = $row[0];
            $payload['images'] = [];
            $ads[$row[0]] = $payload;
        }

        if ($ads === []) {
            throw new RuntimeException('No ads were found in the SQL export.');
        }

        return $ads;
    }

    /** @param array<string, array<string, mixed>> $ads */
    private function stageImages(LegacyAdsSqlReader $reader, string $imagesFile, array &$ads, string $output, bool $dryRun): int
    {
        $imageCount = 0;

        foreach ($reader->rows($imagesFile, 'ad_images') as $row) {
            if (count($row) !== 5 || $row[0] === null || $row[1] === null || $row[2] === null || $row[3] === null) {
                throw new RuntimeException('An ad_images row is incomplete.');
            }

            [$legacyImageId, $adId, $filename, $imageHex, $thumbnailHex] = $row;

            if (! isset($ads[$adId])) {
                $this->orphanedImages++;

                continue;
            }

            $original = $this->decodeHex($imageHex, "image {$legacyImageId}");
            $thumbnail = $thumbnailHex === null ? $original : $this->decodeHex($thumbnailHex, "thumbnail {$legacyImageId}");
            $safeFilename = basename($filename);
            $relativeDirectory = "images/{$adId}";
            $relativeOriginal = "{$relativeDirectory}/{$legacyImageId}-{$safeFilename}";
            $relativeThumbnail = "{$relativeDirectory}/{$legacyImageId}-thumb-{$safeFilename}";

            if (! $dryRun) {
                File::ensureDirectoryExists("{$output}/{$relativeDirectory}");
                File::put("{$output}/{$relativeOriginal}", $original);
                File::put("{$output}/{$relativeThumbnail}", $thumbnail);
            }

            $ads[$adId]['images'][] = [
                'legacy_image_id' => (int) $legacyImageId,
                'original_name' => $safeFilename,
                'original_path' => $relativeOriginal,
                'thumbnail_path' => $relativeThumbnail,
                'original_sha256' => hash('sha256', $original),
                'thumbnail_sha256' => hash('sha256', $thumbnail),
            ];
            $imageCount++;
        }

        return $imageCount;
    }

    /** @param array<string, array<string, mixed>> $ads */
    private function writeManifest(array $ads, string $output, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $lines = [];

        foreach ($ads as $ad) {
            $lines[] = json_encode($ad, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        File::put("{$output}/manifest.jsonl", implode("\n", $lines)."\n");
    }

    private function decodeHex(string $value, string $label): string
    {
        if (! str_starts_with(strtolower($value), '0x')) {
            throw new RuntimeException("Invalid {$label} BLOB value.");
        }

        $binary = hex2bin(substr($value, 2));

        if ($binary === false) {
            throw new RuntimeException("Could not decode {$label} BLOB value.");
        }

        return $binary;
    }
}
