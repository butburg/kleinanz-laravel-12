<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { router } from '@inertiajs/svelte';
    import { Form } from '@inertiajs/svelte';
    import type { BaseFormSnippetProps } from '@/types/forms';

    type AdImage = {
        id: number;
        original_name: string;
        url: string;
        is_title: boolean;
        position: number;
        use_cropped?: boolean;
        variants?: {
            large: string;
            large_thumb: string;
            cropped: string | null;
            cropped_thumb: string | null;
        };
        is_cropped?: boolean;
        crop_metadata?: {
            original_size?: [number, number] | null;
            cropped_size?: [number, number] | null;
            cropped_at?: string | null;
            crop_status?: 'queued' | 'processing' | 'completed' | 'failed' | 'no_detection' | null;
            crop_requested_at?: string | null;
            crop_started_at?: string | null;
            crop_error?: string | null;
            crop_pending_seconds?: number | null;
            is_queue_stale?: boolean;
            is_queue_stuck?: boolean;
        } | null;
    };

    type Ad = {
        id: string;
        title: string;
        description: string;
        price: number;
        condition: string;
        shipping: string;
        status: string;
        prompt_text?: string | null;
        images: AdImage[];
    };

    interface Props {
        ad: Ad;
        options: {
            conditions: string[];
            shipping: string[];
            statuses: string[];
            limits: {
                title: number;
                description: number;
                images: number;
                prompt: number;
            };
        };
    }

    let { ad, options }: Props = $props();

    let breadcrumbs: BreadcrumbItem[] = $derived([
        { title: 'Ads', href: '/ads' },
        { title: 'Edit', href: `/ads/${ad.id}/edit` },
    ]);

    // Form state
    let titleValue = $state('');
    let descriptionValue = $state('');
    let priceValue = $state<number | ''>('');
    let conditionValue = $state('');
    let shippingValue = $state('');
    let statusValue = $state('');
    let promptValue = $state('');
    let selectedImages = $state<FileList | null>(null);
    let selectedImageNames = $state<string[]>([]);
    let showGenerateConfirm = $state(false);
    let showDeleteConfirm = $state(false);
    let isSubmitting = $state(false);
    let fieldStates = $state<Record<string, 'saved' | 'saving' | 'error'>>({});
    let fieldErrors = $state<Record<string, string>>({});
    let imageActionState = $state<Record<string, { deleting?: boolean; cropping?: boolean }>>({});
    let saveTimeouts = new Map<string, number>();
    let isRefreshingCropStatus = $state(false);

    const CROP_STATUS_POLL_INTERVAL_MS = 3000;

    $effect(() => {
        titleValue = ad.title;
        descriptionValue = ad.description;
        priceValue = ad.price;
        conditionValue = ad.condition;
        shippingValue = ad.shipping;
        statusValue = ad.status;
        promptValue = ad.prompt_text ?? '';
    });

    $effect(() => {
        const hasPendingCrop = ad.images.some((image) =>
            image.crop_metadata?.crop_status === 'queued' || image.crop_metadata?.crop_status === 'processing'
        );

        if (!hasPendingCrop) {
            return;
        }

        const intervalId = window.setInterval(() => {
            if (isRefreshingCropStatus) {
                return;
            }

            isRefreshingCropStatus = true;

            router.reload({
                only: ['ad'],
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    isRefreshingCropStatus = false;
                },
            });
        }, CROP_STATUS_POLL_INTERVAL_MS);

        return () => {
            clearInterval(intervalId);
            isRefreshingCropStatus = false;
        };
    });

    // Derived state
    let hasImages = $derived(ad.images.length > 0);
    let hasFieldContent = $derived(
        titleValue.trim().length > 0 ||
        descriptionValue.trim().length > 0 ||
        promptValue.trim().length > 0 ||
        (typeof priceValue === 'number' && priceValue > 0)
    );
    let canGenerate = $derived(hasImages && !isSubmitting);

    function onImageSelection(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;
        selectedImages = input.files;
        selectedImageNames = Array.from(input.files ?? []).map(f => f.name);
    }

    function handleGenerateClick(): void {
        if (hasFieldContent) {
            showGenerateConfirm = true;
        } else {
            submitForGenerate();
        }
    }

    function submitForGenerate(): void {
        if (!hasImages) return;

        isSubmitting = true;
        showGenerateConfirm = false;

        router.post(route('ads.generate', ad.id), {
            prompt_text: promptValue,
        }, {
            preserveScroll: true,
            onFinish: () => isSubmitting = false,
        });
    }

    function handleSave(): void {
        if (!hasImages) return;

        isSubmitting = true;

        router.patch(route('ads.update', ad.id), {
            title: titleValue,
            description: descriptionValue,
            price: priceValue,
            condition: conditionValue,
            shipping: shippingValue,
            status: statusValue,
            prompt_text: promptValue,
        }, {
            preserveScroll: true,
            onFinish: () => isSubmitting = false,
        });
    }

    function handleDelete(): void {
        isSubmitting = true;
        showDeleteConfirm = false;

        router.delete(route('ads.destroy', ad.id), {
            onFinish: () => isSubmitting = false,
        });
    }

    // Auto-save on blur with debounce
    function autoSaveField(fieldName: string, value: any): void {
        // Clear previous timeout for this field
        const existingTimeout = saveTimeouts.get(fieldName);
        if (existingTimeout) {
            clearTimeout(existingTimeout);
        }

        fieldStates[fieldName] = 'saving';
        fieldErrors[fieldName] = '';

        // Debounce: wait 300ms before saving
        const timeoutId = window.setTimeout(() => {
            router.patch(route('ads.update', ad.id), {
                [fieldName]: value,
            }, {
                preserveScroll: true,
                preserveState: false, // Allow state updates
                onSuccess: () => {
                    fieldStates[fieldName] = 'saved';
                    fieldErrors[fieldName] = '';
                    setTimeout(() => {
                        fieldStates[fieldName] = undefined as any;
                    }, 2000);
                },
                onError: (errors) => {
                    fieldStates[fieldName] = 'error';
                    fieldErrors[fieldName] = errors[fieldName] || 'Failed to save';
                },
                onFinish: () => {
                    saveTimeouts.delete(fieldName);
                },
            });
        }, 300);

        saveTimeouts.set(fieldName, timeoutId);
    }

    function getFieldClass(fieldName: string): string {
        const state = fieldStates[fieldName];
        if (state === 'saved') return 'border-green-500';
        if (state === 'saving') return 'border-yellow-500 animate-pulse';
        if (state === 'error') return 'border-red-500';
        return '';
    }

    function getFieldError(fieldName: string): string {
        return fieldErrors[fieldName] || '';
    }

    function setImageActionState(imageId: number, key: 'deleting' | 'cropping', value: boolean): void {
        const id = String(imageId);
        imageActionState = {
            ...imageActionState,
            [id]: {
                ...(imageActionState[id] ?? {}),
                [key]: value,
            },
        };
    }

    function isImageDeleting(imageId: number): boolean {
        return Boolean(imageActionState[String(imageId)]?.deleting);
    }

    function isImageCropping(imageId: number): boolean {
        return Boolean(imageActionState[String(imageId)]?.cropping);
    }

    function deleteImage(imageId: number): void {
        if (!window.confirm('Please confirm that you want to delete this image.')) {
            return;
        }

        setImageActionState(imageId, 'deleting', true);

        router.delete(route('ads.images.destroy', [ad.id, imageId]), {
            preserveScroll: true,
            onFinish: () => setImageActionState(imageId, 'deleting', false),
        });
    }

    function toggleCrop(imageId: number): void {
        setImageActionState(imageId, 'cropping', true);

        router.post(route('ads.images.toggle-crop', [ad.id, imageId]), {}, {
            preserveScroll: true,
            onFinish: () => setImageActionState(imageId, 'cropping', false),
        });
    }

    function formatSeconds(value: number | null | undefined): string {
        if (typeof value !== 'number' || Number.isNaN(value)) {
            return 'unknown duration';
        }

        if (value < 60) {
            return `${value}s`;
        }

        const minutes = Math.floor(value / 60);
        const seconds = value % 60;
        return `${minutes}m ${seconds}s`;
    }
</script>

<svelte:head>
    <title>Edit Ad</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-4 px-4 pt-4">
        <h1 class="text-2xl font-semibold">Edit Ad</h1>

        <section class="space-y-3 rounded-md border p-4">
            <h2 class="text-lg font-medium">Images</h2>

            <!-- Show existing images + upload new -->
            <Form method="post" action={route('ads.images.store', ad.id)} class="space-y-2">
                    {#snippet children({ errors, processing }: BaseFormSnippetProps)}
                        <div class="space-y-2">
                            <Label for="new_images">Upload new images</Label>
                            <label
                                for="new_images"
                                class="block cursor-pointer rounded-md border-2 border-dashed p-4 text-center text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
                            >
                                Click to upload images
                            </label>
                            <Input id="new_images" name="images[]" type="file" multiple accept="image/*" class="hidden" onchange={onImageSelection} />
                            <div class="text-xs text-muted-foreground">
                                {selectedImageNames.length} selected (max {options.limits.images} total per ad)
                            </div>
                            {#if selectedImageNames.length > 0}
                                <div class="max-h-24 space-y-1 overflow-auto rounded-md border p-2 text-xs">
                                    {#each selectedImageNames as fileName (fileName)}
                                        <div>{fileName}</div>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                        {#if selectedImageNames.length > 0}
                            <Button type="submit" disabled={processing}>Add selected images</Button>
                        {/if}
                    {/snippet}
                </Form>

                {#if ad.images.length === 0}
                    <p class="text-sm text-muted-foreground">No images uploaded yet.</p>
                {:else}
                    <div class="grid gap-3 md:grid-cols-2">
                        {#each ad.images as image (image.id)}
                            <div class="space-y-2 rounded-md border p-3">
                                <img src={image.url} alt={image.original_name} class="h-40 w-full rounded-md bg-muted/20 object-contain" />
                                <p class="text-sm">{image.original_name}</p>
                                {#if image.is_cropped}
                                    <p class="text-xs font-medium text-green-700">Clothes detected and cropped</p>
                                    {#if image.crop_metadata?.cropped_size}
                                        <p class="text-xs text-muted-foreground">
                                            Cropped size: {image.crop_metadata.cropped_size[0]}x{image.crop_metadata.cropped_size[1]}
                                        </p>
                                    {/if}

                                    <p class="text-xs text-muted-foreground">
                                        Currently using: {image.use_cropped ? 'Cropped' : 'Original'}
                                    </p>
                                {:else if image.crop_metadata?.is_queue_stuck}
                                    <p class="text-xs font-medium text-red-700">
                                        Cropping appears stuck ({formatSeconds(image.crop_metadata?.crop_pending_seconds)}).
                                        Queue worker may not be running.
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Ask admin to run: php artisan queue:work --queue=default --stop-when-empty
                                    </p>
                                {:else if image.crop_metadata?.is_queue_stale}
                                    <p class="text-xs font-medium text-amber-700">
                                        Cropping is taking longer than expected ({formatSeconds(image.crop_metadata?.crop_pending_seconds)}).
                                    </p>
                                {:else if image.crop_metadata?.crop_status === 'queued' || image.crop_metadata?.crop_status === 'processing' || isImageCropping(image.id)}
                                    <p class="text-xs font-medium text-blue-700">Cropping in progress...</p>
                                {:else if image.crop_metadata?.crop_status === 'no_detection'}
                                    <p class="text-xs text-amber-700">No clothing item detected. You can try crop again.</p>
                                {:else if image.crop_metadata?.crop_status === 'failed'}
                                    <p class="text-xs text-red-700">Cropping failed. Please retry.</p>
                                    {#if image.crop_metadata?.crop_error}
                                        <p class="text-xs text-muted-foreground">Error: {image.crop_metadata.crop_error}</p>
                                    {/if}
                                {:else}
                                    <p class="text-xs text-amber-700">No crop available yet. You can apply it now.</p>
                                {/if}

                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={isImageCropping(image.id) || isImageDeleting(image.id)}
                                    onclick={() => toggleCrop(image.id)}
                                >
                                    {#if image.crop_metadata?.crop_status === 'queued' || image.crop_metadata?.crop_status === 'processing' || isImageCropping(image.id)}
                                        Cropping...
                                    {:else if image.is_cropped}
                                        {image.use_cropped ? 'Restore original image' : 'Use cropped image'}
                                    {:else}
                                        Apply crop now
                                    {/if}
                                </Button>
                                {#if image.is_title}
                                    <p class="text-sm font-medium text-green-700">Title image</p>
                                {:else}
                                    <Form method="patch" action={route('ads.images.set-title', [ad.id, image.id])}>
                                        <Button type="submit" size="sm">Set as title</Button>
                                    </Form>
                                {/if}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={isImageDeleting(image.id) || isImageCropping(image.id)}
                                    onclick={() => deleteImage(image.id)}
                                >
                                    {isImageDeleting(image.id) ? 'Deleting...' : 'Delete image'}
                                </Button>
                            </div>
                        {/each}
                    </div>
                {/if}

            <div class="flex gap-2">
                <Dialog open={showGenerateConfirm} onOpenChange={(open) => (showGenerateConfirm = open)}>
                    <DialogContent>
                        <DialogHeader class="space-y-2">
                            <DialogTitle>Generate Ad Text?</DialogTitle>
                            <DialogDescription>
                                Generating will overwrite the title, description, price, and condition with AI-generated content. Continue?
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button onclick={submitForGenerate}>Generate</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Button type="button" onclick={handleGenerateClick} disabled={!canGenerate} variant="outline">
                    Generate with AI
                </Button>
            </div>
        </section>

        <section class="space-y-3 rounded-md border p-4">
            <h2 class="text-lg font-medium">Details</h2>

            <div class="grid gap-2">
                <div class="flex items-center gap-1">
                    <Label for="title">Title</Label>
                    {#if fieldStates.title === 'saved'}
                        <span class="text-sm text-green-600">✓ Saved</span>
                    {:else if fieldStates.title === 'saving'}
                        <span class="text-sm text-yellow-600">Saving...</span>
                    {/if}
                </div>
                <Input
                    id="title"
                    bind:value={titleValue}
                    onblur={() => autoSaveField('title', titleValue)}
                    class={getFieldClass('title')}
                    required
                />
                <div class="text-xs text-muted-foreground">{titleValue.length} / {options.limits.title}</div>
                {#if getFieldError('title')}
                    <p class="text-sm text-red-600">{getFieldError('title')}</p>
                {/if}
            </div>

            <div class="grid gap-2">
                <div class="flex items-center gap-1">
                    <Label for="description">Description</Label>
                    {#if fieldStates.description === 'saved'}
                        <span class="text-sm text-green-600">✓ Saved</span>
                    {:else if fieldStates.description === 'saving'}
                        <span class="text-sm text-yellow-600">Saving...</span>
                    {/if}
                </div>
                <textarea
                    id="description"
                    bind:value={descriptionValue}
                    onblur={() => autoSaveField('description', descriptionValue)}
                    class="rounded-md border p-2 {getFieldClass('description')}"
                    required
                    rows="6"
                ></textarea>
                <div class="text-xs text-muted-foreground">{descriptionValue.length} / {options.limits.description}</div>
                {#if getFieldError('description')}
                    <p class="text-sm text-red-600">{getFieldError('description')}</p>
                {/if}
            </div>

            <div class="grid gap-2">
                <div class="flex items-center gap-1">
                    <Label for="prompt_text">Prompt (optional)</Label>
                    {#if fieldStates.prompt_text === 'saved'}
                        <span class="text-sm text-green-600">✓ Saved</span>
                    {:else if fieldStates.prompt_text === 'saving'}
                        <span class="text-sm text-yellow-600">Saving...</span>
                    {/if}
                </div>
                <textarea
                    id="prompt_text"
                    bind:value={promptValue}
                    onblur={() => autoSaveField('prompt_text', promptValue)}
                    class="rounded-md border p-2 {getFieldClass('prompt_text')}"
                    rows="3"
                ></textarea>
                <div class="text-xs text-muted-foreground">{promptValue.length} / {options.limits.prompt}</div>
                {#if getFieldError('prompt_text')}
                    <p class="text-sm text-red-600">{getFieldError('prompt_text')}</p>
                {/if}
            </div>

            <div class="grid gap-2">
                <div class="flex items-center gap-1">
                    <Label for="price">Price</Label>
                    {#if fieldStates.price === 'saved'}
                        <span class="text-sm text-green-600">✓ Saved</span>
                    {:else if fieldStates.price === 'saving'}
                        <span class="text-sm text-yellow-600">Saving...</span>
                    {/if}
                </div>
                <Input
                    id="price"
                    type="number"
                    min="0"
                    bind:value={priceValue}
                    onblur={() => autoSaveField('price', priceValue)}
                    class={getFieldClass('price')}
                    required
                />
                {#if getFieldError('price')}
                    <p class="text-sm text-red-600">{getFieldError('price')}</p>
                {/if}
            </div>

            <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                <div class="grid gap-2">
                    <div class="flex items-center gap-1">
                        <Label for="condition">Condition</Label>
                        {#if fieldStates.condition === 'saved'}
                            <span class="text-sm text-green-600">✓</span>
                        {:else if fieldStates.condition === 'saving'}
                            <span class="text-sm text-yellow-600">⋯</span>
                        {/if}
                    </div>
                    <select
                        id="condition"
                        bind:value={conditionValue}
                        onblur={() => autoSaveField('condition', conditionValue)}
                        class="rounded-md border p-2 {getFieldClass('condition')}"
                        required
                    >
                        {#each options.conditions as condition (condition)}
                            <option value={condition}>{condition}</option>
                        {/each}
                    </select>
                    {#if getFieldError('condition')}
                        <p class="text-sm text-red-600">{getFieldError('condition')}</p>
                    {/if}
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center gap-1">
                        <Label for="shipping">Shipping</Label>
                        {#if fieldStates.shipping === 'saved'}
                            <span class="text-sm text-green-600">✓</span>
                        {:else if fieldStates.shipping === 'saving'}
                            <span class="text-sm text-yellow-600">⋯</span>
                        {/if}
                    </div>
                    <select
                        id="shipping"
                        bind:value={shippingValue}
                        onblur={() => autoSaveField('shipping', shippingValue)}
                        class="rounded-md border p-2 {getFieldClass('shipping')}"
                        required
                    >
                        {#each options.shipping as shipping (shipping)}
                            <option value={shipping}>{shipping}</option>
                        {/each}
                    </select>
                    {#if getFieldError('shipping')}
                        <p class="text-sm text-red-600">{getFieldError('shipping')}</p>
                    {/if}
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center gap-1">
                    <Label for="status">Status</Label>
                    {#if fieldStates.status === 'saved'}
                        <span class="text-sm text-green-600">✓ Saved</span>
                    {:else if fieldStates.status === 'saving'}
                        <span class="text-sm text-yellow-600">Saving...</span>
                    {/if}
                </div>
                <select
                    id="status"
                    bind:value={statusValue}
                    onblur={() => autoSaveField('status', statusValue)}
                    class="rounded-md border p-2 {getFieldClass('status')}"
                >
                    {#each options.statuses as status (status)}
                        <option value={status}>{status}</option>
                    {/each}
                </select>
                {#if getFieldError('status')}
                    <p class="text-sm text-red-600">{getFieldError('status')}</p>
                {/if}
            </div>

            <div class="flex flex-wrap gap-2">
                <Button type="button" onclick={handleSave} disabled={isSubmitting || !hasImages}>
                    Save Changes
                </Button>

                <Dialog open={showDeleteConfirm} onOpenChange={(open) => (showDeleteConfirm = open)}>
                        <DialogContent>
                            <DialogHeader class="space-y-2">
                                <DialogTitle>Please confirm that you want to delete this ad.</DialogTitle>
                                <DialogDescription>
                                    This action cannot be undone. The ad and all its images will be permanently deleted.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button variant="destructive" onclick={handleDelete}>Delete</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Button type="button" variant="destructive" onclick={() => (showDeleteConfirm = true)} disabled={isSubmitting}>
                        Delete Ad
                    </Button>
            </div>
        </section>
    </div>
</AppLayout>
