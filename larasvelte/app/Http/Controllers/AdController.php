<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdImageRequest;
use App\Http\Requests\StoreAdRequest;
use App\Http\Requests\UpdateAdRequest;
use App\Http\Requests\UpdateAdStatusRequest;
use App\Http\Requests\GenerateAdRequest;
use App\Models\Ad;
use App\Models\AdImage;
use App\Services\TextGenerationException;
use App\Services\TextGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdController extends Controller
{
    private function statusColor(string $status): string
    {
        return match ($status) {
            'Online' => 'green',
            'Archiviert' => 'zinc',
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
        $thumbPath = $titleImage?->cropped_thumb_path ?? $titleImage?->large_thumb_path;

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
        $largeThumbPath = "ads/{$ad->id}/large_thumb/" . basename($largePath);

        // Create proper thumbnail instead of copying full image
        // Use Imagick if available (supports more formats like AVIF), otherwise GD
        try {
            $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
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
        $thumbPath = $image->cropped_thumb_path ?? $image->large_thumb_path;

        return [
            'id' => $image->id,
            'original_name' => $image->original_name,
            'url' => $this->encodeStorageUrl($thumbPath),
            'variants' => [
                'large' => $this->encodeStorageUrl($image->large_path),
                'large_thumb' => $this->encodeStorageUrl($image->large_thumb_path),
                'cropped' => $image->cropped_path ? $this->encodeStorageUrl($image->cropped_path) : null,
                'cropped_thumb' => $image->cropped_thumb_path ? $this->encodeStorageUrl($image->cropped_thumb_path) : null,
            ],
            'is_title' => $image->is_title,
        ];
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

        return $baseUrl . $encodedPath;
    }

    private function formOptions(): array
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
        ];
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function appendImages(Ad $ad, array $files): void
    {
        if ($files === []) {
            return;
        }

        foreach ($files as $file) {
            $variants = $this->storeImageVariants($ad, $file);

            $ad->images()->create([
                ...$variants,
                'original_name' => $file->getClientOriginalName(),
                'is_title' => false,
            ]);
        }

        $hasTitle = $ad->images()->where('is_title', true)->exists();

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
        $ads = Ad::query()
            ->whereBelongsTo($request->user())
            ->with(['images:id,ad_id,original_name,large_thumb_path,cropped_thumb_path,is_title'])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn(Ad $ad): array => [
                'id' => $ad->id,
                'title' => $ad->title,
                'description' => $ad->description,
                'status' => $ad->status,
                'status_color' => $this->statusColor($ad->status),
                'price' => $ad->price,
                ...$this->expiryDetails($ad),
                'thumbnail_url' => $this->listThumbnailUrl($ad),
                'images' => $ad->images
                    ->map(fn(AdImage $image): array => $this->listImageDownloadPayload($ad, $image))
                    ->values()
                    ->all(),
            ]);

        return Inertia::render('ads/Index', [
            'ads' => $ads,
            'statusOptions' => config('ads.status.options'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ads/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreAdRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $payload = [
                ...$request->safe()->except(['images']),
                'status' => $request->validated('status') ?? config('ads.status.default'),
            ];

            if ($payload['status'] === 'Online') {
                $payload['last_online_at'] = now();
            }

            $ad = $request->user()->ads()->create($payload);

            /** @var list<UploadedFile> $files */
            $files = $request->file('images', []);
            $this->appendImages($ad, $files);
        });

        return to_route('ads.index')->with('success', 'Ad created successfully.');
    }

    public function edit(Ad $ad): Response
    {
        $this->authorize('update', $ad);

        return Inertia::render('ads/Edit', [
            'ad' => [
                ...$ad->toArray(),
                'images' => $ad->images()
                    ->get()
                    ->map(fn(AdImage $image): array => $this->imagePayload($image))
                    ->values()
                    ->all(),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateAdRequest $request, Ad $ad): RedirectResponse
    {
        $this->authorize('update', $ad);
        $payload = $request->validated();

        if (($payload['status'] ?? null) === 'Online' && $ad->status !== 'Online') {
            $payload['last_online_at'] = now();
        }

        $ad->update($payload);

        return to_route('ads.index')->with('success', 'Ad updated successfully.');
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
            'description' => $generated['description'],
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

        $downloadPath = $adImage->cropped_path ?? $adImage->large_path;
        abort_if($downloadPath === null, 404);

        return Storage::disk('public')->download($downloadPath, $adImage->original_name);
    }
}
