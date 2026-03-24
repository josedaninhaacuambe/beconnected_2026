import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'icons/*.png'],
            manifest: {
                name: 'Beconnect - Mercado Virtual de Moçambique',
                short_name: 'Beconnect',
                description: 'Compra nas melhores lojas do teu país. Entrega em casa.',
                theme_color: '#D4A017',
                background_color: '#1A1A1A',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/',
                lang: 'pt',
                icons: [
                    { src: '/icons/icon-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icons/icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
                categories: ['shopping', 'lifestyle'],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg}'],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/beconnect\.test\/api\/.*/i,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'api-cache',
                            expiration: { maxEntries: 100, maxAgeSeconds: 60 * 5 },
                        },
                    },
                ],
            },
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/vue') || id.includes('node_modules/vue-router') || id.includes('node_modules/pinia')) {
                        return 'vendor'
                    }
                    if (id.includes('node_modules/axios')) {
                        return 'http'
                    }
                },
            },
        },
    },
    resolve: {
        alias: { '@': '/resources/js' },
    },
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
