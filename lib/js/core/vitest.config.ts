import {defineConfig} from 'vitest/config';
import path from 'node:path';

export default defineConfig({
    esbuild: {jsx: 'automatic'},
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['src/**/*.test.{ts,tsx}'],
    },
    resolve: {
        alias: {
            'server-only': path.resolve(__dirname, './src/test/server-only.ts'),
        },
    },
});
