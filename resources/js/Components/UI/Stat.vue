<script setup>
// Indicador (KPI) del Design System. Tono semántico por variante; los valores de tono se mapean aquí, no en las pantallas.
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'success', 'warning', 'danger'].includes(v),
    },
    href: { type: String, default: null },
});

const TONOS = {
    neutral: { caja: 'border-line bg-surface', etiqueta: 'text-ink-muted', valor: 'text-navy' },
    success: { caja: 'border-line bg-surface', etiqueta: 'text-success-text', valor: 'text-navy' },
    warning: { caja: 'border-line bg-warning-soft', etiqueta: 'text-warning-text', valor: 'text-warning-text' },
    danger: { caja: 'border-line bg-danger-soft', etiqueta: 'text-danger-text', valor: 'text-danger-text' },
};

const tono = computed(() => TONOS[props.tone] ?? TONOS.neutral);
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href || undefined"
        class="block rounded-xl border p-4"
        :class="[tono.caja, href ? 'ds-focus transition-colors hover:bg-surface-2' : '']"
    >
        <p class="text-ds-label" :class="tono.etiqueta">{{ label }}</p>
        <p class="mt-1 text-ds-metric tabular-nums" :class="tono.valor">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-ds-caption text-ink-muted">{{ hint }}</p>
    </component>
</template>
