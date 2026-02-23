<script lang="ts">
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form } from '@inertiajs/svelte';

    interface Props {
        options: {
            conditions: string[];
            shipping: string[];
            statuses: string[];
            limits: {
                title: number;
                description: number;
                images: number;
                prompt: number;
            };
        };
    }

    let { options }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Ads',
            href: '/ads',
        },
        {
            title: 'Create',
            href: '/ads/create',
        },
    ];

    let titleValue = $state('');
    let descriptionValue = $state('');
    let promptValue = $state('');
    let promptCharacters = $state(0);
    let selectedImageNames = $state<string[]>([]);
    let generateHint = $state<string | null>(null);

    function onImageSelection(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;
        selectedImageNames = Array.from(input.files ?? []).map((file) => file.name);
    }

    function showGenerateHint(): void {
        generateHint = 'Generate is prepared in UI and will be connected in the next step.';
    }
</script>

<svelte:head>
    <title>Create Ad</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-4 px-4 pt-4">
        <h1 class="text-2xl font-semibold">Create Ad</h1>

        <Form method="post" action={route('ads.store')} class="space-y-4">
            {#snippet children({ errors, processing }: BaseFormSnippetProps)}
                <section class="space-y-3 rounded-md border p-4">
                    <h2 class="text-lg font-medium">Images</h2>

                    <div class="space-y-2">
                        <Label for="images">Upload images</Label>
                        <label
                            for="images"
                            class="block cursor-pointer rounded-md border-2 border-dashed p-4 text-center text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
                        >
                            Click to upload images
                        </label>
                        <Input id="images" name="images[]" type="file" multiple accept="image/*" class="hidden" onchange={onImageSelection} />
                        <div class="text-xs text-muted-foreground">
                            {selectedImageNames.length} / {options.limits.images} selected
                        </div>
                        {#if selectedImageNames.length > 0}
                            <div class="max-h-24 space-y-1 overflow-auto rounded-md border p-2 text-xs text-muted-foreground">
                                {#each selectedImageNames as fileName (fileName)}
                                    <div>{fileName}</div>
                                {/each}
                            </div>
                        {/if}
                        <InputError message={errors.images} />
                    </div>
                </section>

                <section class="space-y-3 rounded-md border p-4">
                    <h2 class="text-lg font-medium">Details</h2>

                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" name="title" required value={titleValue} oninput={(event) => (titleValue = (event.currentTarget as HTMLInputElement).value)} />
                        <div class="text-xs text-muted-foreground">{titleValue.length} / {options.limits.title}</div>
                        <InputError message={errors.title} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            required
                            rows="6"
                            class="rounded-md border p-2"
                            oninput={(event) => (descriptionValue = (event.currentTarget as HTMLTextAreaElement).value)}
                        >{descriptionValue}</textarea>
                        <div class="text-xs text-muted-foreground">{descriptionValue.length} / {options.limits.description}</div>
                        <InputError message={errors.description} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="prompt_text">Prompt (optional)</Label>
                        <textarea
                            id="prompt_text"
                            name="prompt_text"
                            rows="3"
                            class="rounded-md border p-2"
                            oninput={(event) => {
                                promptValue = (event.currentTarget as HTMLTextAreaElement).value;
                                promptCharacters = promptValue.length;
                            }}
                        >{promptValue}</textarea>
                        <div class="text-xs text-muted-foreground">{promptCharacters} / {options.limits.prompt}</div>
                        <InputError message={errors.prompt_text} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="price">Price</Label>
                        <Input id="price" name="price" type="number" min="0" required />
                        <InputError message={errors.price} />
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div class="grid gap-2">
                            <Label for="condition">Condition</Label>
                            <select id="condition" name="condition" class="rounded-md border p-2" required>
                                {#each options.conditions as condition (condition)}
                                    <option value={condition}>{condition}</option>
                                {/each}
                            </select>
                            <InputError message={errors.condition} />
                        </div>

                        <div class="grid gap-2">
                            <Label for="shipping">Shipping</Label>
                            <select id="shipping" name="shipping" class="rounded-md border p-2" required>
                                {#each options.shipping as shipping (shipping)}
                                    <option value={shipping}>{shipping}</option>
                                {/each}
                            </select>
                            <InputError message={errors.shipping} />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select id="status" name="status" class="rounded-md border p-2">
                            {#each options.statuses as status (status)}
                                <option value={status}>{status}</option>
                            {/each}
                        </select>
                        <InputError message={errors.status} />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" disabled={processing}>Save Ad</Button>
                        <Button type="button" variant="outline" onclick={showGenerateHint} disabled={selectedImageNames.length === 0}>Generate</Button>
                    </div>
                    {#if generateHint}
                        <p class="text-xs text-muted-foreground">{generateHint}</p>
                    {/if}
                </section>
            {/snippet}
        </Form>
    </div>
</AppLayout>
