import { resolve } from 'path';
import { fileURLToPath } from 'url';
import vue from '@vitejs/plugin-vue';

const root = fileURLToPath(new URL('.', import.meta.url));

/** @type {import('vite').UserConfig} */
export default {
    plugins: [vue()],
    root,
    base: './',
    build: {
        outDir: resolve(root, '../../webroot/frontend'),
        emptyOutDir: true,
        assetsDir: 'assets',
        manifest: 'manifest.json',
        chunkSizeWarningLimit: 1600,
        rollupOptions: {
            input: {
                app: resolve(root, 'js/app.js'),
                styles: resolve(root, 'sass/styles.scss'),
                'styles-dark': resolve(root, 'sass/styles-dark.scss'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return '[name][extname]';
                    }

                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': resolve(root, 'js'),
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                includePaths: [resolve(root, 'node_modules')],
                quietDeps: true,
                silenceDeprecations: [
                    'import',
                    'color-functions',
                    'global-builtin',
                    'legacy-js-api',
                    'if-function',
                ],
            },
        },
    },
};
