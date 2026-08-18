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
        onCurrentClick?: () => void;
    }

    let { breadcrumbs, onCurrentClick }: Props = $props();
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
                {:else if index === breadcrumbs.length - 1 && onCurrentClick}
                    <button
                        type="button"
                        class="cursor-pointer bg-transparent p-0 text-foreground hover:text-foreground/80"
                        onclick={onCurrentClick}
                        aria-label={`Toggle navigation for ${item.title}`}
                    >
                        {item.title}
                    </button>
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
