import { createInertiaApp } from '@inertiajs/react';
import ReactDOMServer from 'react-dom/server';

createInertiaApp({
    title: (title) => title ? `${title} — ClashMonitor` : 'ClashMonitor',
    page: JSON.parse(process.argv[2] || '{}'),
    render: ReactDOMServer.renderToString,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        return pages[`./Pages/${name}.jsx`]();
    },
    setup({ App, props }) {
        return <App {...props} />;
    },
});
