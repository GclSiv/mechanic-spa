import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: 'auto',

            // Para Laravel: el SW debe estar en la raíz pública, no en /build/
            // Usamos strategies: 'generateSW' con scope correcto
            strategies: 'generateSW',

            // Vite con laravel-vite-plugin compila a public/build/
            // El SW y el manifest deben estar en public/ (raíz)
            outDir: 'public',
            base: '/',

            includeAssets: [
                'images/pwa-192x192.png',
                'images/pwa-512x512.png',
                'images/pwa-apple-180x180.png',
                'images/pwa-maskable-512x512.png',
            ],

            manifest: {
                name: 'JK Automotive ERP',
                short_name: 'JK Auto',
                description: 'Sistema de Gestión de Taller Mecánico',
                theme_color: '#10213E',
                background_color: '#ffffff',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/dashboard',
                scope: '/',
                lang: 'es',
                icons: [
                    {
                        src: '/images/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/images/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/images/pwa-maskable-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                    {
                        src: '/images/pwa-apple-180x180.png',
                        sizes: '180x180',
                        type: 'image/png',
                        purpose: 'any',
                    },
                ],
            },

            workbox: {
                // Solo cachear assets estáticos de public/ y public/build/
                globDirectory: 'public',
                globPatterns: [
                    'build/**/*.{js,css}',
                    'images/pwa-*.png',
                ],
                // NO usar navigateFallback en SPAs con Laravel (rompe el routing del servidor)
                navigateFallback: null,
                navigateFallbackDenylist: [],
                // Estrategia Network-First para assets de la app
                runtimeCaching: [
                    {
                        // Fuentes de Bunny.net - Cache First
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-fonts-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                    {
                        // Assets de /build/ - Stale While Revalidate
                        urlPattern: /\/build\/.*/i,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'vite-build-cache',
                            expiration: { maxEntries: 50 },
                        },
                    },
                ],
            },

            devOptions: {
                // Activado en dev para que Chrome pueda detectar el manifest
                // y mostrar el botón de instalación incluso con php artisan serve
                enabled: true,
                type: 'module',
                navigateFallback: null,
            },
        }),
    ],
});
