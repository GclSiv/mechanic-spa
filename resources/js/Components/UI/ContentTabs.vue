<script setup>
// Pestañas del Expediente con patrón tablist/tab. Navegación por teclado (flechas, Inicio, Fin).
import { ref, nextTick } from 'vue';

const props = defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
    label: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);

const botones = ref([]);

function seleccionar(key) {
    emit('update:modelValue', key);
}

function alTeclear(e, index) {
    const n = props.tabs.length;
    let siguiente = null;
    if (e.key === 'ArrowRight') siguiente = (index + 1) % n;
    if (e.key === 'ArrowLeft') siguiente = (index - 1 + n) % n;
    if (e.key === 'Home') siguiente = 0;
    if (e.key === 'End') siguiente = n - 1;
    if (siguiente === null) return;
    e.preventDefault();
    seleccionar(props.tabs[siguiente].key);
    nextTick(() => botones.value[siguiente]?.focus());
}
</script>

<template>
    <div class="overflow-x-auto border-b border-line">
        <div role="tablist" :aria-label="label" class="flex min-w-max gap-1">
            <button
                v-for="(tab, i) in tabs"
                :key="tab.key"
                :id="`tab-${tab.key}`"
                ref="botones"
                type="button"
                role="tab"
                :aria-selected="modelValue === tab.key ? 'true' : 'false'"
                :aria-controls="`panel-${tab.key}`"
                :tabindex="modelValue === tab.key ? 0 : -1"
                class="ds-focus h-control-compact whitespace-nowrap border-b-2 px-3 text-ds-control transition-colors"
                :class="modelValue === tab.key ? 'border-navy font-semibold text-navy' : 'border-transparent text-ink-muted hover:text-ink'"
                @click="seleccionar(tab.key)"
                @keydown="alTeclear($event, i)"
            >
                {{ tab.label }}
            </button>
        </div>
    </div>
</template>
