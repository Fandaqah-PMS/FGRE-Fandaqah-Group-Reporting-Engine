import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';
import { initTheme } from './lib/theme';

createInertiaApp({
    title: (title) => (title ? `${title} · FGRE` : 'FGRE · Fandaqah Group Reporting'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        // Every page except login uses the app layout.
        page.default.layout ??= name.startsWith('Auth/') ? undefined : AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        initTheme(props.initialPage.props.auth?.user?.theme);
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#F15F5A' },
});
