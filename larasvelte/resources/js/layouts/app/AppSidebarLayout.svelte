<script lang="ts">
    import AppContent from '@/components/AppContent.svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import AppSidebar from '@/components/AppSidebar.svelte';
    import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';
    import type { BreadcrumbItemType } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItemType[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();
</script>

<AppShell variant="sidebar">
    <AppSidebar />
    <AppContent variant="sidebar" class="overflow-x-hidden">
        <div class="mx-auto flex min-h-full w-full max-w-7xl flex-1 flex-col">
            <AppSidebarHeader {breadcrumbs} />
            <div class="flex-1">
                {@render children?.()}
            </div>
            <AppFooter />
        </div>
    </AppContent>
</AppShell>
