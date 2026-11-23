import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Design System v1 — tokens (fuente: artifact 2f7hDWrQs2ZPv2qZnsvfGm).
 * Los tokens nuevos son aditivos: no reemplazan utilidades existentes.
 * Reglas de texto: ink-subtle SOLO para placeholder, disabled, decoración e información no esencial.
 * No usar ink-subtle en labels, instrucciones, datos de negocio ni mensajes que el usuario deba leer.
 */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            // Inter según DS v1 (cargada en resources/views/app.blade.php)
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Marca existente del proyecto (no tocar)
                'jk-blue': '#10213E',  // El azul del logo
                'jk-red': '#EE2857',   // El rojo de acento
                'jk-gray': '#BDBEC6',  // El gris de los manuales

                // Design System v1 — marca y superficies
                navy: '#10213E',
                teal: '#13B8A6',
                page: '#F5F7FA',
                surface: { DEFAULT: '#FFFFFF', 2: '#F9FAFB', column: '#F2F4F7' },
                line: { DEFAULT: '#EAECF0', strong: '#D0D5DD' },

                // Texto
                ink: {
                    DEFAULT: '#1D2939',   // text-primary
                    secondary: '#475467', // text-secondary
                    muted: '#667085',     // text-muted: helper, metadata, captions
                    subtle: '#98A2B3',    // text-subtle: SOLO placeholder/disabled/decorativo
                },

                // Foco de accesibilidad. DS: teal no cumple 3:1 sobre blanco; ver informe (desviación pendiente de aprobación).
                focus: '#10213E',

                // Semánticos
                success: { DEFAULT: '#12B76A', text: '#067647', soft: '#ECFDF3' },
                warning: { DEFAULT: '#F79009', text: '#B54708', soft: '#FFFAEB' },
                danger: { DEFAULT: '#F04438', text: '#B42318', soft: '#FEF3F2' },
                info: { DEFAULT: '#2E90FA', text: '#175CD3', soft: '#EFF8FF' },
                neutral: { DEFAULT: '#475467', soft: '#F2F4F7' },
            },

            // Escala tipográfica canónica del DS (Inter según DS; ver informe sobre Figtree).
            fontSize: {
                'ds-metric': ['30px', { lineHeight: '33px', fontWeight: '700' }],
                'ds-page-title': ['24px', { lineHeight: '29px', fontWeight: '700' }],
                'ds-section-title': ['18px', { lineHeight: '23px', fontWeight: '600' }],
                'ds-body': ['14px', { lineHeight: '21px', fontWeight: '400' }],
                'ds-control': ['13px', { lineHeight: '18px', fontWeight: '500' }],
                'ds-label': ['12px', { lineHeight: '16px', fontWeight: '500' }],
                'ds-caption': ['12px', { lineHeight: '16px', fontWeight: '400' }],
                'ds-sidebar-group': ['11px', { lineHeight: '14px', fontWeight: '600' }],
            },

            // Alturas de control del DS: compact 32, standard 40, touch 44.
            height: {
                'control-compact': '32px',
                control: '40px',
                'control-touch': '44px',
            },

            // Margen del contenido junto al sidebar (ml-sidebar / lg:ml-sidebar).
            spacing: {
                sidebar: '232px',
            },
            // Anchos de layout del DS: sidebar desktop 232, side panel de referencia 300.
            width: {
                sidebar: '232px',
                'side-panel': '300px',
            },
        },
    },

    plugins: [forms],
};
