<script lang="ts">
    import HeadingSmall from '@/components/HeadingSmall.svelte';
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
    <div class="mx-auto w-full max-w-2xl space-y-6 px-4 py-6">
        <HeadingSmall title="Support" description="Send me a message if you need help. You can send up to 5 messages per day." />

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
    </div>
</AppLayout>
