<script lang="ts">
    import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
    import { adsIndexHref, visitSavedAdsIndex } from '@/hooks/useAdPreferences.svelte';
    import type { NavItem } from '@/types';
    import { Link, page } from '@inertiajs/svelte';

    interface Props {
        items: NavItem[];
    }

    let { items = [] }: Props = $props();

    const isCurrentRoute = $derived((url: string) => url === '/ads' ? $page.url.startsWith('/ads') : $page.url === url);
</script>

<SidebarGroup class="px-2 py-0 group-data-[collapsible=icon]:p-0">
    <SidebarGroupLabel>Navigation</SidebarGroupLabel>
    <SidebarMenu>
        {#each items as item (item.title)}
            <SidebarMenuItem>
                {#if item.href === '/ads'}
                    <a href={adsIndexHref()} onclick={visitSavedAdsIndex} class="block w-full">
                        <SidebarMenuButton isActive={isCurrentRoute(item.href)}>
                            {#snippet tooltipContent()}
                                {item.title}
                            {/snippet}
                            {#if item.icon}
                                {@const Icon = item.icon}
                                <Icon class="h-4 w-4 shrink-0" />
                            {/if}
                            <span>{item.title}</span>
                        </SidebarMenuButton>
                    </a>
                {:else}
                    <Link href={item.href} class="block w-full">
                        <SidebarMenuButton isActive={isCurrentRoute(item.href)}>
                            {#snippet tooltipContent()}
                                {item.title}
                            {/snippet}
                            {#if item.icon}
                                {@const Icon = item.icon}
                                <Icon class="h-4 w-4 shrink-0" />
                            {/if}
                            <span>{item.title}</span>
                        </SidebarMenuButton>
                    </Link>
                {/if}
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>
