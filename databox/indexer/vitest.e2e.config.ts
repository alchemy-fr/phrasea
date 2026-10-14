import {defineConfig} from 'vitest/config';

/**
 * End-to-end suite, kept out of `pnpm test` on purpose: it needs the whole
 * Docker stack and is driven by bin/dev/test-indexer-e2e.sh, which runs the
 * files one at a time with an indexation in between.
 */

export default defineConfig({
    test: {
        environment: 'node',
        globals: true,
        include: ['tests/e2e/**/*.test.ts'],
        // No setupFiles: unlike the unit suite, nothing here loads the indexer
        // config, and the fixture environment would hide the real one.
        testTimeout: 60_000,
        hookTimeout: 120_000,
        // One file at a time: the suite is a sequence of steps sharing the
        // /e2e volume, not independent specs.
        fileParallelism: false,
    },
});
