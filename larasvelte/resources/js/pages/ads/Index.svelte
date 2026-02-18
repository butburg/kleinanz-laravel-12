<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';

    type Ad = {
        id: number;
        title: string;
        status: string;
        price: number;
    };

    interface Props {
        ads: Ad[];
    }

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Ads',
            href: '/ads',
        },
    ];

    let { ads }: Props = $props();
</script>

<svelte:head>
    <title>Ads</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-4 px-4 pt-4">
        <h1 class="text-2xl font-semibold">My Ads</h1>

        {#if ads.length === 0}
            <p class="text-muted-foreground">No ads yet.</p>
        {:else}
            <ul class="space-y-2">
                {#each ads as ad (ad.id)}
                    <li class="rounded border p-3">
                        <div class="font-medium">{ad.title}</div>
                        <div class="text-sm text-muted-foreground">{ad.status} · {ad.price} EUR</div>
                    </li>
                {/each}
            </ul>
        {/if}
    </div>
</AppLayout>
