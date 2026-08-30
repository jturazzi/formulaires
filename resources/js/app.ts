import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import * as Sentry from '@sentry/vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

// Extend ImportMeta interface for Vite...
declare global {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_SENTRY_DSN?: string;
        readonly VITE_SENTRY_ENVIRONMENT?: string;
        [key: string]: string | boolean | undefined;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Formulaires';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        if (import.meta.env.VITE_SENTRY_DSN) {
            Sentry.init({
                app,
                dsn: import.meta.env.VITE_SENTRY_DSN,
                environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || import.meta.env.MODE,
                integrations: [Sentry.browserTracingIntegration()],
                tracesSampleRate: 0.2,
            });
        }

        const lang = (props.initialPage.props.locale as string) || 'fr';

        app.use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                lang,
                fallbackLang: 'en',
                resolve: async (lang: string) => {
                    // Bundled eagerly (not a lazy per-locale chunk) so translations are already
                    // in memory — loadLanguageAsync below can then resolve them synchronously,
                    // instead of racing the very first render with a network-fetched chunk.
                    const langs = import.meta.glob<{ default: Record<string, string> }>('../../lang/*.json', { eager: true });
                    return langs[`../../lang/${lang}.json`] ?? { default: {} };
                },
            });

        // Wait for the initial translations to be loaded before mounting: otherwise the first
        // render (e.g. the page <Head title>) briefly shows raw/English text until the plugin's
        // background load finishes and forces a re-render.
        loadLanguageAsync(lang).then(() => app.mount(el));
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
