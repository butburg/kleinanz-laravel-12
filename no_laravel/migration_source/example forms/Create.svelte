<script>
    import { useForm, Link } from "@inertiajs/svelte";
    import { onMount } from "svelte";
    import AppLayout from "../../Layouts/AppLayout.svelte";

    let {
        ad = null,
        conditions,
        shippingOptions,
        auth = {},
        flash = {},
        errors = {},
    } = $props();

    let fileInput;
    let uploading = false;
    let localErrors = {};

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || "";
    }

    const fieldError = (name) => $form.errors?.[name] ?? errors?.[name];

    const form = useForm({
        title: ad?.title || "",
        description: ad?.description || "",
        price: ad?.price || "",
        condition: ad?.condition || "Sehr gut",
        shipping: ad?.shipping || "klein",
        images: ad?.images || [],
        promptText: ad?.prompt_text || "",
    });

    onMount(() => {
        if (ad?.images) {
            form.images = ad.images.map((img) => ({
                id: img.id,
                file: null,
                preview: img.thumbnail_url,
                use_cropped: img.use_cropped,
                is_title_image: img.is_title_image,
                is_new: false,
            }));
        }
    });

    function handleImageUpload(e) {
        const files = Array.from(e.target.files);
        localErrors.images = [];

        for (const file of files) {
            // Validate file size (max 20MB)
            if (file.size > 20 * 1024 * 1024) {
                localErrors.images.push(`${file.name} is too large (max 20MB)`);
                continue;
            }

            // Validate file type
            if (
                !["image/jpeg", "image/png", "image/webp"].includes(file.type)
            ) {
                localErrors.images.push(
                    `${file.name} is not a valid image format`,
                );
                continue;
            }

            // Create preview
            const reader = new FileReader();
            reader.onload = (event) => {
                $form.images = [
                    ...$form.images,
                    {
                        id: Date.now() + Math.random(),
                        file: file,
                        preview: event.target.result,
                        use_cropped: false,
                        is_title_image: $form.images.length === 0,
                        is_new: true,
                    },
                ];
            };
            reader.readAsDataURL(file);
        }

        fileInput.value = "";
        localErrors = { ...localErrors };
    }

    async function deleteImage(imageId) {
        const target = $form.images.find((img) => img.id === imageId);
        if (!target) {
            return;
        }

        if (!target.is_new) {
            const response = await fetch(`/ad-images/${imageId}`, {
                method: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                    Accept: "application/json",
                },
            });

            if (!response.ok) {
                localErrors.images = ["Failed to delete image."];
                return;
            }
        }

        $form.images = $form.images.filter((img) => img.id !== imageId);

        // Ensure at least one is title image
        if (
            $form.images.length > 0 &&
            !$form.images.some((img) => img.is_title_image)
        ) {
            $form.images[0].is_title_image = true;
        }
    }

    async function setTitleImage(imageId) {
        $form.images = $form.images.map((img) => ({
            ...img,
            is_title_image: img.id === imageId,
        }));

        const target = $form.images.find((img) => img.id === imageId);
        if (target && !target.is_new) {
            await fetch(`/ad-images/${imageId}/set-title`, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                    Accept: "application/json",
                },
            });
        }
    }

    async function toggleCropped(imageId) {
        $form.images = $form.images.map((img) =>
            img.id === imageId
                ? { ...img, use_cropped: !img.use_cropped }
                : img,
        );

        const target = $form.images.find((img) => img.id === imageId);
        if (target && !target.is_new) {
            await fetch(`/ad-images/${imageId}/preference`, {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                    Accept: "application/json",
                },
                body: JSON.stringify({ use_cropped: target.use_cropped }),
            });
        }
    }

    async function uploadNewImages(adId) {
        const pending = $form.images.filter((img) => img.is_new && img.file);
        if (pending.length === 0) {
            return;
        }

        for (const img of pending) {
            const formData = new FormData();
            formData.append("image", img.file);

            const response = await fetch(`/ads/${adId}/images`, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": getCsrfToken(),
                    Accept: "application/json",
                },
                body: formData,
            });

            if (!response.ok) {
                localErrors.images = ["Failed to upload image."];
                continue;
            }

            const uploaded = await response.json();
            const updated = $form.images.map((item) =>
                item.id === img.id
                    ? {
                          id: uploaded.id,
                          file: null,
                          preview: uploaded.thumbnail_url,
                          use_cropped: uploaded.use_cropped,
                          is_title_image: img.is_title_image,
                          is_new: false,
                      }
                    : item,
            );

            $form.images = updated;

            if (img.is_title_image) {
                await fetch(`/ad-images/${uploaded.id}/set-title`, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": getCsrfToken(),
                        Accept: "application/json",
                    },
                });
            }
        }
    }

    async function generateText() {
        if (!ad?.id) {
            localErrors.generate = "Save the ad before generating text.";
            return;
        }

        const titleImg = $form.images.find((img) => img.is_title_image);
        if (!titleImg || titleImg.is_new) {
            localErrors.generate =
                "Upload a title image before generating text.";
            return;
        }

        uploading = true;

        const response = await fetch("/api/ads/generate-text", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": getCsrfToken(),
            },
            body: JSON.stringify({
                ad_id: ad.id,
                prompt_text: $form.promptText,
            }),
        });

        const data = await response.json();
        if (data.error) {
            localErrors.generate = data.error;
        } else {
            $form.title = data.title || $form.title;
            $form.description = data.description || $form.description;
            $form.price = data.price || $form.price;
            $form.condition = data.condition || $form.condition;
            $form.shipping = data.shipping || $form.shipping;
        }

        uploading = false;
    }

    async function submit() {
        uploading = true;

        if (ad?.id) {
            await uploadNewImages(ad.id);

            $form.patch(`/ads/${ad.id}`, {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    uploading = false;
                },
            });
        } else {
            $form.post("/ads", {
                onFinish: () => {
                    uploading = false;
                },
            });
        }
    }
</script>

<AppLayout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
            <h1 class="text-3xl font-bold text-slate-900">
                {ad ? "Edit Ad" : "Create New Ad"}
            </h1>
            <Link
                href="/ads"
                class="px-3 py-1.5 text-sm rounded-full border border-slate-300/70 bg-white/80 hover:bg-white transition"
            >
                Back to Ads
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Image Section -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-6">
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                    >
                        Images
                    </h2>

                    <!-- Image Upload -->
                    <div class="mb-4">
                        <input
                            bind:this={fileInput}
                            type="file"
                            multiple
                            accept="image/*"
                            on:change={handleImageUpload}
                            class="hidden"
                        />
                        <button
                            on:click={() => fileInput.click()}
                            class="w-full px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-center hover:border-blue-500 transition"
                        >
                            <span class="text-gray-600 dark:text-gray-400"
                                >Click to upload images</span
                            >
                        </button>
                    </div>

                    {#if localErrors.images?.length}
                        <div
                            class="bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 p-3 rounded mb-4 text-sm"
                        >
                            {#each localErrors.images as error}
                                <p>{error}</p>
                            {/each}
                        </div>
                    {/if}

                    <!-- Image Gallery -->
                    <div class="space-y-3">
                        {#each $form.images as image (image.id)}
                            <div
                                class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden"
                            >
                                <img
                                    src={image.preview}
                                    alt="Preview"
                                    class="w-full h-32 object-cover"
                                />
                                <div
                                    class="p-2 space-y-2 bg-gray-50 dark:bg-slate-700"
                                >
                                    <label
                                        class="flex items-center gap-2 cursor-pointer"
                                    >
                                        <input
                                            type="radio"
                                            name="titleImage"
                                            checked={image.is_title_image}
                                            on:change={() =>
                                                setTitleImage(image.id)}
                                        />
                                        <span
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                            >Title Image</span
                                        >
                                    </label>

                                    {#if image.use_cropped !== undefined}
                                        <label
                                            class="flex items-center gap-2 cursor-pointer"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={image.use_cropped}
                                                on:change={() =>
                                                    toggleCropped(image.id)}
                                            />
                                            <span
                                                class="text-sm text-gray-700 dark:text-gray-300"
                                                >Use Cropped</span
                                            >
                                        </label>
                                    {/if}

                                    <button
                                        on:click={() => deleteImage(image.id)}
                                        class="w-full px-2 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600 transition"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                        {$form.images.length} of 10 images
                    </p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="lg:col-span-2">
                <div
                    class="bg-white dark:bg-slate-800 rounded-lg shadow p-6 space-y-6"
                >
                    <!-- Title -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Title *
                        </label>
                        <input
                            bind:value={$form.title}
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:outline-none focus:border-blue-500"
                            maxlength="80"
                            placeholder="Ad title"
                        />
                        {#if fieldError("title")}
                            <p class="text-red-500 text-sm mt-1">
                                {fieldError("title")}
                            </p>
                        {/if}
                        <p
                            class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                        >
                            {$form.title.length} / 80
                        </p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Description *
                        </label>
                        <textarea
                            bind:value={$form.description}
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:outline-none focus:border-blue-500"
                            rows="5"
                            minlength="50"
                            maxlength="1000"
                            placeholder="Describe your item..."
                        />
                        {#if fieldError("description")}
                            <p class="text-red-500 text-sm mt-1">
                                {fieldError("description")}
                            </p>
                        {/if}
                        <p
                            class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                        >
                            {$form.description.length} / 1000
                        </p>
                    </div>

                    <!-- Price -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Price (€) *
                        </label>
                        <input
                            bind:value={$form.price}
                            type="number"
                            min="0"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:outline-none focus:border-blue-500"
                            placeholder="0"
                        />
                        {#if fieldError("price")}
                            <p class="text-red-500 text-sm mt-1">
                                {fieldError("price")}
                            </p>
                        {/if}
                    </div>

                    <!-- Condition -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Condition *
                        </label>
                        <select
                            bind:value={$form.condition}
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:outline-none focus:border-blue-500"
                        >
                            {#each conditions as cond}
                                <option value={cond}>{cond}</option>
                            {/each}
                        </select>
                        {#if fieldError("condition")}
                            <p class="text-red-500 text-sm mt-1">
                                {fieldError("condition")}
                            </p>
                        {/if}
                    </div>

                    <!-- Shipping -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Shipping *
                        </label>
                        <select
                            bind:value={$form.shipping}
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:outline-none focus:border-blue-500"
                        >
                            {#each shippingOptions as opt}
                                <option value={opt}>{opt}</option>
                            {/each}
                        </select>
                        {#if fieldError("shipping")}
                            <p class="text-red-500 text-sm mt-1">
                                {fieldError("shipping")}
                            </p>
                        {/if}
                    </div>

                    <!-- Prompt Text -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Custom Prompt (Optional)
                        </label>
                        <textarea
                            bind:value={$form.promptText}
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:outline-none focus:border-blue-500"
                            rows="3"
                            maxlength="500"
                            placeholder="Give AI tips for description..."
                        />
                    </div>

                    {#if localErrors.generate}
                        <div
                            class="bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 p-3 rounded text-sm"
                        >
                            {localErrors.generate}
                        </div>
                    {/if}

                    <!-- Action Buttons -->
                    <div
                        class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-600"
                    >
                        <button
                            on:click={generateText}
                            disabled={!ad?.id ||
                                !$form.images.length ||
                                uploading}
                            class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 disabled:bg-gray-400 transition"
                        >
                            {uploading
                                ? "Processing..."
                                : "✨ Generate with AI"}
                        </button>

                        <button
                            on:click={submit}
                            disabled={uploading}
                            class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:bg-gray-400 transition"
                        >
                            {uploading
                                ? "Saving..."
                                : ad
                                  ? "Update Ad"
                                  : "Create Ad"}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AppLayout>
