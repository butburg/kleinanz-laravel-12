<script lang="ts">
    import HeadingSmall from '@/components/HeadingSmall.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form, router } from '@inertiajs/svelte';

    type Appendix = {
        id: number;
        platform: string;
        content: string;
    };

    interface Props {
        appendices: Appendix[];
        limit: number;
    }

    let { appendices, limit }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Platforms',
            href: '/appendices',
        },
    ];

    function deleteAppendix(appendixId: number): void {
        router.delete(route('appendices.destroy', appendixId));
    }
</script>

<svelte:head>
    <title>Platforms</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6">
        <p class="text-sm text-muted-foreground">Set the standard text that is added to generated descriptions for each platform.</p>


        {#if appendices.length < limit}
            <section class="space-y-4 rounded-lg border p-4">
                <HeadingSmall title="Add platform" description="Configure up to four platforms." />

                <Form method="post" action={route('appendices.store')} class="space-y-4">
                    {#snippet children({ errors, processing }: BaseFormSnippetProps)}
                        <div class="grid gap-2">
                            <Label for="platform">Platform</Label>
                            <Input id="platform" name="platform" required maxlength={50} placeholder="e.g. Kleinanzeigen" />
                            <InputError message={errors.platform} />
                        </div>

                        <div class="grid gap-2">
                            <Label for="content">Standard appendix</Label>
                            <Textarea id="content" name="content" rows={5} maxlength={5000} placeholder="e.g. Private sale, no returns or warranty." />
                            <InputError message={errors.content} />
                        </div>

                        <Button type="submit" disabled={processing}>Add platform</Button>
                    {/snippet}
                </Form>
            </section>
        {/if}

        <section class="space-y-4">
            <HeadingSmall title="Configured platforms" description="Your platforms you can choose from while creating new ads." />

            {#if appendices.length === 0}
                <p class="text-sm text-muted-foreground">No platforms configured yet.</p>
            {:else}
                <div class="space-y-4">
                    {#each appendices as appendix (appendix.id)}
                        <Form method="put" action={route('appendices.update', appendix.id)} class="space-y-4 rounded-lg border p-4">
                            {#snippet children({ errors, processing }: BaseFormSnippetProps)}
                                <div class="grid gap-2">
                                    <Label for={'platform-' + appendix.id}>Platform</Label>
                                    <Input id={'platform-' + appendix.id} name="platform" required maxlength={50} defaultValue={appendix.platform} />
                                    <InputError message={errors.platform} />
                                </div>

                                <div class="grid gap-2">
                                    <Label for={'content-' + appendix.id}>Standard appendix</Label>
                                    <Textarea id={'content-' + appendix.id} name="content" rows={5} maxlength={5000} value={appendix.content} />
                                    <InputError message={errors.content} />
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>Save</Button>
                                    <Button type="button" variant="destructive" disabled={processing} onclick={() => deleteAppendix(appendix.id)}>Delete</Button>
                                </div>
                            {/snippet}
                        </Form>
                    {/each}
                </div>
            {/if}
        </section>
    </div>
</AppLayout>
