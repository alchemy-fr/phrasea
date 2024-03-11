import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react-swc';
import svgr from 'vite-plugin-svgr';
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
    },
    resolve: {
        alias: {
            path: 'url',
        },
    },
});
