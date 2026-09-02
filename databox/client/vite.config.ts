import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react-swc';
import svgr from 'vite-plugin-svgr';
import fixReactVirtualized from 'esbuild-plugin-react-virtualized';
import checker from 'vite-plugin-checker';

// https://vitejs.dev/config/
export default defineConfig({
    build: {
        sourcemap: true,
    },
    plugins: [
        react(),
        checker({
            typescript: true,
        }),
        svgr({
            include: '**/*.svg?react',
        }),
    ],
    server: {
        port: 3000,
        host: '0.0.0.0',
        allowedHosts: true,
    },
    ssr: {
        // Packages that must go through the vite transform pipeline during
        // server-side rendering: CJS named-export interop issues, and
        // MUI/emotion which must resolve to a single instance shared with
        // the transformed workspace sources
        noExternal: [
            '@jonkoops/matomo-tracker-react',
            '@jonkoops/matomo-tracker',
            'styled-components',
            'react-virtualized',
            /^@algolia\//,
            /^@mui\//,
            /^@emotion\//,
        ],
    },
    optimizeDeps: {
        esbuildOptions: {
            plugins: [fixReactVirtualized],
        },
    },
});
