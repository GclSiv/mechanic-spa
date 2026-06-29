<script setup>
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n/index.js';

const { locale } = useI18n();

function choose(lang) {
    if (locale.value === lang) return;
    setLocale(lang);
}
</script>

<template>
    <div class="inline-flex items-center gap-1.5 rounded-full bg-white/70 backdrop-blur-sm border border-gray-200 p-1 shadow-sm">
        <button
            type="button"
            @click="choose('es')"
            :aria-pressed="locale === 'es'"
            class="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold transition-all duration-150"
            :class="locale === 'es'
                ? 'bg-[#10213E] text-white shadow-sm'
                : 'text-gray-500 hover:bg-gray-100'"
        >
            <span class="text-base leading-none">🇲🇽</span>
            <span>ES</span>
        </button>

        <button
            type="button"
            @click="choose('en')"
            :aria-pressed="locale === 'en'"
            class="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold transition-all duration-150"
            :class="locale === 'en'
                ? 'bg-[#10213E] text-white shadow-sm'
                : 'text-gray-500 hover:bg-gray-100'"
        >
            <span class="text-base leading-none">🇺🇸</span>
            <span>EN</span>
        </button>
    </div>
</template>
