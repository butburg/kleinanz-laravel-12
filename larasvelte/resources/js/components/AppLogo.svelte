<script lang="ts">
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const appName = import.meta.env.VITE_APP_NAME || 'Anzeigen Generator';
</script>

<div class="inline-flex items-center gap-3">
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary">
        <AppLogoIcon class="size-5 fill-current text-white! dark:text-black!" />
    </div>
    <div class="flex items-center">
        <span class="truncate text-sm font-semibold">{appName}</span>
    </div>
</div>
