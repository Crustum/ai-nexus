import { resolve } from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': resolve(root, 'js'),
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    test: {
        environment: 'jsdom',
        include: ['js/__tests__/**/*.{test,spec}.js'],
    },
});
