import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig(({ mode }) => {
    // Local:      lê .env          → APP_URL=http://localhost:8000
    // Produção:   lê .env.production → APP_URL=https://beconnected.escaleno.co.mz
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL || 'http://localhost:8000';
    return {
        server: {
            host: '0.0.0.0',
            port: 5173,
            cors: { origin: '*' },
            // O browser acede sempre através do gateway nginx (porta 8000).
            // O nginx proxy /@vite/ e /resources/ para app:5173 (Vite interno).
            origin: 'http://localhost:8000',
            hmr: {
                host: 'localhost',
                port: 8000,
                clientPort: 8000,
                protocol: 'ws',
            },
            watch: { ignored: ['**/storage/framework/views/**'] },
        },
        plugins: [
            laravel({
                input: ['resources/js/app.js'],
                refresh: true,
            }),
            vue(),
            tailwindcss(),
            VitePWA({
                registerType: 'autoUpdate',
                devOptions: { enabled: true, type: 'module' },
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
                    skipWaiting: true,
                    clientsClaim: true,
                    navigateFallback: null,
                    globPatterns: ['**/*.{js,css,ico,png,svg}'],
                    runtimeCaching: [
                        {
                            urlPattern: /\/api\/.*/i,
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
        // Define APP_URL disponível no JS do frontend via import.meta.env.VITE_APP_URL
        define: {
            __APP_URL__: JSON.stringify(appUrl),
        },
        build: {
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (id.includes('node_modules/vue') || id.includes('node_modules/vue-router') || id.includes('node_modules/pinia')) {
                            return 'vendor';
                        }
                        if (id.includes('node_modules/axios')) {
                            return 'http';
                        }
                    },
                },
            },
        },
        resolve: {
            alias: { '@': '/resources/js' },
        },
    };
});
