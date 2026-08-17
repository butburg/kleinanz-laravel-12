<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use DateTime;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOldAds extends Command
{
    protected $signature = 'import:old-ads {--ads-file=} {--images-file=}';
    protected $description = 'Import ads from old JSON-based SQL backup into new relational structure';

    public function handle(): int
    {
        $adsFile = $this->option('ads-file') ?? base_path('../no_laravel/old ad data/ads.sql');
        $imagesFile = $this->option('images-file') ?? base_path('../no_laravel/old ad data/ad_images.sql');

        if (!file_exists($adsFile)) {
            $this->error("Ads file not found: $adsFile");
            return 1;
        }

        $this->info('Starting import of old ads data...');

        try {
            $this->importAds($adsFile);
            $this->info('✓ Import completed successfully!');
            return 0;
        } catch (Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function importAds(string $adsFile): void
    {
        $content = file_get_contents($adsFile);

        // Find all INSERT statements and extract the VALUES part
        preg_match_all("/INSERT INTO `ads`[^V]*VALUES\s*(.+?);/s", $content, $matches);

        if (empty($matches[1])) {
            $this->warn('No INSERT statements found in ads.sql');
            return;
        }

        $count = 0;
        $errors = 0;

        foreach ($matches[1] as $valuesBlock) {
            // Parse each row manually using a simple state machine
            $rows = $this->parseInsertValues($valuesBlock);

            foreach ($rows as $row) {
                if (count($row) === 2) {
                    [$uuid, $jsonStr] = $row;
                    try {
                        $this->processAdFromJson($uuid, $jsonStr);
                        $count++;
                    } catch (Exception $e) {
                        $this->warn("Error processing ad $uuid: " . $e->getMessage());
                        $errors++;
                    }
                }
            }
        }

        $this->info("Successfully imported $count ads" . ($errors > 0 ? " ($errors errors)" : ""));
    }

    private function parseInsertValues(string $valuesBlock): array
    {
        $rows = [];
        $inString = false;
        $currentValue = '';
        $currentRow = [];
        $escapeNext = false;

        for ($i = 0; $i < strlen($valuesBlock); $i++) {
            $char = $valuesBlock[$i];

            if ($escapeNext) {
                $currentValue .= $char;
                $escapeNext = false;
                continue;
            }

            if ($char === '\\') {
                $escapeNext = true;
                continue;
            }

            if ($char === "'" && !$escapeNext) {
                $inString = !$inString;
                continue;
            }

            if (!$inString) {
                if ($char === ',' || $char === ')') {
                    if ($currentValue !== '') {
                        $currentRow[] = $currentValue;
                        $currentValue = '';
                    }
                    if ($char === ')' && count($currentRow) === 2) {
                        $rows[] = $currentRow;
                        $currentRow = [];
                    }
                    continue;
                }
                if ($char === '(') {
                    continue;
                }
            }

            if ($inString || !in_array($char, [' ', "\n", "\r", "\t"])) {
                $currentValue .= $char;
            }
        }

        return $rows;
    }

    private function processAdFromJson(string $uuid, string $jsonStr): void
    {
        // The JSON comes from SQL with specific escaping:
        // \' -> '
        // \" -> "
        // \\ -> \
        // But keep \n, \r, \t as-is for JSON decoder
        $jsonStr = str_replace(["\\'", '\\"'], ["'", '"'], $jsonStr);

        $adData = json_decode($jsonStr, true);

        if (!$adData) {
            $this->warn("Failed to parse JSON for $uuid: " . json_last_error_msg());
            return;
        }

        // Find or create user
        $userEmail = $adData['user_owner'] ?? null;
        if (!$userEmail) {
            throw new Exception("No user_owner found");
        }

        $user = User::firstOrCreate(
            ['email' => $userEmail],
            [
                'name' => explode('@', $userEmail)[0],
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Skip if ad already exists
        if (Ad::where('id', $uuid)->exists()) {
            $this->line("⊘ Ad already exists: $uuid");
            return;
        }

        // Create ad with proper ID (bypassing auto-increment)
        $ad = new Ad([
            'id' => $uuid,
            'user_id' => $user->id,
            'title' => $adData['title'] ?? 'Untitled',
            'description' => $adData['description'] ?? '',
            'price' => (int)($adData['price'] ?? 0),
            'condition' => $adData['condition'] ?? 'Neu',
            'shipping' => $adData['shipping'] ?? 'klein',
            'status' => $adData['status'] ?? 'Draft',
            'prompt_text' => $adData['prompt_text'] ?? null,
            'metadata' => json_encode($adData['metadata'] ?? []),
        ]);

        // Set timestamps if available
        if (isset($adData['metadata']['created_at'])) {
            try {
                $ad->created_at = new DateTime($adData['metadata']['created_at']);
                $ad->updated_at = new DateTime($adData['metadata']['created_at']);
            } catch (Exception $e) {
                // Use defaults
            }
        }

        // Insert without incrementing
        DB::table('ads')->insert($ad->getAttributes());

        // Process images
        if (isset($adData['images']) && is_array($adData['images'])) {
            foreach ($adData['images'] as $index => $imageName) {
                AdImage::create([
                    'ad_id' => $uuid,
                    'large_path' => 'ads/' . $uuid . '/large/' . $imageName,
                    'large_thumb_path' => 'ads/' . $uuid . '/large_thumb/' . $imageName,
                    'original_name' => $imageName,
                    'is_title' => $index === 0,
                ]);
            }
        }

        $this->line("✓ {$ad->title}");
    }
}
