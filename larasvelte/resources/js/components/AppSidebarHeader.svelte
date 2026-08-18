<script lang="ts">
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
    import type { BreadcrumbItem } from '@/types';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
    }

    let { breadcrumbs = [] }: Props = $props();
    const sidebar = useSidebar();
</script>

<header
    class="fixed inset-x-0 top-0 z-10 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:static md:z-auto md:px-4"
>
    <div class="flex items-center gap-2">
        <SidebarTrigger class="-ml-1" />

        {#if breadcrumbs.length > 0}
            <Breadcrumbs {breadcrumbs} onCurrentClick={sidebar.toggle} />
        {/if}
    </div>
</header>
