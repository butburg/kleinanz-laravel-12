<?php

namespace App\Console\Commands;

use App\Models\AdImage;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportImageBlobs extends Command
{
    protected $signature = 'import:image-blobs {--sql-file=}';
    protected $description = 'Import image blobs from ad_images.sql and save to storage';

    public function handle(): int
    {
        $sqlFile = $this->option('sql-file') ?? base_path('../no_laravel/old ad data/ad_images.sql');

        if (!file_exists($sqlFile)) {
            $this->error("SQL file not found: $sqlFile");
            return 1;
        }

        $this->info('Starting image blob import...');
        $this->info('Processing SQL file line by line...');

        $handle = fopen($sqlFile, 'r');
        if (!$handle) {
            $this->error("Could not open SQL file");
            return 1;
        }

        $imported = 0;
        $errors = 0;

        while (($line = fgets($handle)) !== false) {
            // Check if this line is an INSERT statement
            if (str_contains($line, 'INSERT INTO')) {
                // Read next line which should contain VALUES
                $valuesLine = fgets($handle);
                if (!$valuesLine) {
                    continue;
                }

                // Parse VALUES line with regex
                // Format: (id, 'uuid', 'filename', 0xHEXIMAGE, 0xHEXTHUMB);
                if (preg_match('/\((\d+),\s*\'([^\']+)\',\s*\'([^\']+)\',\s*0x([0-9a-f]+),\s*0x([0-9a-f]+)\)/i', $valuesLine, $match)) {
                    $id = $match[1];
                    $adId = $match[2];
                    $filename = $match[3];
                    $imageHex = $match[4];
                    $thumbHex = $match[5];

                    try {
                        $this->processImageBlob($id, $adId, $filename, $imageHex, $thumbHex);
                        $imported++;
                        $this->line("✓ Image $filename");
                    } catch (Exception $e) {
                        $this->warn("✗ Error for $filename: " . $e->getMessage());
                        $errors++;
                    }
                }
            }
        }

        fclose($handle);
        $this->info("Imported $imported images" . ($errors > 0 ? " ($errors errors)" : ""));
        return 0;
    }

    private function processImageBlob(string $id, string $adId, string $filename, string $imageHex, string $thumbHex): void
    {
        // Find matching AdImage record
        $adImage = AdImage::where('ad_id', $adId)
            ->where('original_name', $filename)
            ->first();

        if (!$adImage) {
            throw new Exception("AdImage not found for ad_id=$adId, filename=$filename");
        }

        // Convert hex to binary
        $imageBinary = hex2bin($imageHex);
        $thumbBinary = hex2bin($thumbHex);

        if ($imageBinary === false || $thumbBinary === false) {
            throw new Exception("Failed to decode hex data");
        }

        // Ensure directories exist and save images
        if ($adImage->large_path) {
            Storage::disk('public')->put($adImage->large_path, $imageBinary);
        }

        if ($adImage->large_thumb_path) {
            Storage::disk('public')->put($adImage->large_thumb_path, $thumbBinary);
        }
    }
}
