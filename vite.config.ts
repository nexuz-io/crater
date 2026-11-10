import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'

export default defineConfig({
    // Ensure dynamic-import dependency preloads point at /build/assets
    // instead of /assets (which nginx 404s, breaking route navigation).
    base: '/build/',
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/scripts/main.js'),
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources'),
            "vue-i18n": "vue-i18n/dist/vue-i18n.cjs.js",
        },
    },
    plugins: [vue()],
    server: {
        watch: {
            ignored: ['**/.env/**'],
        },
    },
})
