import {defineConfig} from 'vitest/config';

/**
 * Vitest is pinned to 3.x, not 4.x as in databox/client: vitest 4 requires
 * vite >= 6, while this package is held at vite 5 by vite-plugin-node, which
 * builds dist/console.mjs. Bumping vitest means replacing that plugin first.
 */

export default defineConfig({
    test: {
        environment: 'node',
        globals: true,
        // Forks keep each test file in its own process: the modules that read
        // the config at import time (configLoader, locations, pathFilter) are
        // re-evaluated per file instead of being shared.
        pool: 'forks',
        include: ['tests/unit/**/*.test.ts'],
        setupFiles: ['./tests/setup.ts'],
    },
});
