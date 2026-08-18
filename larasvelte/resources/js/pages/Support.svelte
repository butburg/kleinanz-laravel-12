<script lang="ts">
    import InputError from '@/components/InputError.svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import type { BreadcrumbItem } from '@/types';
    import { Form } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';

    type SupportFormSnippetProps = {
        errors: Record<string, string | undefined>;
        processing: boolean;
        recentlySuccessful: boolean;
    };

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Support',
            href: '/support',
        },
    ];
</script>

<svelte:head>
    <title>Support</title>
</svelte:head>

<AppLayout breadcrumbs={breadcrumbItems}>
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6">
        <p class="text-sm text-muted-foreground">Send me a message if you need help, have feedback, or want to report a bug.</p>

        <Form method="post" action={route('support.store')} resetOnSuccess class="space-y-6">
            {#snippet children({ errors, processing, recentlySuccessful }: SupportFormSnippetProps)}
                <div class="grid gap-2">
                    <Label for="message">Message</Label>
                    <Textarea id="message" name="message" rows={8} required maxlength={5000} placeholder="Tell me how I can help." />
                    <InputError class="mt-2" message={errors.message} />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>{processing ? 'Sending…' : 'Send message'}</Button>

                    {#if recentlySuccessful}
                        <p class="text-sm text-neutral-600 dark:text-neutral-300" transition:fade={{ duration: 150 }}>Message sent.</p>
                    {/if}
                </div>
            {/snippet}
        </Form>
        <p class="text-xs text-muted-foreground">You can send up to five messages per day.</p>
    </div>
</AppLayout>
