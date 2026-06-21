import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

import { applyAppBranding } from '@/composables/useAppBranding';
import { initTheme } from '@/composables/useTheme';
import { setAppTimezone } from '@/lib/timezone';
import type { AppBranding } from '@/types/branding';

initTheme();

const appName = import.meta.env.VITE_APP_NAME || 'HRM';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const branding = props.initialPage.props.branding as AppBranding;
        const timezone = props.initialPage.props.timezone as string | undefined;

        if (timezone) {
            setAppTimezone(timezone);
        }

        if (branding) {
            applyAppBranding(branding);
        }

        router.on('navigate', (event) => {
            const nextBranding = event.detail.page.props.branding as AppBranding | undefined;
            const nextTimezone = event.detail.page.props.timezone as string | undefined;

            if (nextTimezone) {
                setAppTimezone(nextTimezone);
            }

            if (nextBranding) {
                applyAppBranding(nextBranding);
            }
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#d97706',
    },
});
