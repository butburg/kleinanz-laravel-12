<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Switch } from '@/components/ui/switch';
    import InfoIcon from '@lucide/svelte/icons/info';
    import { Link, page, router } from '@inertiajs/svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Check, ChevronsUpDown, Copy, Download, Pencil } from 'lucide-svelte';

    type Ad = {
        id: number;
        title: string;
        description: string;
        status: string;
        status_color: 'green' | 'amber' | 'zinc';
        price: number;
        expiry_at: string | null;
        days_to_expiry: number | null;
        is_expired: boolean;
        thumbnail_url: string | null;
        images: Array<{
            id: number;
            original_name: string;
            download_url: string;
        }>;
    };

    type PaginatedAds = {
        data: Ad[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };

    interface Props {
        ads: PaginatedAds;
        perPage: '10' | '20' | '50' | '100' | 'all';
        statusOptions: string[];
        options?: {
            conditions: string[];
            shipping: string[];
            statuses: string[];
            limits: {
                title: number;
                description: number;
                images: number;
                prompt: number;
            };
            image?: {
                client?: {
                    max_dimension?: number;
                    quality?: number;
                    output_mime?: string;
                };
            };
            platforms?: string[];
            default_platform?: string | null;
        };
        aiStatus?: {
            use_test_mode: boolean;
            has_user_api_key: boolean;
        };
        flash?: {
            success?: string | null;
            error?: string | null;
        };
        errors?: {
            images?: string;
            generate?: string;
            [key: string]: string | undefined;
        };
    }

    let isCreateAdPage = $derived($page.url === '/dashboard');

    let breadcrumbs: BreadcrumbItem[] = $derived([
        {
            title: isCreateAdPage ? 'Create Ad' : 'Ads',
            href: isCreateAdPage ? '/dashboard' : '/ads',
        },
    ]);

    let { ads, perPage, statusOptions, options, aiStatus, flash, errors }: Props = $props();
    let copiedTarget = $state<string | null>(null);
    let updatingStatusIds = $state<number[]>([]);

    // Create ad state
    type PendingImage = {
        id: string;
        file: File;
        previewUrl: string;
        originalName: string;
        wasResized: boolean;
    };

    let pendingImages = $state<PendingImage[]>([]);
    let selectedTitleIndex = $state(0);
    let promptValue = $state('');
    let autoCropEnabled = $state(true);
    let selectedPlatform = $state('');
    let isSubmitting = $state(false);
    let isPreparingImages = $state(false);

    // Derived state
    let imageCount = $derived(pendingImages.length);
    let hasImages = $derived(imageCount > 0);
    let hasPlatform = $derived(selectedPlatform.trim().length > 0);
    let canGenerate = $derived(hasImages && hasPlatform && !isSubmitting && !isPreparingImages);

    $effect(() => {
        if (selectedPlatform || !options?.platforms?.length) {
            return;
        }

        selectedPlatform = options.platforms.includes(options.default_platform ?? '')
            ? options.default_platform ?? ''
            : options.platforms[0];
    });

    function imageClientConfig() {
        const maxDimension = options?.image?.client?.max_dimension ?? 1000;
        const qualityPercent = options?.image?.client?.quality ?? 90;
        const outputMime = options?.image?.client?.output_mime ?? 'image/jpeg';

        return {
            maxDimension,
            quality: Math.max(0.1, Math.min(1, qualityPercent / 100)),
            outputMime,
        };
    }

    function nextFileName(originalName: string, outputMime: string): string {
        const extensionMap: Record<string, string> = {
            'image/jpeg': 'jpg',
            'image/png': 'png',
            'image/webp': 'webp',
            'image/avif': 'avif',
        };

        const extension = extensionMap[outputMime] ?? 'jpg';
        const dotIndex = originalName.lastIndexOf('.');
        const baseName = dotIndex > 0 ? originalName.slice(0, dotIndex) : originalName;

        return `${baseName}.${extension}`;
    }

    function loadImageDimensions(file: File): Promise<{ width: number; height: number; image: HTMLImageElement }> {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const image = new Image();

            image.onload = () => {
                URL.revokeObjectURL(url);
                resolve({ width: image.naturalWidth, height: image.naturalHeight, image });
            };

            image.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error(`Could not read image: ${file.name}`));
            };

            image.src = url;
        });
    }

    async function resizeIfNeeded(file: File): Promise<{ file: File; wasResized: boolean }> {
        const config = imageClientConfig();
        const { width, height, image } = await loadImageDimensions(file);
        const largest = Math.max(width, height);

        if (largest <= config.maxDimension) {
            return { file, wasResized: false };
        }

        const ratio = config.maxDimension / largest;
        const targetWidth = Math.max(1, Math.round(width * ratio));
        const targetHeight = Math.max(1, Math.round(height * ratio));

        const canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;

        const context = canvas.getContext('2d');
        if (!context) {
            return { file, wasResized: false };
        }

        context.drawImage(image, 0, 0, targetWidth, targetHeight);

        const blob = await new Promise<Blob | null>(resolve => {
            canvas.toBlob(resolve, config.outputMime, config.quality);
        });

        if (!blob) {
            return { file, wasResized: false };
        }

        return {
            file: new File([blob], nextFileName(file.name, config.outputMime), {
                type: config.outputMime,
                lastModified: Date.now(),
            }),
            wasResized: true,
        };
    }

    function normalizeTitleIndex(): void {
        if (pendingImages.length === 0) {
            selectedTitleIndex = 0;
            return;
        }

        if (selectedTitleIndex >= pendingImages.length) {
            selectedTitleIndex = pendingImages.length - 1;
        }
    }

    function resetCreateState(): void {
        pendingImages.forEach(image => URL.revokeObjectURL(image.previewUrl));
        pendingImages = [];
        selectedTitleIndex = 0;
        promptValue = '';
    }

    async function onImageSelection(event: Event): Promise<void> {
        const input = event.currentTarget as HTMLInputElement;
        const newFiles = Array.from(input.files ?? []);
        input.value = '';

        if (newFiles.length === 0) {
            return;
        }

        const maxFiles = options?.limits.images ?? 10;
        const remainingSlots = Math.max(0, maxFiles - pendingImages.length);

        if (remainingSlots === 0) {
            return;
        }

        const filesToProcess = newFiles.slice(0, remainingSlots);
        isPreparingImages = true;

        try {
            const prepared = await Promise.all(
                filesToProcess.map(async (file): Promise<PendingImage> => {
                    const resized = await resizeIfNeeded(file);

                    return {
                        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                        file: resized.file,
                        previewUrl: URL.createObjectURL(resized.file),
                        originalName: file.name,
                        wasResized: resized.wasResized,
                    };
                })
            );

            pendingImages = [...pendingImages, ...prepared];
            normalizeTitleIndex();
        } finally {
            isPreparingImages = false;
        }
    }

    function removePendingImage(index: number): void {
        const image = pendingImages[index];
        if (!image) return;

        URL.revokeObjectURL(image.previewUrl);
        pendingImages = pendingImages.filter((_, currentIndex) => currentIndex !== index);

        if (index < selectedTitleIndex) {
            selectedTitleIndex -= 1;
        } else if (index === selectedTitleIndex) {
            selectedTitleIndex = 0;
        }

        normalizeTitleIndex();
    }

    function movePendingImage(index: number, direction: -1 | 1): void {
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= pendingImages.length) return;

        const next = [...pendingImages];
        const [moved] = next.splice(index, 1);
        next.splice(targetIndex, 0, moved);
        pendingImages = next;

        if (selectedTitleIndex === index) {
            selectedTitleIndex = targetIndex;
        } else if (selectedTitleIndex === targetIndex) {
            selectedTitleIndex = index;
        }
    }

    function submitForGenerate(): void {
        if (!hasImages) return;

        isSubmitting = true;

        const formData = new FormData();
        pendingImages.forEach(image => formData.append('images[]', image.file));
        formData.append('prompt_text', promptValue);
        formData.append('title_image_index', String(selectedTitleIndex));
        formData.append('auto_crop_enabled', autoCropEnabled ? '1' : '0');
        formData.append('status', options?.statuses[0] || 'Entwurf');
        formData.append('platform', selectedPlatform);
        formData.append('_generate', 'true');

        router.post(route('ads.store'), formData, {
            preserveScroll: true,
            onSuccess: () => {
                resetCreateState();
            },
            onFinish: () => isSubmitting = false,
        });
    }

    function statusBadgeClasses(statusColor: Ad['status_color']): string {
        switch (statusColor) {
            case 'green':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'zinc':
                return 'bg-zinc-100 text-zinc-800 border-zinc-200';
            default:
                return 'bg-amber-100 text-amber-800 border-amber-200';
        }
    }

    function closeStatusMenu(event: MouseEvent): void {
        const trigger = event.currentTarget;
        if (!(trigger instanceof HTMLElement)) return;

        const details = trigger.closest('details');
        if (details) {
            details.open = false;
        }
    }

    function isUpdatingStatus(adId: number): boolean {
        return updatingStatusIds.includes(adId);
    }

    function updateStatus(ad: Ad, status: string, event: MouseEvent): void {
        if (isUpdatingStatus(ad.id)) {
            return;
        }

        closeStatusMenu(event);
        updatingStatusIds = [...updatingStatusIds, ad.id];
        const startedAt = performance.now();

        router.patch(route('ads.status.update', ad.id), { status }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                const remainingTime = Math.max(0, 900 - (performance.now() - startedAt));
                window.setTimeout(() => {
                    updatingStatusIds = updatingStatusIds.filter((id) => id !== ad.id);
                }, remainingTime);
            },
        });
    }

    async function copyText(text: string, target: string): Promise<void> {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const fallback = document.createElement('textarea');
            fallback.value = text;
            document.body.appendChild(fallback);
            fallback.select();
            document.execCommand('copy');
            document.body.removeChild(fallback);
        }

        copiedTarget = target;
        window.setTimeout(() => {
            if (copiedTarget === target) {
                copiedTarget = null;
            }
        }, 1200);
    }

    function updatePerPage(event: Event): void {
        const select = event.currentTarget as HTMLSelectElement;

        router.get(route('ads.index'), { per_page: select.value }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }
</script>

<svelte:head>
    <title>{isCreateAdPage ? 'Create Ad' : 'Ads'}</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-4 px-4 pt-4 pb-8">
        {#if flash?.success}
            <div class="rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800" data-test="flash-success">
                {flash.success}
            </div>
        {/if}

        {#if flash?.error}
            <div class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800" data-test="flash-error">
                {flash.error}
            </div>
        {/if}

        {#if isCreateAdPage}
            <div class="space-y-4 px-4 py-4">
                <!-- AI Status Warnings -->
                {#if aiStatus?.use_test_mode}
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                        <p class="font-medium">ℹ️ Test Mode Enabled</p>
                        <p class="mt-1 text-xs">
                            Using mock AI generator. No API costs.
                            <Link href={route('api-key.edit')} class="underline hover:text-blue-900 dark:hover:text-blue-100">
                                Disable in settings
                            </Link>
                        </p>
                    </div>
                {:else if !aiStatus?.has_user_api_key}
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        <p class="font-medium">⚠️ No API Key Configured</p>
                        <p class="mt-1 text-xs">
                            Using mock AI generator. To use real OpenAI generation,
                            <Link href={route('api-key.edit')} class="underline hover:text-amber-900 dark:hover:text-amber-100">
                                add your API key in settings
                            </Link>
                        </p>
                    </div>
                {/if}

                <!-- Upload Images Section -->
                <div class="space-y-2">
                    <Label for="create-images">Upload Images</Label>
                    <label
                        for="create-images"
                        class="block cursor-pointer rounded-md border-2 border-dashed p-4 text-center text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
                    >
                        Click to upload images
                    </label>
                    <Input
                        id="create-images"
                        type="file"
                        multiple
                        accept="image/*"
                        class="hidden"
                        onchange={onImageSelection}
                    />
                    <div class="text-xs text-muted-foreground">
                        {imageCount} {#if options?.limits.images}/ {options.limits.images}{/if} selected
                    </div>
                    {#if isPreparingImages}
                        <div class="text-xs text-muted-foreground">Optimizing images for upload...</div>
                    {/if}
                    {#if imageCount > 0}
                        <div class="max-h-24 space-y-1 overflow-auto rounded-md border p-2 text-xs">
                            {#each pendingImages as image (image.id)}
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate">{image.file.name}</span>
                                    {#if image.wasResized}
                                        <span class="text-[10px] text-muted-foreground">resized</span>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>

                {#if imageCount > 0}
                    <div class="space-y-2">
                        <Label>Arrange images and choose title image</Label>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                            {#each pendingImages as image, index (image.id)}
                                <div class={`rounded-md border p-1 ${selectedTitleIndex === index ? 'border-primary ring-2 ring-primary/40' : 'border-muted'}`}>
                                    <img src={image.previewUrl} alt={image.file.name} class="h-24 w-full rounded bg-muted/20 object-contain" />
                                    <div class="mt-1 truncate text-[11px]">{image.file.name}</div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={selectedTitleIndex === index ? 'default' : 'outline'}
                                            class="h-7 px-2 text-[11px]"
                                            onclick={() => {
                                                selectedTitleIndex = index;
                                            }}
                                        >
                                            Title
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="h-7 px-2 text-[11px]"
                                            disabled={index === 0}
                                            onclick={() => {
                                                movePendingImage(index, -1);
                                            }}
                                        >
                                            ↑
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="h-7 px-2 text-[11px]"
                                            disabled={index === pendingImages.length - 1}
                                            onclick={() => {
                                                movePendingImage(index, 1);
                                            }}
                                        >
                                            ↓
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            class="h-7 px-2 text-[11px]"
                                            onclick={() => {
                                                removePendingImage(index);
                                            }}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}

                {#if errors?.images}
                    <div class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {errors.images}
                    </div>
                {/if}

                {#if errors?.generate}
                    <div class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {errors.generate}
                    </div>
                {/if}

                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <Label for="create-auto-crop">Crop to clothing</Label>
                            <span
                                class="inline-flex text-muted-foreground"
                                title="Keeps the clothing item centered in the photo."
                                aria-label="Keeps the clothing item centered in the photo."
                            >
                                <InfoIcon class="size-4" />
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">Turn off to keep your photos as they are.</p>
                    </div>
                    <Switch id="create-auto-crop" bind:checked={autoCropEnabled} aria-label="Auto-crop images" />
                </div>

                <!-- Prompt Field -->
                <div class="space-y-2">
                    <Label for="create-platform">Platform</Label>
                    {#if options?.platforms?.length}
                        <select
                            id="create-platform"
                            bind:value={selectedPlatform}
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            {#each options.platforms as platform (platform)}
                                <option value={platform}>{platform}</option>
                            {/each}
                        </select>
                    {:else}
                        <p class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                            Add a platform and its standard appendix before generating an ad.
                            <Link href={route('appendices.index')} class="underline">Manage platforms</Link>
                        </p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="create-prompt">Prompt (optional)</Label>
                    <textarea
                        id="create-prompt"
                        bind:value={promptValue}
                        class="w-full rounded-md border p-2"
                        rows="3"
                        placeholder="Optional instructions for AI generation..."
                        maxlength={options?.limits.prompt || 1000}
                    ></textarea>
                    <div class="text-xs text-muted-foreground">
                        {promptValue.length} / {options?.limits.prompt || 1000}
                    </div>
                </div>

                <!-- Generate Button -->
                <Button
                    type="button"
                    onclick={submitForGenerate}
                    disabled={!canGenerate}
                >
                    Generate Ad
                </Button>
            </div>
        {:else}
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">My Ads</h1>

                <div class="flex items-center gap-2 pb-1">
                    <Label for="ads-per-page" class="sr-only">Items per page</Label>
                    <select
                        id="ads-per-page"
                        value={perPage}
                        onchange={updatePerPage}
                        title="Items per page"
                        class="flex h-9 w-20 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>

            {#if ads.data.length === 0}
                <p class="text-muted-foreground">No ads yet.</p>
            {:else}
                <ul data-test="ads-list">
                    {#each ads.data as ad (ad.id)}
                        <li class="border-b py-4 last:border-b-0">
                            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start">
                                {#if ad.thumbnail_url}
                                    <img
                                        src={ad.thumbnail_url}
                                        alt={`Thumbnail for ${ad.title}`}
                                        class="h-[220px] w-full max-w-[220px] shrink-0 rounded-md border bg-muted/20 object-contain"
                                        data-test={`ad-thumbnail-${ad.id}`}
                                    />
                                {/if}
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="font-medium">{ad.title}</div>
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                        <details class="relative inline-block" data-test={`status-menu-${ad.id}`}>
                                            <summary
                                                class={`inline-flex items-center cursor-pointer list-none rounded-md border px-2 py-0.5 text-xs font-medium ${statusBadgeClasses(ad.status_color)} ${isUpdatingStatus(ad.id) ? 'status-saving pointer-events-none' : ''}`}
                                                data-test={`ad-status-${ad.id}`}
                                                aria-label={`Change status, currently ${ad.status}`}
                                                aria-busy={isUpdatingStatus(ad.id)}
                                            >
                                                {ad.status}
                                                <ChevronsUpDown class="ml-1 size-3" aria-hidden="true" />
                                            </summary>
                                            <div class="absolute z-10 mt-1 min-w-36 rounded-md border bg-background p-1 shadow-md">
                                                {#each statusOptions as statusOption (statusOption)}
                                                    <button
                                                        type="button"
                                                        class="block w-full rounded px-2 py-1 text-left text-xs hover:bg-muted"
                                                        onclick={(event) => updateStatus(ad, statusOption, event)}
                                                        disabled={isUpdatingStatus(ad.id)}
                                                    >
                                                        {statusOption}
                                                    </button>
                                                {/each}
                                            </div>
                                        </details>
                                        <span>{ad.price} EUR</span>
                                    </div>
                                    {#if ad.expiry_at}
                                        <div class="text-xs" data-test={`ad-expiry-${ad.id}`}>
                                            <span class={ad.is_expired ? 'text-red-700' : 'text-amber-700'}>
                                                Expires {ad.expiry_at}
                                                {#if ad.is_expired}
                                                    (Expired)
                                                {:else if ad.days_to_expiry !== null}
                                                    ({ad.days_to_expiry}d left)
                                                {/if}
                                            </span>
                                        </div>
                                    {/if}

                                    <div class="mt-3 flex flex-col gap-2">
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class={`w-full sm:w-auto ${copiedTarget === `title-${ad.id}` ? 'copy-saved' : ''}`}
                                                onclick={() => {
                                                    void copyText(ad.title, `title-${ad.id}`);
                                                }}
                                                data-test={`copy-title-${ad.id}`}
                                            >
                                                {#if copiedTarget === `title-${ad.id}`}
                                                    <Check class="mr-2 size-4" aria-hidden="true" data-test={`copy-title-check-${ad.id}`} />
                                                {:else}
                                                    <Copy class="mr-2 size-4" aria-hidden="true" />
                                                {/if}
                                                Copy title
                                            </Button>

                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class={`w-full sm:w-auto ${copiedTarget === `description-${ad.id}` ? 'copy-saved' : ''}`}
                                                onclick={() => {
                                                    void copyText(ad.description, `description-${ad.id}`);
                                                }}
                                                data-test={`copy-description-${ad.id}`}
                                            >
                                                {#if copiedTarget === `description-${ad.id}`}
                                                    <Check class="mr-2 size-4" aria-hidden="true" data-test={`copy-description-check-${ad.id}`} />
                                                {:else}
                                                    <Copy class="mr-2 size-4" aria-hidden="true" />
                                                {/if}
                                                Copy description
                                            </Button>
                                        </div>

                                        {#if ad.images.length > 0}
                                            <div class="flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    class="w-full sm:w-auto"
                                                    onclick={() => window.location.assign(route('ads.images.download-all', ad.id))}
                                                    data-test={`download-images-${ad.id}`}
                                                >
                                                    <Download class="mr-2 size-4" aria-hidden="true" />
                                                    Download all images
                                                </Button>
                                            </div>
                                        {/if}

                                        <div class="flex flex-wrap gap-2">
                                            <Link href={route('ads.edit', ad.id)} class="w-full sm:w-auto">
                                                <Button size="sm" class="w-full">
                                                    <Pencil class="mr-2 size-4" aria-hidden="true" />
                                                    Edit ad
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    {/each}
                </ul>

                <div class="mt-4 flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                    <div class="text-xs text-muted-foreground">Page {ads.current_page} of {ads.last_page}</div>
                    <div class="flex items-center gap-3">
                        {#if ads.prev_page_url}
                            <Link href={ads.prev_page_url}>
                                <Button size="sm" variant="outline">Previous</Button>
                            </Link>
                        {/if}
                        {#if ads.next_page_url}
                            <Link href={ads.next_page_url}>
                                <Button size="sm" variant="outline">Next</Button>
                            </Link>
                        {/if}
                    </div>
                </div>
            {/if}
        {/if}
    </div>
</AppLayout>

<style>
    @keyframes field-saved-pulse {
        0%,
        100% {
            border-color: #22c55e;
        }

        50% {
            border-color: #86efac;
        }
    }

    @keyframes status-background-sweep {
        from {
            background-position: 200% 0;
        }

        to {
            background-position: -100% 0;
        }
    }

    .status-saving {
        background-image: linear-gradient(
            90deg,
            transparent 0%,
            color-mix(in srgb, currentColor 14%, transparent) 45%,
            color-mix(in srgb, currentColor 22%, transparent) 50%,
            color-mix(in srgb, currentColor 14%, transparent) 55%,
            transparent 100%
        );
        background-size: 300% 100%;
        animation: status-background-sweep 2400ms linear infinite;
    }

    :global(.copy-saved) {
        border-color: #22c55e;
        animation: field-saved-pulse 700ms ease-out 1;
    }

    @media (prefers-reduced-motion: reduce) {
        :global(.copy-saved),
        .status-saving {
            animation: none;
        }

        .status-saving {
            background-image: none;
        }
    }
</style>
