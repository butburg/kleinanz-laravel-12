<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';

    type Ad = {
        id: number;
        title: string;
    };

    interface Props {
        ad: Ad;
    }

    let { ad }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Ads',
            href: '/ads',
        },
        {
            title: 'Edit',
            href: `/ads/${ad.id}/edit`,
        },
    ];
</script>

<svelte:head>
    <title>Edit Ad</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-2 px-4 pt-4">
        <h1 class="text-2xl font-semibold">Edit Ad</h1>
        <p class="text-muted-foreground">Editing: {ad.title}</p>
    </div>
</AppLayout>
