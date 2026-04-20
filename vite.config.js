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

            // El SW se incluye en el build de Vite pero Laravel lo sirve desde /
            // outDir apunta a public para que sea accesible directamente
            outDir: 'public',
            buildBase: '/',

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
                // Cachear assets estáticos generados por Vite
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                // Rutas que NO deben cachearse (dinámicas de Laravel)
                navigateFallbackDenylist: [/^\/api/, /^\/sanctum/, /^\/_debugbar/],
                navigateFallback: null,
                // Estrategia: Network First para navegación, Cache First para assets
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-cache',
                            expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
                        },
                    },
                ],
            },

            devOptions: {
                enabled: false, // Desactivar SW en desarrollo para no interferir con HMR
            },
        }),
    ],
});
