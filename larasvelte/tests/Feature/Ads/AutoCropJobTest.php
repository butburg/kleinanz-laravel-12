<?php

use App\Jobs\AutoCropImage;
use App\Models\Ad;
use App\Models\AdImage;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->ad = Ad::factory()->for($this->user)->create();

    // Create a test image file
    Storage::disk('public')->put('test-image.jpg', file_get_contents(base_path('tests/fixtures/test-image.jpg')));

    $this->adImage = AdImage::factory()->for($this->ad)->create([
        'large_path' => 'test-image.jpg',
        'large_thumb_path' => 'test-image-thumb.jpg',
        'cropped_path' => null,
        'cropped_thumb_path' => null,
    ]);
});

it('runs python script with correct arguments', function (): void {
    // Mock the Process facade to capture the call
    Process::fake([
        '*auto_crop.py*' => Process::result(output: json_encode([
            'success' => true,
            'was_cropped' => true,
            'original_size' => [1000, 1200],
            'cropped_size' => [850, 950],
            'error' => null,
        ])),
    ]);

    $job = new AutoCropImage($this->adImage);
    $job->handle();

    // Verify Python script was called with correct arguments
    Process::assertRan(function ($process) {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : (string) $process->command;

        return str_contains($command, config('services.python.path'))
            && str_contains($command, config('ads.auto_crop.script_path'))
            && str_contains($command, Storage::disk('public')->path($this->adImage->large_path));
    });
});

it('updates database with cropped paths', function (): void {
    // Mock successful crop response
    Process::fake([
        '*auto_crop.py*' => Process::result(output: json_encode([
            'success' => true,
            'was_cropped' => true,
            'error' => null,
        ])),
    ]);

    $expectedCroppedPath = "ads/{$this->ad->id}/cropped/test-image-cropped.jpg";
    Storage::disk('public')->put($expectedCroppedPath, file_get_contents(base_path('tests/fixtures/test-image.jpg')));

    $job = new AutoCropImage($this->adImage);
    $job->handle();

    // Refresh and verify database was updated
    $this->adImage->refresh();
    expect($this->adImage->cropped_path)->not->toBeNull();
    expect($this->adImage->cropped_thumb_path)->not->toBeNull();
});

it('logs error when image file not found', function (): void {
    // Set non-existent path
    $this->adImage->update(['large_path' => 'non-existent.jpg']);

    $job = new AutoCropImage($this->adImage);

    // Should not throw, but gracefully handle
    $job->handle();

    // Verify adImage wasn't updated
    $this->adImage->refresh();
    expect($this->adImage->cropped_path)->toBeNull();
});

it('throws when python subprocess fails so queue retry can happen', function (): void {
    // Mock Python script failure
    Process::fake([
        '*auto_crop.py*' => Process::result(
            exitCode: 1,
            output: '',
            errorOutput: 'ONNX Runtime Error: Model not found'
        ),
    ]);

    $job = new AutoCropImage($this->adImage);

    $this->expectException(RuntimeException::class);

    $job->handle();
});

it('skips crop when already cropped', function (): void {
    // Pre-set cropped paths to simulate already-cropped image
    $this->adImage->update([
        'cropped_path' => 'already-cropped.jpg',
        'cropped_thumb_path' => 'already-cropped-thumb.jpg',
    ]);
    Storage::disk('public')->put('already-cropped.jpg', file_get_contents(base_path('tests/fixtures/test-image.jpg')));
    Storage::disk('public')->put('already-cropped-thumb.jpg', file_get_contents(base_path('tests/fixtures/test-image.jpg')));

    Process::fake(); // Should not call Python

    $job = new AutoCropImage($this->adImage);
    $job->handle();

    // Verify Process was not called
    Process::assertNotRan(function ($process) {
        return str_contains($process->command, 'auto_crop.py');
    });
});

it('parses crop metadata correctly', function (): void {
    $cropMetadata = [
        'success' => true,
        'was_cropped' => true,
        'original_size' => [1000, 1200],
        'cropped_size' => [850, 950],
        'error' => null,
    ];

    Process::fake([
        '*auto_crop.py*' => Process::result(output: json_encode($cropMetadata)),
    ]);

    $expectedCroppedPath = "ads/{$this->ad->id}/cropped/test-image-cropped.jpg";
    Storage::disk('public')->put($expectedCroppedPath, file_get_contents(base_path('tests/fixtures/test-image.jpg')));

    $job = new AutoCropImage($this->adImage);
    $job->handle();

    $this->adImage->refresh();
    // Metadata should be stored in metadata column for future reference
    expect($this->adImage->metadata)->toBeArray();
    expect($this->adImage->metadata['original_size'] ?? null)->toBe([1000, 1200]);
});

it('handles empty python response', function (): void {
    // Mock empty response
    Process::fake([
        '*auto_crop.py*' => Process::result(output: ''),
    ]);

    $job = new AutoCropImage($this->adImage);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('empty output');

    $job->handle();
});

it('respects max attempts', function (): void {
    $job = new AutoCropImage($this->adImage);
    expect($job->tries)->toBe(3);
});

it('respects timeout config', function (): void {
    $job = new AutoCropImage($this->adImage);
    expect($job->timeout)->toBe(config('ads.auto_crop.timeout'));
});

it('throws on invalid json response', function (): void {
    // Mock invalid JSON response
    Process::fake([
        '*auto_crop.py*' => Process::result(output: 'invalid json {'),
    ]);

    $job = new AutoCropImage($this->adImage);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('invalid JSON');

    $job->handle();
});

it('throws when python script returns success false payload', function (): void {
    Process::fake([
        '*auto_crop.py*' => Process::result(output: json_encode([
            'success' => false,
            'error' => 'Model file not found',
        ])),
    ]);

    $job = new AutoCropImage($this->adImage);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Model file not found');

    $job->handle();
});

it('uses cloth fixtures as realistic source and expected crop assets', function (): void {
    Storage::disk('public')->put(
        'test-image.jpg',
        file_get_contents(base_path('tests/figure_with_clothe.jpg'))
    );

    Process::fake([
        '*auto_crop.py*' => Process::result(output: json_encode([
            'success' => true,
            'was_cropped' => true,
            'original_size' => [1000, 1200],
            'cropped_size' => [850, 950],
            'error' => null,
        ])),
    ]);

    $expectedCroppedPath = "ads/{$this->ad->id}/cropped/test-image-cropped.jpg";
    Storage::disk('public')->put(
        $expectedCroppedPath,
        file_get_contents(base_path('tests/figure_with_clothe.cropped.jpg'))
    );

    $job = new AutoCropImage($this->adImage->fresh());
    $job->handle();

    $this->adImage->refresh();
    expect($this->adImage->cropped_path)->toBe($expectedCroppedPath);
    expect(Storage::disk('public')->exists($this->adImage->cropped_thumb_path))->toBeTrue();
});
