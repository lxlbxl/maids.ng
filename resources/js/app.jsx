import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ThemeProvider } from '@/Components/ThemeProvider';
import { captureFirstTouch } from '@/lib/attribution';
import { track } from '@/lib/track';

const appName = import.meta.env.VITE_APP_NAME || 'Maids.ng';

// Snapshot first-touch attribution before anything else touches the URL.
captureFirstTouch();

// Every page a visitor opens, including the ones they leave without acting on.
// Without this the traffic panel counted only sessions that reached a quiz,
// which understates real visits and hides the top of the funnel entirely.
track('page_view');
router.on('navigate', () => track('page_view'));

createInertiaApp({
    title: (title) => title ? `${title} — ${appName}` : appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <ThemeProvider>
                <App {...props} />
            </ThemeProvider>
        );
    },
    progress: {
        color: '#0F5556',
    },
});
