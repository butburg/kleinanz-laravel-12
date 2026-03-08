<?php

namespace App\Console\Commands;

use App\Jobs\AutoCropImage;
use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestAutoCrop extends Command
{
    protected $signature = 'app:test-auto-crop {ad-id? : Ad ID to test, or create new if not provided}';

    protected $description = 'Test auto-crop job on an image';

    public function handle(): int
    {
        $this->info('🔍 Auto-Crop Test Tool');
        $this->info('======================');
        $this->newLine();

        // Get or create ad
        $adId = $this->argument('ad-id');
        if ($adId) {
            $ad = Ad::find($adId);
            if (!$ad) {
                $this->error("Ad with ID {$adId} not found.");
                return self::FAILURE;
            }
        } else {
            $user = User::first();
            if (!$user) {
                $this->error('No users found. Create a user first.');
                return self::FAILURE;
            }
            $ad = Ad::factory()->for($user)->create();
            $this->info("Created test Ad: {$ad->id}");
        }

        // Get or create an image
        $image = $ad->images->first();
        if (!$image) {
            $this->error("No images found for Ad {$ad->id}. Upload an image first.");
            return self::FAILURE;
        }

        $this->info("Testing image: {$image->original_name}");
        $this->line("Large path: {$image->large_path}");
        $this->line("Large thumb path: {$image->large_thumb_path}");
        $this->newLine();

        // Check if file exists
        if (!Storage::disk('public')->exists($image->large_path)) {
            $this->error("❌ Image file not found: {$image->large_path}");
            return self::FAILURE;
        }
        $this->info("✓ Image file exists");

        // Check if already cropped
        if ($image->cropped_path) {
            $this->warn("⚠️  Image already has cropped version:");
            $this->line("   Cropped: {$image->cropped_path}");
            $this->line("   Cropped thumb: {$image->cropped_thumb_path}");
            $this->newLine();
        }

        // Check config
        $this->info('📋 Auto-Crop Configuration:');
        $this->line('  Enabled: ' . (config('ads.auto_crop.enabled') ? 'Yes' : 'No'));
        $this->line('  Script: ' . config('ads.auto_crop.script_path'));
        $this->line('  Model: ' . config('services.onnx.model_path'));
        $this->line('  Detection Threshold: ' . config('ads.auto_crop.detection_threshold'));
        $this->line('  Closeup Threshold: ' . config('ads.auto_crop.closeup_threshold'));
        $this->newLine();

        // Check if script exists
        $scriptPath = config('ads.auto_crop.script_path');
        if (!file_exists($scriptPath)) {
            $this->error("❌ Python script not found: {$scriptPath}");
            return self::FAILURE;
        }
        $this->info("✓ Python script exists");

        // Check if model exists
        $modelPath = config('services.onnx.model_path');
        if (!file_exists($modelPath)) {
            $this->error("❌ ONNX model not found: {$modelPath}");
            return self::FAILURE;
        }
        $this->info("✓ ONNX model exists");
        $this->newLine();

        // Run auto-crop
        $this->info('🚀 Running auto-crop job...');
        $bar = $this->output->createProgressBar(1);
        $bar->start();

        try {
            $job = new AutoCropImage($image);
            $job->handle();
            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error("❌ Job failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Check results
        $this->newLine();
        $image->refresh();

        if ($image->cropped_path) {
            $this->info('✓ Crop successful!');
            $this->line("  Cropped: {$image->cropped_path}");
            $this->line("  Cropped thumb: {$image->cropped_thumb_path}");

            if ($image->metadata) {
                $this->info('📊 Crop Metadata:');
                $metadata = $image->metadata;
                if (isset($metadata['original_size'])) {
                    $orig = $metadata['original_size'];
                    $this->line("  Original: {$orig[0]}x{$orig[1]}");
                }
                if (isset($metadata['cropped_size'])) {
                    $crop = $metadata['cropped_size'];
                    $this->line("  Cropped: {$crop[0]}x{$crop[1]}");
                }
                if (isset($metadata['cropped_at'])) {
                    $this->line("  Cropped at: {$metadata['cropped_at']}");
                }
            }
        } else {
            $this->warn('⚠️  Image was not cropped (no items detected or closeup threshold not met)');
            if ($image->metadata) {
                $this->info('📊 Metadata:');
                $this->line(json_encode($image->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        $this->newLine();
        $this->info('✓ Test complete');
        return self::SUCCESS;
    }
}
