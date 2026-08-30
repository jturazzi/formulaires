import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import * as Sentry from '@sentry/vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
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

        app.use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                lang: (props.initialPage.props.locale as string) || 'fr',
                fallbackLang: 'en',
                resolve: async (lang: string) => {
                    const langs = import.meta.glob<{ default: Record<string, string> }>('../../lang/*.json');
                    const loader = langs[`../../lang/${lang}.json`];
                    return loader ? await loader() : { default: {} };
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
