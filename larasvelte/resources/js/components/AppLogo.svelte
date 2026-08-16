<script lang="ts">
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const appName = import.meta.env.VITE_APP_NAME || 'Anzeigen Generator';
</script>

<div class="inline-flex items-center gap-3">
    <AppLogoIcon class="size-9 shrink-0 rounded-full object-cover" />
    <div class="flex items-center">
        <span class="truncate text-sm font-semibold">{appName}</span>
    </div>
</div>
