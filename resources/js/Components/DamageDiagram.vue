<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

// Severidades: colores de la zona marcada y clases de los botones
const SEVERIDADES = [
    { key: 'leve',  label: 'Leve',  fill: '#fde68a', stroke: '#d97706', idle: 'border-amber-300 text-amber-700',  active: 'bg-amber-100 border-amber-500 text-amber-800 ring-2 ring-amber-400' },
    { key: 'medio', label: 'Medio', fill: '#fdba74', stroke: '#ea580c', idle: 'border-orange-300 text-orange-700', active: 'bg-orange-100 border-orange-500 text-orange-800 ring-2 ring-orange-400' },
    { key: 'grave', label: 'Grave', fill: '#fca5a5', stroke: '#dc2626', idle: 'border-red-300 text-red-700',      active: 'bg-red-100 border-red-500 text-red-800 ring-2 ring-red-400' },
];

// Zonas tocables. Las claves coinciden con las del backend (StoreRecepcionRequest).
const ZONAS = [
    { key: 'defensa_del', d: 'M92 40 C120 24 180 24 208 40 L220 78 L80 78 Z' },
    { key: 'capo',        d: 'M80 96 C110 76 190 76 220 96 L224 196 L76 196 Z' },
    { key: 'parabrisas',  d: 'M78 200 L222 200 L210 240 L90 240 Z' },
    { key: 'techo',       d: 'M90 244 L210 244 L208 342 L92 342 Z' },
    { key: 'luneta',      d: 'M90 346 L210 346 L222 396 L78 396 Z' },
    { key: 'cajuela',     d: 'M78 400 L222 400 C228 436 230 486 226 512 L74 512 C70 486 72 436 78 400 Z' },
    { key: 'defensa_tra', d: 'M80 528 L220 528 C212 560 184 574 150 574 C116 574 88 560 80 528 Z' },
    { key: 'puerta_fi',   x: 64,  y: 200, w: 22, h: 124, rx: 4 },
    { key: 'puerta_fd',   x: 214, y: 200, w: 22, h: 124, rx: 4 },
    { key: 'puerta_ti',   x: 64,  y: 326, w: 22, h: 74,  rx: 4 },
    { key: 'puerta_td',   x: 214, y: 326, w: 22, h: 74,  rx: 4 },
    { key: 'espejo_i',    x: 60,  y: 196, w: 12, h: 16,  rx: 4 },
    { key: 'espejo_d',    x: 228, y: 196, w: 12, h: 16,  rx: 4 },
    { key: 'llanta_fi',   x: 34,  y: 112, w: 18, h: 62,  rx: 7 },
    { key: 'llanta_fd',   x: 248, y: 112, w: 18, h: 62,  rx: 7 },
    { key: 'llanta_ti',   x: 34,  y: 432, w: 18, h: 62,  rx: 7 },
    { key: 'llanta_td',   x: 248, y: 432, w: 18, h: 62,  rx: 7 },
];

const markers = computed(() => props.modelValue ?? []);
const findMarker = (key) => markers.value.find((m) => m.zone === key);

// Un marcador sin severidad (datos antiguos) se muestra en rojo como antes
function severidadDe(key) {
    const m = findMarker(key);
    return m ? SEVERIDADES.find((s) => s.key === m.severity) : null;
}
const zoneFill   = (key) => (severidadDe(key)?.fill   ?? (findMarker(key) ? '#fde2e8' : '#ffffff'));
const zoneStroke = (key) => (severidadDe(key)?.stroke ?? (findMarker(key) ? '#EE2857' : '#94a3b8'));

function emitir(lista) {
    emit('update:modelValue', lista);
}

// Marca o desmarca una zona completa del vehículo
function toggleZone(key) {
    if (findMarker(key)) {
        emitir(markers.value.filter((m) => m.zone !== key));
    } else {
        emitir([...markers.value, { zone: key, note: '', severity: 'leve' }]);
    }
}

function setSeverity(index, severity) {
    emitir(markers.value.map((m, i) => (i === index ? { ...m, severity } : m)));
}

function updateNote(index, note) {
    emitir(markers.value.map((m, i) => (i === index ? { ...m, note } : m)));
}

function removeMarker(index) {
    emitir(markers.value.filter((_, i) => i !== index));
}
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        <div>
            <svg viewBox="0 0 300 620" class="w-64 mx-auto select-none" role="group" aria-label="Diagrama del vehículo">
                <!-- Llantas y carrocería (decorativo) -->
                <rect v-for="r in [[36,112],[246,112],[36,432],[246,432]]" :key="r.join('-')" :x="r[0]" :y="r[1]" width="18" height="62" rx="7" fill="#1e293b" />
                <path d="M150 18 C192 18 222 42 228 98 L234 200 C237 262 238 300 236 332 C240 402 236 462 232 520 C228 562 198 582 150 583 C102 582 72 562 68 520 C64 462 60 402 64 332 C62 300 63 262 66 200 L72 98 C78 42 108 18 150 18 Z" fill="#f8fafc" stroke="#94a3b8" stroke-width="2.5" />

                <!-- Zonas tocables -->
                <g
                    v-for="z in ZONAS"
                    :key="z.key"
                    class="cursor-pointer focus:outline-none"
                    role="button"
                    tabindex="0"
                    :aria-label="t('recepcion_form.zona_' + z.key)"
                    @click="toggleZone(z.key)"
                    @keydown.enter.prevent="toggleZone(z.key)"
                    @keydown.space.prevent="toggleZone(z.key)"
                >
                    <title>{{ t('recepcion_form.zona_' + z.key) }}</title>
                    <path v-if="z.d" :d="z.d" :fill="zoneFill(z.key)" :stroke="zoneStroke(z.key)" stroke-width="1.5" />
                    <rect v-else :x="z.x" :y="z.y" :width="z.w" :height="z.h" :rx="z.rx" :fill="zoneFill(z.key)" :stroke="zoneStroke(z.key)" stroke-width="1.5" />
                </g>
            </svg>
            <p class="text-xs text-gray-400 text-center mt-2">{{ t('recepcion_form.sinDanos') }} — toca una zona para marcarla</p>
        </div>

        <div>
            <p v-if="!markers.length" class="text-sm text-gray-400 italic">{{ t('recepcion_form.sinDanos') }}</p>
            <ul v-else class="space-y-3">
                <li v-for="(m, i) in markers" :key="m.zone" class="rounded-lg border border-gray-200 p-3 space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-[#EE2857] text-white text-xs font-bold flex items-center justify-center shrink-0">
                            {{ i + 1 }}
                        </span>
                        <span class="text-sm font-bold text-gray-700 flex-1">{{ t('recepcion_form.zona_' + m.zone) }}</span>
                        <button type="button" class="text-xs font-bold text-gray-500 hover:text-red-600" @click="removeMarker(i)">
                            {{ t('recepcion_form.quitar') }}
                        </button>
                    </div>

                    <!-- Severidad -->
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="s in SEVERIDADES"
                            :key="s.key"
                            type="button"
                            class="rounded-md border px-2 py-2 text-xs font-bold min-h-[40px]"
                            :class="m.severity === s.key ? s.active : s.idle"
                            @click="setSeverity(i, s.key)"
                        >
                            {{ s.label }}
                        </button>
                    </div>

                    <input
                        type="text"
                        :value="m.note"
                        :placeholder="t('recepcion_form.notaDano')"
                        maxlength="120"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-jk-blue focus:ring-jk-blue"
                        @input="updateNote(i, $event.target.value)"
                    />
                </li>
            </ul>
        </div>
    </div>
</template>
