<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AdImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class AutoCropImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public ?int $timeout = null;

    public function __construct(
        public AdImage $adImage
    ) {
        // Set timeout from config
        $this->timeout = config('ads.auto_crop.timeout', 60);
    }

    public function handle(): void
    {
        // Skip only when cropped paths are present and both files exist on disk.
        if ($this->hasUsableCroppedFiles()) {
            Log::info('AutoCropImage: Image already cropped', [
                'ad_image_id' => $this->adImage->id,
                'cropped_path' => $this->adImage->cropped_path,
            ]);

            return;
        }

        // Validate source image exists
        $imagePath = Storage::disk('public')->path($this->adImage->large_path);
        if (! file_exists($imagePath)) {
            Log::warning('AutoCropImage: Source image file not found', [
                'ad_image_id' => $this->adImage->id,
                'large_path' => $this->adImage->large_path,
                'full_path' => $imagePath,
            ]);

            return;
        }

        try {
            $croppedImage = $this->runAutoCrop($imagePath);

            if ($croppedImage) {
                $this->storeAndUpdate($croppedImage);
            }
        } catch (\Exception $e) {
            Log::error('AutoCropImage: Exception during processing', [
                'ad_image_id' => $this->adImage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Increment failed_attempts count, will retry based on tries configuration
            throw $e;
        }
    }

    private function hasUsableCroppedFiles(): bool
    {
        if (! $this->adImage->cropped_path || ! $this->adImage->cropped_thumb_path) {
            return false;
        }

        return Storage::disk('public')->exists($this->adImage->cropped_path)
            && Storage::disk('public')->exists($this->adImage->cropped_thumb_path);
    }

    /**
     * Run the auto-crop Python script and return the result.
     *
     * @return array|null Array with cropped result or null if script failed
     */
    private function runAutoCrop(string $imagePath): ?array
    {
        $pythonPath = (string) config('services.python.path', 'python3');
        $pythonPackagesPath = (string) config('services.python.packages_path', '');
        $scriptPath = config('ads.auto_crop.script_path');
        $modelPath = config('services.onnx.model_path', storage_path('models/yolov8n-fashionpedia-1.onnx'));
        $detectionThreshold = config('ads.auto_crop.detection_threshold', 0.7);
        $closeupThreshold = config('ads.auto_crop.closeup_threshold', 0.70);
        $marginPercent = config('ads.auto_crop.margin_percent', 2);

        $outputImagePath = Storage::disk('public')->path($this->generateCroppedFilename());

        $command = [
            $pythonPath,
            $scriptPath,
            $imagePath,
            '--output',
            $outputImagePath,
            '--model',
            $modelPath,
            '--detection-threshold',
            sprintf('%.2f', $detectionThreshold),
            '--closeup-threshold',
            sprintf('%.2f', $closeupThreshold),
            '--margin-percent',
            (string) $marginPercent,
        ];

        $environment = [];
        if ($pythonPackagesPath !== '') {
            $existingPythonPath = getenv('PYTHONPATH');
            $environment['PYTHONPATH'] = $existingPythonPath
                ? $pythonPackagesPath.PATH_SEPARATOR.$existingPythonPath
                : $pythonPackagesPath;
        }

        try {
            $result = Process::timeout($this->timeout)
                ->env($environment)
                ->run($command);

            if (! $result->successful()) {
                Log::error('AutoCropImage: Python script failed', [
                    'ad_image_id' => $this->adImage->id,
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                ]);

                throw new \RuntimeException('Auto-crop subprocess failed with exit code ' . $result->exitCode());
            }

            $output = $result->output();
            if (empty(trim($output))) {
                Log::warning('AutoCropImage: Empty response from Python script', [
                    'ad_image_id' => $this->adImage->id,
                ]);

                return null;
            }

            $response = json_decode($output, true);
            if (! is_array($response)) {
                Log::warning('AutoCropImage: Invalid JSON response from Python script', [
                    'ad_image_id' => $this->adImage->id,
                    'output' => $output,
                ]);

                return null;
            }

            // Check for errors in the response
            if (! ($response['success'] ?? false)) {
                Log::warning('AutoCropImage: Python script returned success=false', [
                    'ad_image_id' => $this->adImage->id,
                    'error' => $response['error'] ?? 'Unknown error',
                ]);

                return null;
            }

            // Only return if image was actually cropped
            if (! ($response['was_cropped'] ?? false)) {
                Log::info('AutoCropImage: Python script determined no cropping needed', [
                    'ad_image_id' => $this->adImage->id,
                ]);

                return null;
            }

            // Guard against false positives: a script can report success but fail to write the output file.
            if (! file_exists($outputImagePath) || filesize($outputImagePath) === 0) {
                Log::warning('AutoCropImage: Script reported success but cropped file is missing/empty', [
                    'ad_image_id' => $this->adImage->id,
                    'output_image_path' => $outputImagePath,
                ]);

                return null;
            }

            Log::info('AutoCropImage: Crop successful', [
                'ad_image_id' => $this->adImage->id,
                'was_cropped' => $response['was_cropped'],
                'original_size' => $response['original_size'] ?? null,
                'cropped_size' => $response['cropped_size'] ?? null,
            ]);

            return [
                'cropped_path' => $this->generateCroppedFilename(),
                'cropped_thumb_path' => $this->generateCroppedThumbFilename(),
                'metadata' => [
                    'original_size' => $response['original_size'] ?? null,
                    'cropped_size' => $response['cropped_size'] ?? null,
                    'cropped_at' => now()->toIso8601String(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('AutoCropImage: Process execution error', [
                'ad_image_id' => $this->adImage->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Store cropped image and update database record.
     */
    private function storeAndUpdate(array $croppedImage): void
    {
        $croppedFilePath = $croppedImage['cropped_path'];
        $croppedThumbPath = $croppedImage['cropped_thumb_path'];

        $this->createCroppedThumbnail($croppedFilePath, $croppedThumbPath);

        $this->adImage->update([
            'cropped_path' => $croppedFilePath,
            'cropped_thumb_path' => $croppedThumbPath,
            'metadata' => array_merge(
                $this->adImage->metadata ?? [],
                $croppedImage['metadata']
            ),
        ]);

        Log::info('AutoCropImage: Database updated', [
            'ad_image_id' => $this->adImage->id,
            'cropped_path' => $croppedFilePath,
        ]);
    }

    private function createCroppedThumbnail(string $croppedPath, string $thumbPath): void
    {
        try {
            $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
            $manager = new ImageManager($driver);

            $image = $manager->read(Storage::disk('public')->path($croppedPath));
            $thumbnail = $image->scaleDown(
                config('ads.image.thumbnail_width'),
                config('ads.image.thumbnail_max_height')
            );

            Storage::disk('public')->put($thumbPath, $thumbnail->toJpeg(quality: 75, progressive: true));
        } catch (\Throwable $e) {
            Log::warning('AutoCropImage: Failed to generate cropped thumbnail, falling back to cropped image', [
                'ad_image_id' => $this->adImage->id,
                'error' => $e->getMessage(),
            ]);

            Storage::disk('public')->copy($croppedPath, $thumbPath);
        }
    }

    /**
     * Generate filename for cropped image.
     */
    private function generateCroppedFilename(): string
    {
        $basename = pathinfo($this->adImage->large_path, PATHINFO_FILENAME);

        return "ads/{$this->adImage->ad_id}/cropped/{$basename}-cropped.jpg";
    }

    /**
     * Generate filename for cropped thumbnail.
     */
    private function generateCroppedThumbFilename(): string
    {
        $basename = pathinfo($this->adImage->large_path, PATHINFO_FILENAME);

        return "ads/{$this->adImage->ad_id}/cropped/{$basename}-cropped-thumb.jpg";
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(\Throwable $e): bool
    {
        return $this->attempts() < $this->tries;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('AutoCropImage: Job failed after all retries', [
            'ad_image_id' => $this->adImage->id,
            'attempts' => $this->attempts(),
            'error' => $e->getMessage(),
        ]);
    }
}
