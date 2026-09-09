import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ThemeProvider } from '@/Components/ThemeProvider';
import { captureFirstTouch } from '@/lib/attribution';

const appName = import.meta.env.VITE_APP_NAME || 'Maids.ng';

// Snapshot first-touch attribution before anything else touches the URL.
captureFirstTouch();

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
