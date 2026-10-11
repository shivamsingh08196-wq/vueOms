import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }), vue(), tailwindcss()],
  resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
  test: {
    environment: 'node',
    setupFiles: ['resources/js/tests/setup.js'],
    include: ['resources/js/**/*.{test,spec}.js', 'tests/frontend/**/*.{test,spec}.js'],
    exclude: ['qa-kit/e2e/**', 'node_modules/**']
  },
});
