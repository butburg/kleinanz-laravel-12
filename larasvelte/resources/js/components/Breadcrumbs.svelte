<script lang="ts">
    import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator, Item } from '@/components/ui/breadcrumb';
    import { adsIndexHref, visitSavedAdsIndex } from '@/hooks/useAdPreferences.svelte';
    import { Link } from '@inertiajs/svelte';

    interface BreadcrumbItem {
        title: string;
        href?: string;
    }

    interface Props {
        breadcrumbs: BreadcrumbItem[];
    }

    let { breadcrumbs }: Props = $props();
</script>

<Breadcrumb>
    <BreadcrumbList>
        {#each breadcrumbs as item, index (index)}
            <Item>
                {#if index === breadcrumbs.length - 1}
                    <BreadcrumbPage>{item.title}</BreadcrumbPage>
                {:else}
                    <BreadcrumbLink>
                        {#if item.href === '/ads'}
                            <a href={adsIndexHref()} onclick={visitSavedAdsIndex}>{item.title}</a>
                        {:else}
                            <Link href={item.href ?? '#'}>{item.title}</Link>
                        {/if}
                    </BreadcrumbLink>
                {/if}
            </Item>
            {#if index !== breadcrumbs.length - 1}
                <BreadcrumbSeparator />
            {/if}
        {/each}
    </BreadcrumbList>
</Breadcrumb>
