import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// ── PWA Service Worker: auto-actualización ──────────────────────────────
// En producción, cuando Vite genera un nuevo SW, el usuario ve una
// notificación discreta y la app se recarga para aplicar la actualización.
if ('serviceWorker' in navigator) {
    // virtual:pwa-register es inyectado por vite-plugin-pwa en el build
    import('virtual:pwa-register').then(({ registerSW }) => {
        registerSW({
            immediate: true,

            onNeedRefresh() {
                // Nueva versión disponible — mostrar banner discreto
                const banner = document.createElement('div');
                banner.id = 'pwa-update-banner';
                banner.style.cssText = [
                    'position:fixed;bottom:16px;left:50%;transform:translateX(-50%)',
                    'background:#10213E;color:#fff;padding:12px 20px;border-radius:12px',
                    'box-shadow:0 4px 24px rgba(0,0,0,.35);z-index:9999',
                    'display:flex;align-items:center;gap:12px;font-family:Arial,sans-serif;font-size:13px',
                ].join(';');
                banner.innerHTML = `
                    <span>🔄 Nueva versión disponible</span>
                    <button id="pwa-update-btn" style="
                        background:#EE2857;border:none;color:#fff;
                        padding:6px 14px;border-radius:8px;cursor:pointer;
                        font-weight:700;font-size:12px;
                    ">Actualizar</button>
                    <button id="pwa-dismiss-btn" style="
                        background:transparent;border:none;color:#aaa;
                        cursor:pointer;font-size:16px;line-height:1;
                    ">✕</button>
                `;
                document.body.appendChild(banner);

                document.getElementById('pwa-update-btn').addEventListener('click', () => {
                    banner.remove();
                    window.location.reload();
                });
                document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
                    banner.remove();
                });
            },

            onOfflineReady() {
                console.log('[PWA] JK Automotive listo para uso offline.');
            },

            onRegisteredSW(swUrl, registration) {
                // Revisar actualizaciones cada hora
                setInterval(() => registration?.update(), 60 * 60 * 1000);
            },
        });
    }).catch(() => {
        // En desarrollo (dev mode), virtual:pwa-register no existe — ignorar
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
