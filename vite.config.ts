import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            // legacy alias names kept for port parity (WS5.3 file mapping)
            '@ac': fileURLToPath(new URL('./resources/js/admin', import.meta.url)),
            '@auth': fileURLToPath(new URL('./resources/js/auth', import.meta.url)),
            '@common': fileURLToPath(new URL('./resources/js/common', import.meta.url)),
        },
    },

    plugins: [
        laravel({
            // one bundle per shell page; admin carries the heavy libraries
            input: [
                'resources/js/admin.ts',
                'resources/js/auth.ts',
                'resources/js/share.ts',
            ],
            refresh: true,
        }),

        vue(),
    ],
});
