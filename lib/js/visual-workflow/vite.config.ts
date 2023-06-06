import { resolve } from 'path'
import { defineConfig } from 'vite'
import dts from 'vite-plugin-dts';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  build: {
    sourcemap: true,
    lib: {
      entry: resolve(__dirname, 'src/index.ts'),
      name: 'Visual Workflow',
      formats: ['es', 'umd'],
      fileName: 'visual-workflow',
    },
    rollupOptions: {
      external: ['react', 'react-dom', 'styled-components'],
      output: {
        globals: {
          react: 'React',
          'react-dom': 'ReactDOM',
          'styled-components': 'styled',
        },
      },
    },
  },
  plugins: [
    react(),
    dts({
      insertTypesEntry: true,
    }),
  ],
})
