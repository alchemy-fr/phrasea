import {defineConfig} from 'vitest/config';

/**
 * Vitest is pinned to 3.x, not 4.x as in databox/client: under vitest 4 the
 * fork running tests/unit/handlers/fs/shared.test.ts exits unexpectedly.
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
