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
    <div class="inline-flex items-center rounded-full border border-gray-200 bg-white/80 backdrop-blur-sm shadow-sm p-1 gap-1">

        <!-- ── Español / México ─────────────────────────────── -->
        <button
            type="button"
            @click="choose('es')"
            :aria-label="'Cambiar a Español'"
            class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold transition-all duration-150 select-none"
            :class="locale === 'es'
                ? 'bg-[#10213E] text-white shadow-inner'
                : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'"
        >
            <!-- Mexico flag SVG -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600" class="h-3.5 w-auto rounded-sm shrink-0" aria-hidden="true">
                <rect width="900" height="600" fill="#006847"/>
                <rect x="300" width="300" height="600" fill="#fff"/>
                <rect x="600" width="300" height="600" fill="#ce1126"/>
                <!-- Eagle coat of arms (simplified circle) -->
                <circle cx="450" cy="300" r="60" fill="#8B6914" opacity="0.35"/>
            </svg>
            <span>ES</span>
        </button>

        <!-- ── English / USA ────────────────────────────────── -->
        <button
            type="button"
            @click="choose('en')"
            :aria-label="'Switch to English'"
            class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold transition-all duration-150 select-none"
            :class="locale === 'en'
                ? 'bg-[#10213E] text-white shadow-inner'
                : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'"
        >
            <!-- USA flag SVG -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600" class="h-3.5 w-auto rounded-sm shrink-0" aria-hidden="true">
                <!-- Red and white stripes -->
                <rect width="900" height="600" fill="#B22234"/>
                <rect y="46"  width="900" height="46" fill="#fff"/>
                <rect y="138" width="900" height="46" fill="#fff"/>
                <rect y="230" width="900" height="46" fill="#fff"/>
                <rect y="322" width="900" height="46" fill="#fff"/>
                <rect y="414" width="900" height="46" fill="#fff"/>
                <rect y="508" width="900" height="46" fill="#fff"/>
                <!-- Blue canton -->
                <rect width="360" height="323" fill="#3C3B6E"/>
                <!-- Stars (simplified dots) -->
                <g fill="#fff">
                    <circle cx="36"  cy="32"  r="11"/><circle cx="108" cy="32"  r="11"/><circle cx="180" cy="32"  r="11"/><circle cx="252" cy="32"  r="11"/><circle cx="324" cy="32"  r="11"/>
                    <circle cx="72"  cy="80"  r="11"/><circle cx="144" cy="80"  r="11"/><circle cx="216" cy="80"  r="11"/><circle cx="288" cy="80"  r="11"/>
                    <circle cx="36"  cy="128" r="11"/><circle cx="108" cy="128" r="11"/><circle cx="180" cy="128" r="11"/><circle cx="252" cy="128" r="11"/><circle cx="324" cy="128" r="11"/>
                    <circle cx="72"  cy="176" r="11"/><circle cx="144" cy="176" r="11"/><circle cx="216" cy="176" r="11"/><circle cx="288" cy="176" r="11"/>
                    <circle cx="36"  cy="224" r="11"/><circle cx="108" cy="224" r="11"/><circle cx="180" cy="224" r="11"/><circle cx="252" cy="224" r="11"/><circle cx="324" cy="224" r="11"/>
                    <circle cx="72"  cy="272" r="11"/><circle cx="144" cy="272" r="11"/><circle cx="216" cy="272" r="11"/><circle cx="288" cy="272" r="11"/>
                </g>
            </svg>
            <span>EN</span>
        </button>

    </div>
</template>
