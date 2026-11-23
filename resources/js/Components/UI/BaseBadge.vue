<script setup>
// Badge: estado de una entidad (DS-05). Variantes semánticas, sin colores por pantalla.
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'info', 'warning', 'success', 'danger'].includes(v),
    },
});

const VARIANTES = {
    neutral: 'bg-neutral-soft text-neutral',
    info: 'bg-info-soft text-info-text',
    warning: 'bg-warning-soft text-warning-text',
    success: 'bg-success-soft text-success-text',
    danger: 'bg-danger-soft text-danger-text',
};

const clases = computed(() => VARIANTES[props.variant]);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-ds-label font-semibold whitespace-nowrap"
        :class="clases"
    >
        <slot />
    </span>
</template>
