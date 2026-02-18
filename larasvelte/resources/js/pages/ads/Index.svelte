<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { Button } from '@/components/ui/button';
    import { Form, Link } from '@inertiajs/svelte';
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

    let { ads, statusOptions, flash }: Props = $props();
    let copyFeedback = $state<string | null>(null);

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

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">My Ads</h1>
            <Link href={route('ads.create')}>
                <Button>Create Ad</Button>
            </Link>
        </div>

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
