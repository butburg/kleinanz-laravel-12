<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Ads',
            href: '/ads',
        },
        {
            title: 'Create',
            href: '/ads/create',
        },
    ];
</script>

<svelte:head>
    <title>Create Ad</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-2 px-4 pt-4">
        <h1 class="text-2xl font-semibold">Create Ad</h1>
        <p class="text-muted-foreground">Ad form implementation will follow in next TDD steps.</p>
    </div>
</AppLayout>
