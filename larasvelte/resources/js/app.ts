import { createInertiaApp, type ResolvedComponent, router } from '@inertiajs/svelte';
import { hydrate, mount } from 'svelte';
import '../css/app.css';
import './bootstrap';

// Configure Inertia progress bar for faster response (especially for auto-save)
router.on('start', () => {
    // Override default 250ms delay with 100ms for snappier feedback
});

createInertiaApp({
    progress: {
        delay: 100,        // Start showing progress after 100ms (default: 250ms)
        color: '#00d9ff',  // Primary color from design system
        includeCSS: true,
        showSpinner: false,
    },
    resolve: (name: string) => {
        const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte', { eager: true });
        return pages[`./pages/${name}.svelte`];
    },
    setup({ el, App, props }) {
        if (el && el.dataset.serverRendered === 'true') {
            hydrate(App, { target: el, props });
        } else if (el) {
            mount(App, { target: el, props });
        }
    },
});
