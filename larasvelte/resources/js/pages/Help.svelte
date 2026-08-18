<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Help', href: '/help' },
    ];
</script>

<svelte:head>
    <title>Help</title>
</svelte:head>

<AppLayout {breadcrumbs}>
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6">
        <p class="text-sm text-muted-foreground">Turn an item photo into a clear, reusable classified listing.</p>


        <section class="space-y-3 rounded-lg border p-5">
            <h2 class="text-base font-semibold">From photo to listing</h2>
            <p class="text-sm leading-6 text-muted-foreground">
                📸 Upload a photo of your item. 🧠 AI analyses the image and 📝 writes a ready-to-use ad, with a ✨ title and 💰 price suggestion. Review it, make any changes, copy it, and you are done!
            </p>
        </section>

        <section class="space-y-3 rounded-lg border p-5">
            <h2 class="text-base font-semibold">Keep your work when listings expire</h2>
            <p class="text-sm leading-6 text-muted-foreground">
                Some classified services remove ads after a period of time, taking their text and images with them. Keep your curated listing information here, so it remains organised and ready to reuse or manage after an external listing is gone.
            </p>
        </section>

        <section class="space-y-3 rounded-lg border p-5">
            <h2 class="text-base font-semibold">Move easily between devices</h2>
            <p class="text-sm leading-6 text-muted-foreground">
                Take photos on your phone, then continue creating and refining the ad on your desktop. Your account keeps the process connected, so you can pick up where you left off on the device that suits the task.
            </p>
        </section>
    </div>
</AppLayout>
