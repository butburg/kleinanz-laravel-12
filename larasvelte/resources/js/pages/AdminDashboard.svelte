<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { Button } from '@/components/ui/button';
    import type { BreadcrumbItem } from '@/types';
    import { Trash2 } from 'lucide-svelte';

    type AdminUser = {
        id: number;
        name: string;
        email: string;
        ads_count: number;
        images_count: number;
        platforms: string[];
        created_at: string;
    };

    interface Props {
        users: AdminUser[];
    }

    let { users }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];

    function formatCreatedAt(createdAt: string): string {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(createdAt));
    }

    function deleteUser(user: AdminUser): void {
        if (!window.confirm(`Delete ${user.name} and all ${user.ads_count} ads, ${user.images_count} images, and associated files? This cannot be undone.`)) {
            return;
        }

        router.delete(route('admin.users.destroy', user.id));
    }
</script>

<svelte:head>
    <title>Admin</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">Admin</h1>
            <p class="text-sm text-muted-foreground">Overview of registered users.</p>
        </div>

        <section class="max-h-[calc(100vh-13rem)] overflow-y-auto rounded-lg border">
            <div class="sticky top-0 z-10 hidden grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_5rem_5rem_minmax(0,1fr)_10rem_5rem] gap-4 border-b bg-muted/95 px-4 py-3 text-xs font-medium text-muted-foreground backdrop-blur 2xl:grid">
                <span>Name</span>
                <span>Email</span>
                <span>Ads</span>
                <span>Images</span>
                <span>Platforms</span>
                <span>Created</span>
                <span class="text-right">Actions</span>
            </div>

            {#if users.length === 0}
                <p class="px-4 py-8 text-sm text-muted-foreground">No users found.</p>
            {:else}
                {#each users as user (user.id)}
                    <article class="grid gap-3 border-b px-4 py-4 last:border-b-0 sm:grid-cols-2 2xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_5rem_5rem_minmax(0,1fr)_10rem_5rem] 2xl:items-center 2xl:gap-4">
                        <div class="min-w-0">
                            <span class="text-xs font-medium text-muted-foreground 2xl:hidden">Name</span>
                            <p class="text-sm font-medium">{user.name}</p>
                        </div>
                        <div class="min-w-0">
                            <span class="text-xs font-medium text-muted-foreground 2xl:hidden">Email</span>
                            <p class="break-all text-sm text-muted-foreground">{user.email}</p>
                        </div>
                        <div class="min-w-0">
                            <span class="text-xs font-medium text-muted-foreground 2xl:hidden">Ads</span>
                            <p class="text-sm tabular-nums">{user.ads_count}</p>
                        </div>
                        <div class="min-w-0">
                            <span class="text-xs font-medium text-muted-foreground 2xl:hidden">Images</span>
                            <p class="text-sm tabular-nums">{user.images_count}</p>
                        </div>
                        <div class="min-w-0">
                            <span class="text-xs font-medium text-muted-foreground 2xl:hidden">Platforms</span>
                            <p class="text-sm text-muted-foreground">{user.platforms.length ? user.platforms.join(', ') : '—'}</p>
                        </div>
                        <div class="min-w-0">
                            <span class="text-xs font-medium text-muted-foreground 2xl:hidden">Created</span>
                            <p class="text-sm text-muted-foreground">{formatCreatedAt(user.created_at)}</p>
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                variant="destructive"
                                size="icon"
                                aria-label={`Delete ${user.name}`}
                                title={`Delete ${user.name}`}
                                onclick={() => deleteUser(user)}
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </article>
                {/each}
            {/if}
        </section>
    </div>
</AppLayout>
