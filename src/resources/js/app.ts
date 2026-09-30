import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

// Puts the display font in the Vite manifest so the root view can preload it (Vite::asset).
import.meta.glob('../../node_modules/@fontsource/lilita-one/files/lilita-one-latin-400-normal.woff2');

const appName = import.meta.env.VITE_APP_NAME || 'Clash Commons';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        delay: 250,
    },
});
