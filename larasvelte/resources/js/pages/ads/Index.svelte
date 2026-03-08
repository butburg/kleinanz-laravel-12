<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Form, Link, router } from '@inertiajs/svelte';
    import type { BreadcrumbItem } from '@/types';

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
        };
        flash?: {
            success?: string | null;
            error?: string | null;
        };
    }

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Ads',
            href: '/ads',
        },
    ];

    let { ads, statusOptions, options, flash }: Props = $props();
    let copyFeedback = $state<string | null>(null);

    // Create ad state
    let selectedImages = $state<FileList | null>(null);
    let selectedImageNames = $state<string[]>([]);
    let promptValue = $state('');
    let isSubmitting = $state(false);
    let createExpanded = $state(false);

    // Derived state
    let imageCount = $derived(selectedImages?.length ?? 0);
    let hasImages = $derived(imageCount > 0);
    let canGenerate = $derived(hasImages && !isSubmitting);

    function onImageSelection(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;
        selectedImages = input.files;
        selectedImageNames = Array.from(input.files ?? []).map(f => f.name);
    }

    function submitForGenerate(): void {
        if (!hasImages) return;

        isSubmitting = true;

        const formData = new FormData();
        Array.from(selectedImages!).forEach(file => formData.append('images[]', file));
        formData.append('title', '');
        formData.append('description', '');
        formData.append('prompt_text', promptValue);
        formData.append('price', '0');
        formData.append('condition', options?.conditions[0] || 'Gut');
        formData.append('shipping', options?.shipping[0] || 'klein');
        formData.append('status', options?.statuses[0] || 'Entwurf');
        formData.append('_generate', 'true');

        router.post(route('ads.store'), formData, {
            preserveScroll: true,
            onSuccess: () => {
                // Reset form
                selectedImages = null;
                selectedImageNames = [];
                promptValue = '';
                createExpanded = false;
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

    async function copyText(text: string, successMessage: string): Promise<void> {
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

        copyFeedback = successMessage;
        window.setTimeout(() => {
            if (copyFeedback === successMessage) {
                copyFeedback = null;
            }
        }, 1500);
    }

    function downloadAllImages(images: Ad['images']): void {
        images.forEach((image, index) => {
            window.setTimeout(() => {
                window.open(image.download_url, '_blank', 'noopener');
            }, index * 150);
        });
    }
</script>

<svelte:head>
    <title>Ads</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-4 px-4 pt-4">
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

        <!-- Create Ad Expandable Card -->
        <details bind:open={createExpanded} class="rounded-md border bg-card shadow-sm">
            <summary class="cursor-pointer px-4 py-3 font-medium hover:bg-muted/50">
                Create Ad
            </summary>
            <div class="space-y-4 border-t px-4 py-4">
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
                    {#if imageCount > 0}
                        <div class="max-h-24 space-y-1 overflow-auto rounded-md border p-2 text-xs">
                            {#each Array.from(selectedImages || []) as file (file.name)}
                                <div>{file.name}</div>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- Prompt Field -->
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
        </details>

        <h1 class="text-2xl font-semibold">My Ads</h1>

        {#if ads.data.length === 0}
            <p class="text-muted-foreground">No ads yet.</p>
        {:else}
            <ul data-test="ads-list">
                {#each ads.data as ad (ad.id)}
                    <li class="border-b py-4 last:border-b-0">
                        <div class="flex min-w-0 flex-col gap-3">
                            {#if ad.thumbnail_url}
                                <img
                                    src={ad.thumbnail_url}
                                    alt={`Thumbnail for ${ad.title}`}
                                    class="h-[220px] w-full max-w-[220px] rounded-md border object-cover"
                                    data-test={`ad-thumbnail-${ad.id}`}
                                />
                            {/if}
                            <div class="min-w-0 space-y-2">
                                <div class="font-medium">{ad.title}</div>
                                <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <details class="relative inline-block" data-test={`status-menu-${ad.id}`}>
                                        <summary
                                            class={`inline-flex cursor-pointer list-none rounded-md border px-2 py-0.5 text-xs font-medium ${statusBadgeClasses(ad.status_color)}`}
                                            data-test={`ad-status-${ad.id}`}
                                        >
                                            {ad.status}
                                        </summary>
                                        <div class="absolute z-10 mt-1 min-w-36 rounded-md border bg-background p-1 shadow-md">
                                            {#each statusOptions as statusOption (statusOption)}
                                                <Form method="patch" action={route('ads.status.update', ad.id)}>
                                                    <input type="hidden" name="status" value={statusOption} />
                                                    <button
                                                        type="submit"
                                                        class="block w-full rounded px-2 py-1 text-left text-xs hover:bg-muted"
                                                    >
                                                        {statusOption}
                                                    </button>
                                                </Form>
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
                                            class="w-full sm:w-auto"
                                            onclick={() => {
                                                void copyText(ad.title, 'Title copied.');
                                            }}
                                        >
                                            Copy title
                                        </Button>

                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="w-full sm:w-auto"
                                            onclick={() => {
                                                void copyText(ad.description, 'Description copied.');
                                            }}
                                        >
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
                                                onclick={() => downloadAllImages(ad.images)}
                                                data-test={`download-images-${ad.id}`}
                                            >
                                                Download all images
                                            </Button>
                                        </div>
                                    {/if}

                                    <div class="flex flex-wrap gap-2">
                                        <Link href={route('ads.edit', ad.id)}>
                                            <Button size="sm" class="w-full sm:w-auto">Edit ad</Button>
                                        </Link>
                                    </div>
                                </div>
                                {#if copyFeedback}
                                    <p class="mt-1 text-xs text-muted-foreground" data-test="copy-feedback">{copyFeedback}</p>
                                {/if}
                            </div>
                        </div>
                    </li>
                {/each}
            </ul>

            <div class="mt-2 flex items-center justify-between">
                <div class="text-xs text-muted-foreground">Page {ads.current_page} of {ads.last_page}</div>
                <div class="flex items-center gap-2">
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
    </div>
</AppLayout>
