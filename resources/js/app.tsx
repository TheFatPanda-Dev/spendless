import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import RuntimeLoadWarning from '@/components/runtime-load-warning';
import SitePrefsBanner from '@/components/site-prefs-banner';
import '../css/app.css';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'SpendLess';

createInertiaApp({
    title: (title) => {
        if (!title || title === appName) {
            return appName;
        }

        return `${title} - ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <App {...props} />
                <SitePrefsBanner />
                <RuntimeLoadWarning />
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
