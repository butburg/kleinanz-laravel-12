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
                {#if item.href && index !== breadcrumbs.length - 1}
                    <BreadcrumbLink>
                        {#if item.href === '/ads'}
                            <a href={adsIndexHref()} onclick={visitSavedAdsIndex}>{item.title}</a>
                        {:else}
                            <Link href={item.href}>{item.title}</Link>
                        {/if}
                    </BreadcrumbLink>
                {:else}
                    <BreadcrumbPage>{item.title}</BreadcrumbPage>
                {/if}
            </Item>
            {#if index !== breadcrumbs.length - 1}
                <BreadcrumbSeparator />
            {/if}
        {/each}
    </BreadcrumbList>
</Breadcrumb>
