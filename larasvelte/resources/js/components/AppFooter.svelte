<script lang="ts">
    import { Heart, IceCreamBowl } from 'lucide-svelte';
</script>

<footer class="border-t border-border/70 px-4 py-8 text-sm text-muted-foreground">
    <div class="flex flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
        <p class="text-xs">Weedy Classifieds v2.x</p>

        <div class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-xs sm:justify-end">
            <span class="inline-flex items-center gap-1">
                Made with <Heart class="size-3 fill-current text-rose-500" aria-hidden="true" /> by E. Wiese
            </span>
            <span aria-hidden="true">·</span>
            <a
                href="https://ko-fi.com/butburg"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 font-medium text-foreground underline decoration-muted-foreground/50 underline-offset-4 transition-colors hover:text-rose-600 hover:decoration-rose-600 dark:hover:text-rose-400"
            >
                <IceCreamBowl class="size-3.5" aria-hidden="true" />
                Buy me some ice cream
            </a>
        </div>
    </div>
</footer>
