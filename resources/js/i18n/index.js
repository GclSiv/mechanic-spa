import { createI18n } from 'vue-i18n';
import es from './es.js';
import en from './en.js';

const savedLocale = localStorage.getItem('jk_locale') || 'es';

export const i18n = createI18n({
    legacy: false,          // Composition API mode
    locale: savedLocale,
    fallbackLocale: 'es',
    messages: { es, en },
});

export function setLocale(lang) {
    i18n.global.locale.value = lang;
    localStorage.setItem('jk_locale', lang);
    document.documentElement.lang = lang;
}
