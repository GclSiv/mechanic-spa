<script setup>
// Diagrama de daños. Dibuja la silueta del diagrama recibido por prop (car, motorcycle, forklift, truck,
// heavy_generic, generic). No decide el diagrama: lo recibe ya resuelto por el backend o por la vista.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { DIAGRAMAS, DIAGRAMA_CAR, diagramaValido, etiquetaZona } from '@/Components/DamageDiagrams/diagramas.js';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    // Clave del diagrama a dibujar. Por defecto automóvil, como antes de existir diagramas por tipo.
    diagram: { type: String, default: DIAGRAMA_CAR },
    // Solo lectura (Editar): muestra los daños guardados sin permitir cambios.
    readonly: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
const { t, te } = useI18n();

// Severidades: colores de la zona marcada y clases de los botones
const SEVERIDADES = [
    { key: 'leve',  label: 'Leve',  fill: '#fde68a', stroke: '#d97706', idle: 'border-amber-300 text-amber-700',  active: 'bg-amber-100 border-amber-500 text-amber-800 ring-2 ring-amber-400' },
    { key: 'medio', label: 'Medio', fill: '#fdba74', stroke: '#ea580c', idle: 'border-orange-300 text-orange-700', active: 'bg-orange-100 border-orange-500 text-orange-800 ring-2 ring-orange-400' },
    { key: 'grave', label: 'Grave', fill: '#fca5a5', stroke: '#dc2626', idle: 'border-red-300 text-red-700',      active: 'bg-red-100 border-red-500 text-red-800 ring-2 ring-red-400' },
];

const diagramaActual = computed(() => diagramaValido(props.diagram));
const definicion = computed(() => DIAGRAMAS[diagramaActual.value]);

const markers = computed(() => props.modelValue ?? []);
const findMarker = (key) => markers.value.find((m) => m.zone === key);

// Un marcador sin severidad (datos antiguos) se muestra en rojo como antes
function severidadDe(key) {
    const m = findMarker(key);
    return m ? SEVERIDADES.find((s) => s.key === m.severity) : null;
}
const zoneFill   = (key) => (severidadDe(key)?.fill   ?? (findMarker(key) ? '#fde2e8' : '#ffffff'));
const zoneStroke = (key) => (severidadDe(key)?.stroke ?? (findMarker(key) ? '#EE2857' : '#94a3b8'));
// La zona marcada se distingue también por grosor de trazo, no solo por color
const zoneStrokeWidth = (key) => (findMarker(key) ? 2.5 : 1.5);

function emitir(lista) {
    emit('update:modelValue', lista);
}

// Marca o desmarca una zona completa del vehículo
function toggleZone(key) {
    if (props.readonly) return;
    if (findMarker(key)) {
        emitir(markers.value.filter((m) => m.zone !== key));
    } else {
        emitir([...markers.value, { zone: key, note: '', severity: 'leve' }]);
    }
}

function setSeverity(index, severity) {
    if (props.readonly) return;
    emitir(markers.value.map((m, i) => (i === index ? { ...m, severity } : m)));
}

function updateNote(index, note) {
    if (props.readonly) return;
    emitir(markers.value.map((m, i) => (i === index ? { ...m, note } : m)));
}

function removeMarker(index) {
    if (props.readonly) return;
    emitir(markers.value.filter((_, i) => i !== index));
}

const etiqueta = (zona) => etiquetaZona(zona, t, te);
const nombreDiagrama = computed(() => t('diagramasUi.nombre_' + diagramaActual.value));
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        <div>
            <p class="mb-2 text-center text-xs font-semibold text-gray-500">{{ nombreDiagrama }}</p>
            <svg
                :viewBox="definicion.viewBox"
                class="h-[380px] md:h-[400px] xl:h-[420px] w-auto max-w-full mx-auto select-none"
                role="group"
                :aria-label="nombreDiagrama"
            >
                <!-- Silueta decorativa: no interactiva -->
                <template v-for="(s, i) in definicion.decor" :key="'decor-' + i">
                    <path v-if="s.d" :d="s.d" :fill="s.fill" :stroke="s.stroke" :stroke-width="s.sw" />
                    <ellipse v-else-if="s.cx !== undefined" :cx="s.cx" :cy="s.cy" :rx="s.rx" :ry="s.ry" :fill="s.fill" :stroke="s.stroke" :stroke-width="s.sw" />
                    <rect v-else :x="s.x" :y="s.y" :width="s.w" :height="s.h" :rx="s.rx" :fill="s.fill" :stroke="s.stroke" :stroke-width="s.sw" />
                </template>

                <!-- Zonas tocables del diagrama activo -->
                <g
                    v-for="z in definicion.zonas"
                    :key="z.key"
                    :class="readonly ? 'zona-lectura' : 'zona-diagrama cursor-pointer focus:outline-none'"
                    :role="readonly ? 'img' : 'button'"
                    :tabindex="readonly ? -1 : 0"
                    :aria-label="etiqueta(z.key)"
                    @click="toggleZone(z.key)"
                    @keydown.enter.prevent="toggleZone(z.key)"
                    @keydown.space.prevent="toggleZone(z.key)"
                >
                    <title>{{ etiqueta(z.key) }}</title>
                    <template v-for="(s, i) in z.shapes" :key="z.key + '-' + i">
                        <path v-if="s.d" :d="s.d" :fill="zoneFill(z.key)" :stroke="zoneStroke(z.key)" :stroke-width="zoneStrokeWidth(z.key)" />
                        <ellipse v-else-if="s.cx !== undefined" :cx="s.cx" :cy="s.cy" :rx="s.rx" :ry="s.ry" :fill="zoneFill(z.key)" :stroke="zoneStroke(z.key)" :stroke-width="zoneStrokeWidth(z.key)" />
                        <rect v-else :x="s.x" :y="s.y" :width="s.w" :height="s.h" :rx="s.rx" :fill="zoneFill(z.key)" :stroke="zoneStroke(z.key)" :stroke-width="zoneStrokeWidth(z.key)" />
                    </template>
                </g>

                <!-- Detalles encima de las zonas (manillar, parabrisas, tacos...): solo trazo, nunca interactivos -->
                <template v-for="(s, i) in definicion.detalles" :key="'det-' + i">
                    <path v-if="s.d" :d="s.d" :fill="s.fill" :stroke="s.stroke" :stroke-width="s.sw" stroke-linecap="round" pointer-events="none" />
                    <ellipse v-else-if="s.cx !== undefined" :cx="s.cx" :cy="s.cy" :rx="s.rx" :ry="s.ry" :fill="s.fill" :stroke="s.stroke" :stroke-width="s.sw" pointer-events="none" />
                    <rect v-else :x="s.x" :y="s.y" :width="s.w" :height="s.h" :rx="s.rx" :fill="s.fill" :stroke="s.stroke" :stroke-width="s.sw" pointer-events="none" />
                </template>
            </svg>
            <p class="text-xs text-gray-400 text-center mt-2">
                {{ readonly ? t('recepcion_form.sinDanos') : t('recepcion_form.sinDanos') + ' — toca una zona para marcarla' }}
            </p>
        </div>

        <div>
            <p v-if="!markers.length" class="text-sm text-gray-400 italic">{{ t('recepcion_form.sinDanos') }}</p>
            <ul v-else class="space-y-3">
                <li v-for="(m, i) in markers" :key="m.zone + '-' + i" class="rounded-lg border border-gray-200 p-3 space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-[#EE2857] text-white text-xs font-bold flex items-center justify-center shrink-0">
                            {{ i + 1 }}
                        </span>
                        <span class="text-sm font-bold text-gray-700 flex-1">{{ etiqueta(m.zone) }}</span>
                        <button v-if="!readonly" type="button" class="text-xs font-bold text-gray-500 hover:text-red-600" @click="removeMarker(i)">
                            {{ t('recepcion_form.quitar') }}
                        </button>
                    </div>

                    <template v-if="!readonly">
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
                    </template>
                    <p v-else class="text-xs text-gray-500">
                        <span class="capitalize">{{ m.severity }}</span><span v-if="m.note"> — {{ m.note }}</span>
                    </p>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
/* Affordance: al pasar el cursor la zona se aclara; con foco de teclado se marca con trazo punteado */
.zona-diagrama { transition: opacity 0.15s ease; }
.zona-diagrama:hover { opacity: 0.8; }
.zona-diagrama:focus-visible > * { stroke: #1d4ed8; stroke-width: 2.5; stroke-dasharray: 4 3; }
</style>
