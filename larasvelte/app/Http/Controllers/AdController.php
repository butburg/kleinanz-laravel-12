<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateAdRequest;
use App\Http\Requests\PartialUpdateAdRequest;
use App\Http\Requests\StoreAdImageRequest;
use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdStatusRequest;
use App\Jobs\AutoCropImage;
use App\Models\Ad;
use App\Models\AdImage;
use App\Services\TextGenerationException;
use App\Services\TextGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdController extends Controller
{
    private function statusColor(string $status): string
    {
        return match ($status) {
            'Online' => 'green',
            'Archived' => 'zinc',
            'Sold' => 'black',
            default => 'amber',
        };
    }

    private function expiryDetails(Ad $ad): array
    {
        if ($ad->status !== 'Online' || $ad->last_online_at === null) {
            return [
                'expiry_at' => null,
                'days_to_expiry' => null,
                'is_expired' => false,
            ];
        }

        $expiryAt = $ad->last_online_at->copy()->addDays(60)->startOfDay();
        $daysToExpiry = now()->startOfDay()->diffInDays($expiryAt, false);

        return [
            'expiry_at' => $expiryAt->toDateString(),
            'days_to_expiry' => $daysToExpiry,
            'is_expired' => $daysToExpiry < 0,
        ];
    }

    private function listThumbnailUrl(Ad $ad): ?string
    {
        $titleImage = $ad->images->firstWhere('is_title', true) ?? $ad->images->first();
        if (! $titleImage) {
            return null;
        }

        $canUseCroppedThumb = $titleImage->use_cropped
            && $titleImage->cropped_thumb_path
            && Storage::disk('public')->exists($titleImage->cropped_thumb_path);
        $thumbPath = $canUseCroppedThumb ? $titleImage->cropped_thumb_path : $titleImage->large_thumb_path;

        return $thumbPath ? $this->encodeStorageUrl($thumbPath) : null;
    }

    private function listImageDownloadPayload(Ad $ad, AdImage $image): array
    {
        return [
            'id' => $image->id,
            'original_name' => $image->original_name,
            'download_url' => route('ads.images.download', [$ad, $image], absolute: false),
        ];
    }

    /**
     * @return array{large_path: string, large_thumb_path: string, cropped_path: null, cropped_thumb_path: null}
     */
    private function storeImageVariants(Ad $ad, UploadedFile $file): array
    {
        $largePath = $file->store("ads/{$ad->id}/large", 'public');
        $largeThumbPath = "ads/{$ad->id}/large_thumb/".basename($largePath);

        // Create proper thumbnail instead of copying full image
        // Use Imagick if available (supports more formats like AVIF), otherwise GD
        try {
            $driver = extension_loaded('imagick') ? new ImagickDriver : new GdDriver;
            $manager = new ImageManager($driver);
            $fullImagePath = Storage::disk('public')->path($largePath);
            $image = $manager->read($fullImagePath);

            // Resize to thumbnail dimensions
            $thumbnail = $image->scaleDown(
                config('ads.image.thumbnail_width'),
                config('ads.image.thumbnail_max_height')
            );

            Storage::disk('public')->put(
                $largeThumbPath,
                $thumbnail->toJpeg(quality: 75, progressive: true)
            );
        } catch (\Intervention\Image\Exceptions\DecoderException $e) {
            // If image can't be decoded (e.g., unsupported format), just copy the file
            Storage::disk('public')->copy($largePath, $largeThumbPath);
        }

        return [
            'large_path' => $largePath,
            'large_thumb_path' => $largeThumbPath,
            'cropped_path' => null,
            'cropped_thumb_path' => null,
        ];
    }

    private function imagePayload(AdImage $image): array
    {
        $croppedThumbExists = $image->cropped_thumb_path
            && Storage::disk('public')->exists($image->cropped_thumb_path);
        $croppedExists = $image->cropped_path
            && Storage::disk('public')->exists($image->cropped_path);
        $usingCropped = $image->use_cropped && $croppedExists;
        $thumbPath = ($image->use_cropped && $croppedThumbExists) ? $image->cropped_thumb_path : $image->large_thumb_path;
        $metadata = is_array($image->metadata) ? $image->metadata : [];
        $cropStatus = $metadata['crop_status'] ?? null;
        $cropRequestedAt = $metadata['crop_requested_at'] ?? null;
        $cropStartedAt = $metadata['crop_started_at'] ?? null;
        $cropPendingSeconds = $this->cropPendingSeconds($cropRequestedAt, $cropStartedAt);
        $queueStaleAfter = (int) config('ads.auto_crop.queue_stale_after_seconds', 90);
        $queueStuckAfter = (int) config('ads.auto_crop.queue_stuck_after_seconds', 300);
        $isQueueState = in_array($cropStatus, ['queued', 'processing'], true);
        $isQueueStale = $isQueueState && $cropPendingSeconds !== null && $cropPendingSeconds >= $queueStaleAfter;
        $isQueueStuck = $isQueueState && $cropPendingSeconds !== null && $cropPendingSeconds >= $queueStuckAfter;

        return [
            'id' => $image->id,
            'original_name' => $image->original_name,
            'url' => $this->encodeStorageUrl($thumbPath),
            'variants' => [
                'large' => $this->encodeStorageUrl($image->large_path),
                'large_thumb' => $this->encodeStorageUrl($image->large_thumb_path),
                'cropped' => $croppedExists ? $this->encodeStorageUrl($image->cropped_path) : null,
                'cropped_thumb' => $croppedThumbExists ? $this->encodeStorageUrl($image->cropped_thumb_path) : null,
            ],
            'is_title' => $image->is_title,
            'is_cropped' => $croppedExists,
            'use_cropped' => $usingCropped,
            'crop_metadata' => $image->metadata ? [
                'original_size' => $metadata['original_size'] ?? null,
                'cropped_size' => $metadata['cropped_size'] ?? null,
                'cropped_at' => $metadata['cropped_at'] ?? null,
                'crop_status' => $cropStatus,
                'crop_requested_at' => $cropRequestedAt,
                'crop_started_at' => $cropStartedAt,
                'crop_error' => $metadata['crop_error'] ?? null,
                'crop_pending_seconds' => $cropPendingSeconds,
                'is_queue_stale' => $isQueueStale,
                'is_queue_stuck' => $isQueueStuck,
            ] : null,
        ];
    }

    private function cropPendingSeconds(mixed $cropRequestedAt, mixed $cropStartedAt): ?int
    {
        $reference = is_string($cropStartedAt) && $cropStartedAt !== ''
            ? strtotime($cropStartedAt)
            : (is_string($cropRequestedAt) && $cropRequestedAt !== '' ? strtotime($cropRequestedAt) : false);

        if ($reference === false) {
            return null;
        }

        return max(0, now()->timestamp - $reference);
    }

    /**
     * Generate a properly encoded storage URL that handles special characters like + in paths
     * By encoding each path segment separately, we preserve the directory structure
     */
    private function encodeStorageUrl(string $path): string
    {
        $baseUrl = Storage::disk('public')->url('');

        // Split path by / and encode each segment separately
        $segments = explode('/', $path);
        $encodedSegments = array_map('rawurlencode', $segments);
        $encodedPath = implode('/', $encodedSegments);

        return $baseUrl.$encodedPath;
    }

    private function formOptions(Request $request): array
    {
        return [
            'conditions' => config('ads.validation.conditions'),
            'shipping' => config('ads.validation.shipping_options'),
            'statuses' => config('ads.status.options'),
            'limits' => [
                'title' => config('ads.validation.title_max_length'),
                'description' => config('ads.validation.description_max_length'),
                'images' => config('ads.image.max_files'),
                'prompt' => config('ads.validation.prompt_max_length'),
            ],
            'image' => [
                'client' => [
                    'max_dimension' => config('ads.image.client.max_dimension'),
                    'quality' => config('ads.image.client.quality'),
                    'output_mime' => config('ads.image.client.output_mime'),
                ],
            ],
            'platforms' => $request->user()
                ->appendices()
                ->orderBy('platform')
                ->pluck('platform')
                ->all(),
            'default_platform' => $request->user()
                ->ads()
                ->whereNotNull('platform')
                ->latest()
                ->value('platform'),
        ];
    }

    private function appendPlatformAppendix(string $description, ?string $platform, Request $request): string
    {
        if ($platform === null) {
            return trim($description);
        }

        $appendix = $request->user()
            ->appendices()
            ->where('platform', $platform)
            ->value('content');

        if (! is_string($appendix) || trim($appendix) === '') {
            return trim($description);
        }

        return trim($description)."\n\n".trim($appendix);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function appendImages(Ad $ad, array $files, ?int $titleImageIndex = null, bool $autoCropEnabled = true): void
    {
        if ($files === []) {
            return;
        }

        $createdImageIds = [];

        foreach ($files as $file) {
            $variants = $this->storeImageVariants($ad, $file);

            $createdImage = $ad->images()->create([
                ...$variants,
                'original_name' => $file->getClientOriginalName(),
                'use_cropped' => $autoCropEnabled,
                'is_title' => false,
            ]);

            if ($autoCropEnabled && config('ads.auto_crop.enabled', true)) {
                $createdImage->update([
                    'metadata' => array_merge($createdImage->metadata ?? [], [
                        'crop_requested_at' => now()->toIso8601String(),
                        'crop_started_at' => null,
                        'crop_error' => null,
                    ]),
                ]);

                AutoCropImage::dispatchSync($createdImage->fresh() ?? $createdImage);
            }

            $createdImageIds[] = $createdImage->id;
        }

        $hasTitle = $ad->images()->where('is_title', true)->exists();

        if (! $hasTitle && $titleImageIndex !== null && array_key_exists($titleImageIndex, $createdImageIds)) {
            $selectedId = $createdImageIds[$titleImageIndex];
            $ad->images()->whereKey($selectedId)->update(['is_title' => true]);

            return;
        }

        if (! $hasTitle) {
            $newTitleImageId = $ad->images()->oldest()->value('id');
            if ($newTitleImageId !== null) {
                $ad->images()->where('is_title', true)->update(['is_title' => false]);
                $ad->images()->whereKey($newTitleImageId)->update(['is_title' => true]);
            }
        }

    }

    private function ensureImageBelongsToAd(Ad $ad, AdImage $adImage): void
    {
        abort_if($adImage->ad_id !== $ad->id, 404);
    }

    public function index(Request $request): Response
    {
        $statusFilter = $request->query('status');
        $statusFilter = is_string($statusFilter) && in_array($statusFilter, config('ads.status.options'), true)
            ? $statusFilter
            : null;

        $perPage = match ($request->query('per_page')) {
            '20' => 20,
            '50' => 50,
            '100' => 100,
            'all' => max(1, $request->user()->ads()->count()),
            default => 10,
        };

        $ads = Ad::query()
            ->whereBelongsTo($request->user())
            ->when($statusFilter, fn ($query) => $query->where('status', $statusFilter))
            ->with(['images:id,ad_id,original_name,large_thumb_path,cropped_thumb_path,use_cropped,is_title'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Ad $ad): array => [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'status' => $ad->status,
                'status_color' => $this->statusColor($ad->status),
                'price' => $ad->price,
                ...$this->expiryDetails($ad),
                'thumbnail_url' => $this->listThumbnailUrl($ad),
                'images' => $ad->images
                    ->map(fn (AdImage $image): array => $this->listImageDownloadPayload($ad, $image))
                    ->values()
                    ->all(),
            ]);

        $user = $request->user();
        $hasUserApiKey = ! empty($user->openai_api_key);

        return Inertia::render('ads/Index', [
            'ads' => $ads,
            'perPage' => $request->query('per_page') === 'all' ? 'all' : (string) $perPage,
            'statusFilter' => $statusFilter,
            'statusOptions' => config('ads.status.options'),
            'options' => $this->formOptions($request),
            'aiStatus' => [
                'use_test_mode' => $user->use_test_mode,
                'has_user_api_key' => $hasUserApiKey,
            ],
        ]);
    }

    public function store(StoreAdRequest $request): RedirectResponse
    {
        if ($request->boolean('_generate')) {
            return $this->storeAndGenerate($request);
        }

        $titleImageIndex = $request->validated('title_image_index');
        $autoCropEnabled = $request->boolean('auto_crop_enabled', true);

        $ad = DB::transaction(function () use ($request, $titleImageIndex, $autoCropEnabled) {
            $payload = [
                ...$request->safe()->except(['images', 'auto_crop_enabled']),
                'status' => $request->validated('status') ?? config('ads.status.default'),
            ];

            if ($payload['status'] === 'Online') {
                $payload['last_online_at'] = now();
            }

            $ad = $request->user()->ads()->create($payload);

            /** @var list<UploadedFile> $files */
            $files = $request->file('images', []);
            $this->appendImages($ad, $files, $titleImageIndex, $autoCropEnabled);

            return $ad;
        });

        return to_route('ads.index')->with('success', 'Ad created successfully.');
    }

    private function storeAndGenerate(StoreAdRequest $request): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('images', []);

        if ($files === []) {
            return back()->withErrors(['images' => 'Please upload at least one image.']);
        }

        $status = $request->validated('status') ?? config('ads.status.default');
        $conditions = (array) config('ads.validation.conditions');
        $shippingOptions = (array) config('ads.validation.shipping_options');

        $titleImageIndex = $request->validated('title_image_index');
        $autoCropEnabled = $request->boolean('auto_crop_enabled', true);

        $ad = DB::transaction(function () use ($request, $files, $status, $conditions, $shippingOptions, $titleImageIndex, $autoCropEnabled) {
            $payload = [
                'title' => 'Generating...',
                'description' => 'Generating ad content...',
                'price' => 0,
                'condition' => (string) ($conditions[0] ?? 'Gut'),
                'shipping' => (string) ($shippingOptions[0] ?? 'klein'),
                'status' => $status,
                'prompt_text' => $request->validated('prompt_text'),
                'platform' => $request->validated('platform'),
            ];

            if ($payload['status'] === 'Online') {
                $payload['last_online_at'] = now();
            }

            $ad = $request->user()->ads()->create($payload);
            $this->appendImages($ad, $files, $titleImageIndex, $autoCropEnabled);

            return $ad;
        });

        try {
            /** @var TextGenerationService $generator */
            $generator = app(TextGenerationService::class);
            $generated = $generator->generateForAd(
                $ad,
                $request->user(),
                $request->validated('prompt_text')
            );
        } catch (TextGenerationException $exception) {
            $ad->delete();

            return back()
                ->withErrors(['generate' => $exception->getMessage()])
                ->with('error', 'Text generation failed. Ad was not saved.');
        }

        $ad->update([
            'title' => $generated['title'],
            'description' => $this->appendPlatformAppendix(
                $generated['description'],
                $ad->platform,
                $request
            ),
            'price' => $generated['price'],
            'condition' => $generated['condition'],
            'shipping' => $generated['shipping'],
            'prompt_text' => $request->validated('prompt_text'),
        ]);

        return to_route('ads.index')->with('success', 'Ad generated and saved successfully.');
    }

    public function edit(Request $request, Ad $ad): Response
    {
        $this->authorize('update', $ad);

        $ads = $request->user()->ads();

        $previousAdId = (clone $ads)
            ->where(function ($query) use ($ad): void {
                $query
                    ->where('created_at', '>', $ad->created_at)
                    ->orWhere(function ($query) use ($ad): void {
                        $query
                            ->where('created_at', $ad->created_at)
                            ->where('id', '>', $ad->id);
                    });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        $nextAdId = (clone $ads)
            ->where(function ($query) use ($ad): void {
                $query
                    ->where('created_at', '<', $ad->created_at)
                    ->orWhere(function ($query) use ($ad): void {
                        $query
                            ->where('created_at', $ad->created_at)
                            ->where('id', '<', $ad->id);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        return Inertia::render('ads/Edit', [
            'ad' => [
                ...$ad->toArray(),
                'images' => $ad->images()
                    ->get()
                    ->map(fn (AdImage $image): array => $this->imagePayload($image))
                    ->values()
                    ->all(),
            ],
            'navigation' => [
                'previousAdId' => $previousAdId,
                'nextAdId' => $nextAdId,
            ],
            'options' => $this->formOptions($request),
        ]);
    }

    /**
     * Update the specified ad. Supports partial updates (auto-save) and full updates.
     */
    public function update(PartialUpdateAdRequest $request, Ad $ad): RedirectResponse
    {
        $this->authorize('update', $ad);
        $payload = $request->validated();

        if (($payload['status'] ?? null) === 'Online' && $ad->status !== 'Online') {
            $payload['last_online_at'] = now();
        }

        $ad->update($payload);

        // Always return back to maintain edit state (Inertia will merge the updated data)
        return back()->with([
            'success' => 'Ad updated successfully.',
        ]);
    }

    public function generate(GenerateAdRequest $request, Ad $ad, TextGenerationService $generator): RedirectResponse
    {
        $this->authorize('update', $ad);

        try {
            $promptText = $request->validated('prompt_text');
            $generated = $generator->generateForAd($ad, $request->user(), $promptText);
        } catch (TextGenerationException $exception) {
            return back()
                ->withErrors(['generate' => $exception->getMessage()])
                ->with('error', 'Text generation failed.');
        }

        $ad->update([
            'title' => $generated['title'],
            'description' => $this->appendPlatformAppendix(
                $generated['description'],
                $ad->platform,
                $request
            ),
            'price' => $generated['price'],
            'condition' => $generated['condition'],
            'shipping' => $generated['shipping'],
            'prompt_text' => $promptText,
        ]);

        return to_route('ads.edit', $ad)->with('success', 'Text generated successfully.');
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        $this->authorize('delete', $ad);
        $ad->delete();

        return to_route('ads.index')->with('success', 'Ad deleted successfully.');
    }

    public function storeImage(StoreAdImageRequest $request, Ad $ad): RedirectResponse
    {
        $this->authorize('update', $ad);

        $existingCount = $ad->images()->count();
        /** @var list<UploadedFile> $files */
        $files = $request->file('images', []);

        if (($existingCount + count($files)) > config('ads.image.max_files')) {
            return back()->withErrors([
                'images' => 'Maximum image count exceeded.',
            ])->with('error', 'Maximum image count exceeded.');
        }

        $this->appendImages($ad, $files);

        return to_route('ads.edit', $ad)->with('success', 'Images uploaded successfully.');
    }

    public function imageStatus(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        return response()->json([
            'images' => $ad->images()
                ->get()
                ->map(fn (AdImage $image): array => $this->imagePayload($image))
                ->values()
                ->all(),
        ]);
    }

    public function updateStatus(UpdateAdStatusRequest $request, Ad $ad): RedirectResponse
    {
        $this->authorize('update', $ad);
        $status = $request->validated('status');
        $payload = ['status' => $status];

        if ($status === 'Online' && $ad->status !== 'Online') {
            $payload['last_online_at'] = now();
        }

        $ad->update($payload);

        return to_route('ads.index')->with('success', 'Ad status updated successfully.');
    }

    public function setTitleImage(Ad $ad, AdImage $adImage): RedirectResponse
    {
        $this->authorize('update', $ad);
        $this->ensureImageBelongsToAd($ad, $adImage);

        $ad->images()->where('is_title', true)->update(['is_title' => false]);
        $adImage->update(['is_title' => true]);

        return to_route('ads.edit', $ad)->with('success', 'Title image updated successfully.');
    }

    public function updateImageCropPreference(Request $request, Ad $ad, AdImage $adImage): RedirectResponse
    {
        $this->authorize('update', $ad);
        $this->ensureImageBelongsToAd($ad, $adImage);

        $validated = $request->validate([
            'use_cropped' => ['required', 'boolean'],
        ]);

        $useCropped = (bool) $validated['use_cropped'];

        if ($useCropped) {
            $croppedAvailable = $this->croppedVariantExists($adImage);

            if (! $croppedAvailable) {
                return to_route('ads.edit', $ad)->with('error', 'Cropped version is not available yet for this image.');
            }
        }

        $adImage->update([
            'use_cropped' => $useCropped,
        ]);

        return to_route('ads.edit', $ad)->with('success', 'Image display preference updated.');
    }

    public function toggleImageCrop(Ad $ad, AdImage $adImage): RedirectResponse
    {
        $this->authorize('update', $ad);
        $this->ensureImageBelongsToAd($ad, $adImage);

        if ($this->croppedVariantExists($adImage)) {
            $useCropped = ! $adImage->use_cropped;
            $adImage->update(['use_cropped' => $useCropped]);

            return to_route('ads.edit', $ad)->with(
                'success',
                $useCropped ? 'Cropped image activated.' : 'Original image restored.'
            );
        }

        // Manual crop runs with threshold 0.0 and closeup threshold 1.0 so it crops whenever a detection is found.
        $adImage->update([
            'use_cropped' => true,
            'metadata' => array_merge($adImage->metadata ?? [], [
                'crop_requested_at' => now()->toIso8601String(),
                'crop_started_at' => null,
                'crop_error' => null,
            ]),
        ]);

        AutoCropImage::dispatchSync($adImage->fresh() ?? $adImage, 0.0, 1.0);

        return to_route('ads.edit', $ad)->with('success', 'Cropping finished.');
    }

    private function croppedVariantExists(AdImage $adImage): bool
    {
        return $adImage->cropped_path
            && Storage::disk('public')->exists($adImage->cropped_path)
            && $adImage->cropped_thumb_path
            && Storage::disk('public')->exists($adImage->cropped_thumb_path);
    }

    public function destroyImage(Ad $ad, AdImage $adImage): RedirectResponse
    {
        $this->authorize('update', $ad);
        $this->ensureImageBelongsToAd($ad, $adImage);

        $deletedWasTitle = $adImage->is_title;
        Storage::disk('public')->delete(array_filter([
            $adImage->large_path,
            $adImage->large_thumb_path,
            $adImage->cropped_path,
            $adImage->cropped_thumb_path,
        ]));
        $adImage->delete();

        if ($deletedWasTitle) {
            $nextImageId = $ad->images()->orderBy('created_at')->value('id');
            if ($nextImageId !== null) {
                $ad->images()->whereKey($nextImageId)->update(['is_title' => true]);
            }
        }

        return to_route('ads.edit', $ad)->with('success', 'Image deleted successfully.');
    }

    public function downloadImage(Ad $ad, AdImage $adImage): StreamedResponse
    {
        $this->authorize('update', $ad);
        $this->ensureImageBelongsToAd($ad, $adImage);

        $useCropped = $adImage->use_cropped
            && $adImage->cropped_path
            && Storage::disk('public')->exists($adImage->cropped_path);

        $downloadPath = $useCropped ? $adImage->cropped_path : $adImage->large_path;
        abort_if($downloadPath === null || ! Storage::disk('public')->exists($downloadPath), 404);

        return Storage::disk('public')->download($downloadPath, $adImage->original_name);
    }

    public function downloadAllImages(Ad $ad): BinaryFileResponse
    {
        $this->authorize('update', $ad);

        $images = $ad->images()->oldest()->get();
        abort_if($images->isEmpty(), 404);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'ad-images-');
        abort_if($temporaryPath === false, 500);

        $archive = new ZipArchive;
        abort_unless($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        foreach ($images as $index => $image) {
            $useCropped = $image->use_cropped
                && $image->cropped_path
                && Storage::disk('public')->exists($image->cropped_path);
            $downloadPath = $useCropped ? $image->cropped_path : $image->large_path;

            if ($downloadPath && Storage::disk('public')->exists($downloadPath)) {
                $archive->addFromString(
                    sprintf('%02d-%s', $index + 1, $image->original_name),
                    Storage::disk('public')->get($downloadPath)
                );
            }
        }

        $archive->close();

        return response()->download(
            $temporaryPath,
            (Str::slug($ad->title) ?: 'ad-images').'.zip',
        )->deleteFileAfterSend();
    }
}
