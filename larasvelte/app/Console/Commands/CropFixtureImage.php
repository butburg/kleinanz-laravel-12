<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class CropFixtureImage extends Command
{
    protected $signature = 'app:crop-fixture-image
                            {--input=tests/fixtures/test-image.jpg : Input image path relative to larasvelte/ or absolute path}
                            {--output=tests/fixtures/test-image_cropped.jpg : Output image path relative to larasvelte/ or absolute path}
                            {--model= : ONNX model path (defaults to services.onnx.model_path)}
                            {--script= : Python script path (defaults to ads.auto_crop.script_path)}
                            {--python= : Python executable (defaults to services.python.path)}
                            {--detection-threshold= : Confidence threshold between 0 and 1}
                            {--closeup-threshold= : Close-up threshold between 0 and 1}
                            {--margin-percent= : Margin percent around detected item}
                            {--timeout= : Process timeout in seconds}
                            {--matrix : Generate a parameter matrix of output images}
                            {--step=0.10 : Matrix step size for detection/closeup thresholds}
                            {--detection-min=0.10 : Matrix minimum detection threshold}
                            {--detection-max=0.90 : Matrix maximum detection threshold}
                            {--closeup-min=0.10 : Matrix minimum closeup threshold}
                            {--closeup-max=0.90 : Matrix maximum closeup threshold}';

    protected $description = 'Ad-hoc auto-crop for one fixture image with tunable model parameters';

    public function handle(): int
    {
        $scriptPath = $this->resolvePath((string) ($this->option('script') ?: config('ads.auto_crop.script_path')));
        $inputPath = $this->resolvePath((string) $this->option('input'));
        $outputPath = $this->resolvePath((string) $this->option('output'));
        $modelPath = $this->resolvePath((string) ($this->option('model') ?: config('services.onnx.model_path')));
        $pythonPath = (string) ($this->option('python') ?: config('services.python.path', 'python3'));
        $detectionThreshold = (float) ($this->option('detection-threshold') ?: config('ads.auto_crop.detection_threshold', 0.7));
        $closeupThreshold = (float) ($this->option('closeup-threshold') ?: config('ads.auto_crop.closeup_threshold', 0.80));
        $marginPercent = (int) ($this->option('margin-percent') ?: config('ads.auto_crop.margin_percent', 2));
        $timeout = (int) ($this->option('timeout') ?: config('ads.auto_crop.timeout', 60));

        if (! file_exists($scriptPath)) {
            $this->error('Auto-crop script not found: ' . $scriptPath);

            return self::FAILURE;
        }

        if (! file_exists($inputPath)) {
            $this->error('Input image not found: ' . $inputPath);

            return self::FAILURE;
        }

        if (! file_exists($modelPath)) {
            $this->error('ONNX model not found: ' . $modelPath);

            return self::FAILURE;
        }

        if (! $this->isThresholdValid($detectionThreshold)) {
            $this->error('detection-threshold must be between 0 and 1.');

            return self::FAILURE;
        }

        if (! $this->isThresholdValid($closeupThreshold)) {
            $this->error('closeup-threshold must be between 0 and 1.');

            return self::FAILURE;
        }

        if ($marginPercent < 0) {
            $this->error('margin-percent must be 0 or greater.');

            return self::FAILURE;
        }

        if ($timeout < 1) {
            $this->error('timeout must be at least 1 second.');

            return self::FAILURE;
        }

        $outputDirectory = dirname($outputPath);
        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            $this->error('Unable to create output directory: ' . $outputDirectory);

            return self::FAILURE;
        }

        $this->info('Running auto-crop fixture test with parameters:');
        $this->line('  Input: ' . $inputPath);
        $this->line('  Output: ' . $outputPath);
        $this->line('  Script: ' . $scriptPath);
        $this->line('  Model: ' . $modelPath);
        $this->line('  Python: ' . $pythonPath);
        $this->line('  Detection threshold: ' . sprintf('%.2f', $detectionThreshold));
        $this->line('  Closeup threshold: ' . sprintf('%.2f', $closeupThreshold));
        $this->line('  Margin percent: ' . $marginPercent);
        $this->line('  Timeout: ' . $timeout . 's');

        if ((bool) $this->option('matrix')) {
            return $this->runMatrix(
                scriptPath: $scriptPath,
                inputPath: $inputPath,
                outputPath: $outputPath,
                modelPath: $modelPath,
                pythonPath: $pythonPath,
                marginPercent: $marginPercent,
                timeout: $timeout,
            );
        }

        $result = $this->runCropCommand(
            scriptPath: $scriptPath,
            inputPath: $inputPath,
            outputPath: $outputPath,
            modelPath: $modelPath,
            pythonPath: $pythonPath,
            detectionThreshold: $detectionThreshold,
            closeupThreshold: $closeupThreshold,
            marginPercent: $marginPercent,
            timeout: $timeout,
        );

        if (! $result->successful()) {
            $this->error('Auto-crop process failed with exit code ' . $result->exitCode() . '.');

            $errorOutput = trim($result->errorOutput());
            if ($errorOutput !== '') {
                $this->line('stderr: ' . $errorOutput);
            }

            $output = trim($result->output());
            if ($output !== '') {
                $this->line('stdout: ' . $output);
            }

            return self::FAILURE;
        }

        $payload = json_decode($result->output(), true);
        if (! is_array($payload)) {
            $this->error('Python script did not return valid JSON.');
            $this->line('Raw output: ' . trim($result->output()));

            return self::FAILURE;
        }

        if (! ($payload['success'] ?? false)) {
            $this->error('Auto-crop returned success=false.');
            $this->line('Error: ' . ($payload['error'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        if (! file_exists($outputPath)) {
            $this->error('Expected output file was not created: ' . $outputPath);

            return self::FAILURE;
        }

        $this->info('Auto-crop command finished.');
        $this->line('  was_cropped: ' . (($payload['was_cropped'] ?? false) ? 'true' : 'false'));
        $this->line('  original_size: ' . json_encode($payload['original_size'] ?? null));
        $this->line('  cropped_size: ' . json_encode($payload['cropped_size'] ?? null));
        $this->line('  output_file: ' . $outputPath);

        return self::SUCCESS;
    }

    private function runMatrix(
        string $scriptPath,
        string $inputPath,
        string $outputPath,
        string $modelPath,
        string $pythonPath,
        int $marginPercent,
        int $timeout,
    ): int {
        $step = (float) $this->option('step');
        $detectionMin = (float) $this->option('detection-min');
        $detectionMax = (float) $this->option('detection-max');
        $closeupMin = (float) $this->option('closeup-min');
        $closeupMax = (float) $this->option('closeup-max');

        if ($step <= 0.0) {
            $this->error('step must be greater than 0.');

            return self::FAILURE;
        }

        if ($step > 1.0) {
            $this->error('step must be less than or equal to 1.');

            return self::FAILURE;
        }

        if (! $this->isThresholdValid($detectionMin) || ! $this->isThresholdValid($detectionMax) || $detectionMin > $detectionMax) {
            $this->error('Invalid detection range. Ensure 0 <= detection-min <= detection-max <= 1.');

            return self::FAILURE;
        }

        if (! $this->isThresholdValid($closeupMin) || ! $this->isThresholdValid($closeupMax) || $closeupMin > $closeupMax) {
            $this->error('Invalid closeup range. Ensure 0 <= closeup-min <= closeup-max <= 1.');

            return self::FAILURE;
        }

        $detectionValues = $this->buildStepValues($detectionMin, $detectionMax, $step);
        $closeupValues = $this->buildStepValues($closeupMin, $closeupMax, $step);

        $total = count($detectionValues) * count($closeupValues);
        $this->info('Matrix mode enabled. Generating ' . $total . ' images...');

        $success = 0;
        $failures = 0;
        $croppedCount = 0;
        $noDetectionCount = 0;
        $alreadyCloseupCount = 0;
        $reportRows = [];

        foreach ($detectionValues as $detectionValue) {
            foreach ($closeupValues as $closeupValue) {
                $matrixOutputPath = $this->buildMatrixOutputPath(
                    baseOutputPath: $outputPath,
                    detectionThreshold: $detectionValue,
                    closeupThreshold: $closeupValue,
                    marginPercent: $marginPercent,
                );

                $result = $this->runCropCommand(
                    scriptPath: $scriptPath,
                    inputPath: $inputPath,
                    outputPath: $matrixOutputPath,
                    modelPath: $modelPath,
                    pythonPath: $pythonPath,
                    detectionThreshold: $detectionValue,
                    closeupThreshold: $closeupValue,
                    marginPercent: $marginPercent,
                    timeout: $timeout,
                );

                if (! $result->successful()) {
                    $failures++;
                    $reportRows[] = [
                        'detection_threshold' => $detectionValue,
                        'closeup_threshold' => $closeupValue,
                        'margin_percent' => $marginPercent,
                        'success' => false,
                        'was_cropped' => null,
                        'decision_reason' => 'process_failed',
                        'detection_count' => null,
                        'main_confidence' => null,
                        'main_coverage' => null,
                        'output_file' => $this->toProjectRelativePath($matrixOutputPath),
                    ];
                    $this->warn(sprintf(
                        'FAILED dt=%.2f ct=%.2f -> %s',
                        $detectionValue,
                        $closeupValue,
                        $matrixOutputPath
                    ));
                    continue;
                }

                $payload = json_decode($result->output(), true);
                if (! is_array($payload) || ! ($payload['success'] ?? false) || ! file_exists($matrixOutputPath)) {
                    $failures++;
                    $reportRows[] = [
                        'detection_threshold' => $detectionValue,
                        'closeup_threshold' => $closeupValue,
                        'margin_percent' => $marginPercent,
                        'success' => false,
                        'was_cropped' => null,
                        'decision_reason' => is_array($payload) ? ($payload['decision_reason'] ?? 'invalid_payload') : 'invalid_payload',
                        'detection_count' => is_array($payload) ? ($payload['detection_count'] ?? null) : null,
                        'main_confidence' => is_array($payload) ? ($payload['main_confidence'] ?? null) : null,
                        'main_coverage' => is_array($payload) ? ($payload['main_coverage'] ?? null) : null,
                        'output_file' => $this->toProjectRelativePath($matrixOutputPath),
                    ];
                    $this->warn(sprintf(
                        'FAILED dt=%.2f ct=%.2f -> %s',
                        $detectionValue,
                        $closeupValue,
                        $matrixOutputPath
                    ));
                    continue;
                }

                $success++;
                $wasCropped = (bool) ($payload['was_cropped'] ?? false);
                $reason = (string) ($payload['decision_reason'] ?? 'unknown');

                if ($wasCropped) {
                    $croppedCount++;
                } elseif ($reason === 'no_detection') {
                    $noDetectionCount++;
                } elseif ($reason === 'already_closeup') {
                    $alreadyCloseupCount++;
                }

                $reportRows[] = [
                    'detection_threshold' => $detectionValue,
                    'closeup_threshold' => $closeupValue,
                    'margin_percent' => $marginPercent,
                    'success' => true,
                    'was_cropped' => $wasCropped,
                    'decision_reason' => $reason,
                    'detection_count' => $payload['detection_count'] ?? null,
                    'main_confidence' => $payload['main_confidence'] ?? null,
                    'main_coverage' => $payload['main_coverage'] ?? null,
                    'output_file' => $this->toProjectRelativePath($matrixOutputPath),
                ];

                $this->line(sprintf(
                    'OK dt=%.2f ct=%.2f cropped=%s reason=%s det=%s cov=%s -> %s',
                    $detectionValue,
                    $closeupValue,
                    $wasCropped ? 'yes' : 'no',
                    $reason,
                    (string) ($payload['detection_count'] ?? 'n/a'),
                    isset($payload['main_coverage']) ? sprintf('%.4f', (float) $payload['main_coverage']) : 'n/a',
                    $this->toProjectRelativePath($matrixOutputPath)
                ));
            }
        }

        $reportPath = $this->buildMatrixReportPath($outputPath);
        File::put($reportPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'input_file' => $this->toProjectRelativePath($inputPath),
            'model_file' => $this->toProjectRelativePath($modelPath),
            'step' => $step,
            'ranges' => [
                'detection_min' => $detectionMin,
                'detection_max' => $detectionMax,
                'closeup_min' => $closeupMin,
                'closeup_max' => $closeupMax,
                'margin_percent' => $marginPercent,
            ],
            'summary' => [
                'total' => $total,
                'success' => $success,
                'failed' => $failures,
                'cropped' => $croppedCount,
                'no_detection' => $noDetectionCount,
                'already_closeup' => $alreadyCloseupCount,
            ],
            'results' => $reportRows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info('Matrix generation complete.');
        $this->line('  Total: ' . $total);
        $this->line('  Success: ' . $success);
        $this->line('  Failed: ' . $failures);
        $this->line('  Cropped: ' . $croppedCount);
        $this->line('  No detection: ' . $noDetectionCount);
        $this->line('  Already closeup: ' . $alreadyCloseupCount);
        $this->line('  Report: ' . $this->toProjectRelativePath($reportPath));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runCropCommand(
        string $scriptPath,
        string $inputPath,
        string $outputPath,
        string $modelPath,
        string $pythonPath,
        float $detectionThreshold,
        float $closeupThreshold,
        int $marginPercent,
        int $timeout,
    ) {
        $command = [
            $pythonPath,
            $scriptPath,
            $inputPath,
            '--output',
            $outputPath,
            '--model',
            $modelPath,
            '--detection-threshold',
            sprintf('%.2f', $detectionThreshold),
            '--closeup-threshold',
            sprintf('%.2f', $closeupThreshold),
            '--margin-percent',
            (string) $marginPercent,
        ];

        $pythonPackagesPath = (string) config('services.python.packages_path', '');
        $environment = [];
        if ($pythonPackagesPath !== '') {
            $existingPythonPath = getenv('PYTHONPATH');
            $environment['PYTHONPATH'] = $existingPythonPath
                ? $pythonPackagesPath . PATH_SEPARATOR . $existingPythonPath
                : $pythonPackagesPath;
        }

        return Process::timeout($timeout)
            ->env($environment)
            ->run($command);
    }

    private function buildStepValues(float $min, float $max, float $step): array
    {
        $values = [];
        $current = $min;

        while ($current <= $max + 1e-9) {
            $values[] = round($current, 2);
            $current += $step;
        }

        return $values;
    }

    private function buildMatrixOutputPath(
        string $baseOutputPath,
        float $detectionThreshold,
        float $closeupThreshold,
        int $marginPercent,
    ): string {
        $extension = pathinfo($baseOutputPath, PATHINFO_EXTENSION);
        $filename = pathinfo($baseOutputPath, PATHINFO_FILENAME);
        $directory = dirname($baseOutputPath);

        $det = str_replace('.', '', sprintf('%.2f', $detectionThreshold));
        $clo = str_replace('.', '', sprintf('%.2f', $closeupThreshold));
        $mar = str_pad((string) $marginPercent, 2, '0', STR_PAD_LEFT);

        $suffix = '_dt' . $det . '_ct' . $clo . '_m' . $mar;
        $fullFilename = $filename . $suffix . ($extension !== '' ? '.' . $extension : '');

        return $directory . DIRECTORY_SEPARATOR . $fullFilename;
    }

    private function buildMatrixReportPath(string $baseOutputPath): string
    {
        $extension = pathinfo($baseOutputPath, PATHINFO_EXTENSION);
        $filename = pathinfo($baseOutputPath, PATHINFO_FILENAME);
        $directory = dirname($baseOutputPath);

        $reportFilename = $filename . '_matrix_report.json';
        if ($extension === '') {
            return $directory . DIRECTORY_SEPARATOR . $reportFilename;
        }

        return $directory . DIRECTORY_SEPARATOR . $reportFilename;
    }

    private function toProjectRelativePath(string $absolutePath): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolutePath, $base)) {
            return substr($absolutePath, strlen($base));
        }

        return $absolutePath;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    private function isThresholdValid(float $threshold): bool
    {
        return $threshold >= 0.0 && $threshold <= 1.0;
    }
}
