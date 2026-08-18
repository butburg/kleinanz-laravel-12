<script lang="ts">
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const appName = import.meta.env.VITE_APP_NAME || 'Anzeigen Generator';
</script>

<div class="inline-flex items-center gap-3 group-data-[collapsible=icon]:gap-0">
    <AppLogoIcon class="size-9 shrink-0 rounded-full object-contain p-0.5" />
    <div class="flex items-center group-data-[collapsible=icon]:hidden">
        <span class="truncate text-sm font-semibold">{appName}</span>
    </div>
</div>
