import { defineConfig } from 'vitest/config';

// Standalone test config — intentionally without the Laravel/Tailwind build
// plugins. esbuild handles JSX with the automatic runtime, so components and
// tests need no explicit React import (matching the app's build behaviour).
export default defineConfig({
    esbuild: {
        jsx: 'automatic',
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test/setup.js'],
        include: ['resources/js/**/*.test.{js,jsx}'],
        css: false,
    },
});
