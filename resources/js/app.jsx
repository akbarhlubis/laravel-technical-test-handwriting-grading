import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} - TingXie HERO` : 'TingXie HERO'),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
