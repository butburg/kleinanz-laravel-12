<script lang="ts">
    import { IceCreamBowl, Smile } from 'lucide-svelte';
</script>

<footer class="border-t border-border/70 px-4 py-8 text-sm text-muted-foreground">
    <div class="flex flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
        <p class="text-xs">Weedy Classifieds v2.x</p>

        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-xs sm:justify-end">
            <a
                href="https://ko-fi.com/butburg"
                target="_blank"
                rel="noopener"
                aria-label="Made with Love by E. Wiese"
                class="group inline-flex items-center gap-2 text-foreground/70 transition-colors hover:text-foreground"
            >
                <span class="inline-flex size-8 items-center justify-center rounded-full bg-muted transition-colors group-hover:bg-accent">
                    <Smile class="size-5 rotate-180 transition-transform duration-300 group-hover:rotate-[225deg]" aria-hidden="true" />
                </span>
                <span class="text-left">
                    <span class="block font-medium underline decoration-muted-foreground/50 underline-offset-4 transition-colors group-hover:decoration-foreground">
                        Made with Love
                    </span>
                    <span class="block text-muted-foreground">by E. Wiese</span>
                </span>
            </a>
            <span aria-hidden="true">·</span>
            <a
                href="https://ko-fi.com/butburg"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 font-medium text-foreground underline decoration-muted-foreground/50 underline-offset-4 transition-colors hover:decoration-foreground"
            >
                <IceCreamBowl class="size-3.5" aria-hidden="true" />
                Buy me some ice cream
            </a>
        </div>
    </div>
</footer>
