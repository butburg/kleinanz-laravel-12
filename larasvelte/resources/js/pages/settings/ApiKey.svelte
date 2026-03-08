<script lang="ts">
    import HeadingSmall from '@/components/HeadingSmall.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';
    import { type BreadcrumbItem } from '@/types';
    import { Form, page } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';

    interface Props {
        maskedApiKey?: string | null;
        status?: string;
    }

    let { maskedApiKey, status }: Props = $props();

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'OpenAI API Key',
            href: '/settings/api-key',
        },
    ];

    let apiKeyInput = $state(null as unknown as HTMLInputElement);
    let isAddingKey = $state(!maskedApiKey);
    let showDeleteDialog = $state(false);
</script>

<svelte:head>
    <title>API Key Settings</title>
</svelte:head>

<AppLayout breadcrumbs={breadcrumbItems}>
    <SettingsLayout>
        <div class="space-y-6">
            <HeadingSmall
                title="OpenAI API Key"
                description={maskedApiKey
                    ? 'Manage your OpenAI API key for text generation'
                    : 'Add your OpenAI API key to use AI-powered text generation for your ads'}
            />

            {#if maskedApiKey}
                <div class="space-y-4">
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Your API Key</Label>
                            <div class="rounded bg-neutral-100 p-3 font-mono text-sm dark:bg-neutral-800">
                                {maskedApiKey}
                            </div>
                            <p class="text-xs text-neutral-600 dark:text-neutral-400">
                                Your full API key is encrypted and only shown on first save.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            onclick={() => {
                                isAddingKey = true;
                                setTimeout(() => apiKeyInput?.focus(), 0);
                            }}>Update API Key</Button
                        >

                        <Dialog open={showDeleteDialog} onOpenChange={(open) => (showDeleteDialog = open)}>
                            <DialogTrigger>
                                <Button variant="destructive">Remove Key</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    method="delete"
                                    action={route('api-key.destroy')}
                                    resetOnSuccess
                                    onSuccess={() => (showDeleteDialog = false)}
                                >
                                    {#snippet children({ processing })}
                                        <DialogHeader class="space-y-3">
                                            <DialogTitle>Remove API Key?</DialogTitle>
                                            <DialogDescription>
                                                Your OpenAI API key will be removed from your account. You'll use the default mock generator for text generation until you add a new key.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <DialogFooter class="gap-2">
                                            <DialogClose>
                                                <Button variant="secondary">Cancel</Button>
                                            </DialogClose>
                                            <Button type="submit" variant="destructive" disabled={processing}>
                                                Remove Key
                                            </Button>
                                        </DialogFooter>
                                    {/snippet}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>

                    {#if status}
                        <p class="text-sm text-green-600 dark:text-green-400" transition:fade={{ duration: 150 }}>
                            {status}
                        </p>
                    {/if}
                </div>
            {/if}

            {#if isAddingKey}
                <div class="space-y-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                    <HeadingSmall
                        title="Add or Update API Key"
                        description="Get your API key from OpenAI: Settings → API Keys → Create new secret key"
                    />

                    <Form
                        method="post"
                        action={route('api-key.update')}
                        options={{ preserveScroll: true }}
                        onError={(errors) => {
                            if (errors.openai_api_key) {
                                apiKeyInput?.focus();
                            }
                        }}
                        resetOnSuccess
                        onSuccess={() => {
                            isAddingKey = false;
                        }}
                        class="space-y-4"
                    >
                        {#snippet children({ errors, processing, recentlySuccessful })}
                            <div class="grid gap-2">
                                <Label for="openai_api_key">API Key</Label>
                                <Input
                                    ref={apiKeyInput}
                                    name="openai_api_key"
                                    type="password"
                                    class="mt-1 block w-full font-mono"
                                    placeholder="sk-..."
                                    required
                                />
                                <InputError message={errors.openai_api_key} />
                                <p class="text-xs text-neutral-600 dark:text-neutral-400">
                                    Your API key will be securely encrypted and stored. Never share this key with anyone.
                                </p>
                            </div>

                            <div class="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    {maskedApiKey ? 'Update Key' : 'Save Key'}
                                </Button>

                                {#if maskedApiKey}
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onclick={() => {
                                            isAddingKey = false;
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                {/if}

                                {#if recentlySuccessful}
                                    <p class="text-sm text-green-600 dark:text-green-400" transition:fade={{ duration: 150 }}>
                                        Saved successfully.
                                    </p>
                                {/if}
                            </div>
                        {/snippet}
                    </Form>
                </div>
            {/if}
        </div>
    </SettingsLayout>
</AppLayout>
