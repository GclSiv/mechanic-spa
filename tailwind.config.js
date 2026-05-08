import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
            colors: {
                'jk-blue': '#10213E',  // El azul del logo
                'jk-red': '#EE2857',   // El rojo de acento
                'jk-gray': '#BDBEC6',  // El gris de los manuales
            },
        },
    },

    plugins: [forms],
};
