<script setup>
// InlineStatus: señal o alerta compacta dentro de contenido (DS-05).
// Diferencia estructural con BaseBadge: BaseBadge = estado de entidad; InlineStatus = condición contextual.
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'info', 'warning', 'success', 'danger'].includes(v),
    },
});

const TEXTO = {
    neutral: 'text-neutral',
    info: 'text-info-text',
    warning: 'text-warning-text',
    success: 'text-success-text',
    danger: 'text-danger-text',
};

const PUNTO = {
    neutral: 'bg-neutral',
    info: 'bg-info',
    warning: 'bg-warning',
    success: 'bg-success',
    danger: 'bg-danger',
};

const texto = computed(() => TEXTO[props.variant]);
const punto = computed(() => PUNTO[props.variant]);
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-ds-label font-semibold" :class="texto">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="punto" aria-hidden="true"></span>
        <slot />
    </span>
</template>
