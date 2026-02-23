<script lang="ts">
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form } from '@inertiajs/svelte';

    type Ad = {
        id: string;
        title: string;
        description: string;
        price: number;
        condition: string;
        shipping: string;
        status: string;
        prompt_text?: string | null;
        images: Array<{
            id: number;
            original_name: string;
            url: string;
            is_title: boolean;
            position: number;
        }>;
    };

    interface Props {
        ad: Ad;
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

    let { ad, options }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Ads',
            href: '/ads',
        },
        {
            title: 'Edit',
            href: '/ads',
        },
    ];

    let titleValue = $state('');
    let descriptionValue = $state('');
    let promptValue = $state('');
    let promptCharacters = $state(0);
    let selectedImageNames = $state<string[]>([]);

    function onImageSelection(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;
        selectedImageNames = Array.from(input.files ?? []).map((file) => file.name);
    }

    $effect(() => {
        titleValue = ad.title;
        descriptionValue = ad.description;
        promptValue = ad.prompt_text ?? '';
        promptCharacters = promptValue.length;
    });
</script>

<svelte:head>
    <title>Edit Ad</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="space-y-4 px-4 pt-4">
        <h1 class="text-2xl font-semibold">Edit Ad</h1>

        <section class="space-y-3 rounded-md border p-4">
            <h2 class="text-lg font-medium">Images</h2>

            <Form method="post" action={route('ads.images.store', ad.id)} class="space-y-2">
                {#snippet children({ errors, processing }: BaseFormSnippetProps)}
                    <div class="space-y-2">
                        <Label for="new_images">Upload images</Label>
                        <label
                            for="new_images"
                            class="block cursor-pointer rounded-md border-2 border-dashed p-4 text-center text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
                        >
                            Click to upload images
                        </label>
                        <Input id="new_images" name="images[]" type="file" multiple accept="image/*" class="hidden" onchange={onImageSelection} />
                        <div class="text-xs text-muted-foreground">
                            {selectedImageNames.length} selected (max {options.limits.images} total per ad)
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
                    <Button type="submit" disabled={processing}>Add selected images</Button>
                {/snippet}
            </Form>

            {#if ad.images.length === 0}
                <p class="text-sm text-muted-foreground">No images uploaded yet.</p>
            {:else}
                <div class="grid gap-3 md:grid-cols-2" data-test="image-grid">
                    {#each ad.images as image (image.id)}
                        <div class="space-y-2 rounded-md border p-3" data-test={`image-card-${image.id}`}>
                            <img src={image.url} alt={image.original_name} class="h-40 w-full rounded-md object-cover" data-test={`image-preview-${image.id}`} />
                            <p class="text-sm">{image.original_name}</p>
                            {#if image.is_title}
                                <p class="text-sm font-medium text-green-700">Title image</p>
                            {:else}
                                <Form method="patch" action={route('ads.images.set-title', [ad.id, image.id])}>
                                    <Button type="submit" size="sm" data-test={`set-title-${image.id}`}>Set as title</Button>
                                </Form>
                            {/if}
                            <Form method="delete" action={route('ads.images.destroy', [ad.id, image.id])}>
                                <Button type="submit" size="sm" variant="outline">Delete image</Button>
                            </Form>
                        </div>
                    {/each}
                </div>
            {/if}
        </section>

        <section class="space-y-3 rounded-md border p-4">
            <h2 class="text-lg font-medium">Details</h2>
            <Form method="patch" action={route('ads.update', ad.id)} class="space-y-4">
                {#snippet children({ errors, processing }: BaseFormSnippetProps)}
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
                        <Input id="price" name="price" type="number" min="0" required defaultValue={ad.price} />
                        <InputError message={errors.price} />
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div class="grid gap-2">
                            <Label for="condition">Condition</Label>
                            <select id="condition" name="condition" class="rounded-md border p-2" required>
                                {#each options.conditions as condition (condition)}
                                    <option value={condition} selected={condition === ad.condition}>{condition}</option>
                                {/each}
                            </select>
                            <InputError message={errors.condition} />
                        </div>

                        <div class="grid gap-2">
                            <Label for="shipping">Shipping</Label>
                            <select id="shipping" name="shipping" class="rounded-md border p-2" required>
                                {#each options.shipping as shipping (shipping)}
                                    <option value={shipping} selected={shipping === ad.shipping}>{shipping}</option>
                                {/each}
                            </select>
                            <InputError message={errors.shipping} />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select id="status" name="status" class="rounded-md border p-2" required>
                            {#each options.statuses as status (status)}
                                <option value={status} selected={status === ad.status}>{status}</option>
                            {/each}
                        </select>
                        <InputError message={errors.status} />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" disabled={processing}>Update Ad</Button>
                    </div>
                {/snippet}
            </Form>

            <div class="flex flex-wrap gap-2">
                <Form method="post" action={route('ads.generate', ad.id)}>
                    {#snippet children({ errors, processing }: BaseFormSnippetProps)}
                        <input type="hidden" name="prompt_text" value={promptValue} />
                        <Button type="submit" variant="outline" disabled={ad.images.length === 0 || processing}>Generate</Button>
                        <InputError message={errors.generate} />
                    {/snippet}
                </Form>

                <Form method="delete" action={route('ads.destroy', ad.id)}>
                    <Button type="submit" variant="destructive">Delete Ad</Button>
                </Form>
            </div>
        </section>
    </div>
</AppLayout>
