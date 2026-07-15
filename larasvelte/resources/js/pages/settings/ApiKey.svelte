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
    import { Form, router, page } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';
    import Loader2 from '@lucide/svelte/icons/loader-2';

    interface Props {
        maskedApiKey?: string | null;
        status?: string;
        useTestMode?: boolean;
    }

    let { maskedApiKey, status, useTestMode = false }: Props = $props();

    // Reactive state that updates when page props change
    let testModeEnabled = $state(false);
    $effect(() => {
        testModeEnabled = useTestMode;
    });

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'OpenAI API Key',
            href: '/settings/api-key',
        },
    ];

    let apiKeyInput = $state(null as unknown as HTMLInputElement);
    let isAddingKey = $state(false);
    $effect(() => {
        if (!maskedApiKey) {
            isAddingKey = true;
        }
    });
    let showDeleteDialog = $state(false);
    let testMode = $state(false);
    let testLoading = $state(false);
    let testResult = $state<{ success: boolean; message: string } | null>(null);
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

            {#if maskedApiKey && !isAddingKey}
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium">Test API Key</h3>
                        <p class="text-xs text-neutral-600 dark:text-neutral-400">Verify your API key is working correctly</p>
                    </div>

                    <Button
                        variant="outline"
                        disabled={testLoading}
                        onclick={async () => {
                            testLoading = true;
                            testResult = null;
                            try {
                                const response = await fetch(route('api-key.test'), {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                    },
                                });

                                const data = await response.json();
                                testResult = {
                                    success: response.ok,
                                    message: data.message || (response.ok ? 'API key is valid!' : 'API key test failed'),
                                };
                            } catch (error) {
                                testResult = {
                                    success: false,
                                    message: 'Error testing API key: ' + (error instanceof Error ? error.message : 'Unknown error'),
                                };
                            } finally {
                                testLoading = false;
                            }
                        }}
                    >
                        {#if testLoading}
                            <Loader2 class="mr-2 h-4 w-4 animate-spin" />
                            Testing...
                        {:else}
                            Test Connection
                        {/if}
                    </Button>

                    {#if testResult}
                        <div
                            class={`rounded-lg border p-3 transition-all duration-200 ${
                                testResult.success
                                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200'
                                    : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                            }`}
                            transition:fade={{ duration: 150 }}
                        >
                            <p class="text-sm">{testResult.message}</p>
                        </div>
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

            <div class="space-y-4 border-t pt-6">
                <div>
                    <h3 class="text-sm font-medium">Test Mode</h3>
                    <p class="text-xs text-neutral-600 dark:text-neutral-400">Use mock AI model for testing without API calls</p>
                </div>

                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input
                            type="checkbox"
                            checked={testModeEnabled}
                            class="h-4 w-4 rounded border-neutral-300"
                            onchange={(e) => {
                                const checked = (e.currentTarget as HTMLInputElement).checked;
                                testModeEnabled = checked;
                                router.patch(
                                    route('profile.update'),
                                    { use_test_mode: checked },
                                    {
                                        preserveScroll: true,
                                        onError: () => {
                                            // Revert on error
                                            testModeEnabled = !checked;
                                        }
                                    },
                                );
                            }}
                        />
                        <div>
                            <p class="text-sm font-medium">Enable Test Mode</p>
                            <p class="text-xs text-neutral-500">
                                Fake OpenAI model generates example content – no API costs, ideal for testing.
                            </p>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </SettingsLayout>
</AppLayout>
