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

        {#if users.length === 0}
            <section class="rounded-lg border px-4 py-8">
                <p class="text-sm text-muted-foreground">No users found.</p>
            </section>
        {:else}
            <section class="grid gap-4 lg:grid-cols-2">
                {#each users as user (user.id)}
                    <article class="flex min-w-0 flex-col gap-5 rounded-lg border p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold">{user.name}</p>
                                <p class="break-all text-sm text-muted-foreground">{user.email}</p>
                            </div>
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

                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Ads</dt>
                                <dd class="mt-1 font-medium tabular-nums">{user.ads_count}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Images</dt>
                                <dd class="mt-1 font-medium tabular-nums">{user.images_count}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-muted-foreground">Platforms</dt>
                                {#if user.platforms.length}
                                    <dd class="mt-2 flex flex-wrap gap-2">
                                        {#each user.platforms as platform (platform)}
                                            <span class="rounded-md bg-muted px-2 py-1 text-xs font-medium">{platform}</span>
                                        {/each}
                                    </dd>
                                {:else}
                                    <dd class="mt-1 text-muted-foreground">No platforms configured.</dd>
                                {/if}
                            </div>
                            <div class="col-span-2">
                                <dt class="text-muted-foreground">Registered</dt>
                                <dd class="mt-1">{formatCreatedAt(user.created_at)}</dd>
                            </div>
                        </dl>
                    </article>
                {/each}
            </section>
        {/if}
    </div>
</AppLayout>
