import {defineConfig} from 'vitest/config';

export default defineConfig({
    test: {
        // localeHelper reads window.navigator.languages at import time
        environment: 'jsdom',
        globals: true,
    },
});
