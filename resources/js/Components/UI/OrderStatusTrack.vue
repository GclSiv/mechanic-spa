<script setup>
// Ciclo de vida del taller. Representa el estado actual dentro del flujo normal:
// completed (anterior), current (actual) y future (posterior). No es historial ni fechas de transición.
// La etapa "current" usa el mismo slug que el badge del encabezado, así ambos siempre coinciden.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import { estadosDelLifecycle } from '@/Support/orderStatus.js';

const props = defineProps({
    slug: { type: String, default: null },
});

const { t, te } = useI18n();
const etiqueta = (slug) => (te(`estadoUi.${slug}`) ? t(`estadoUi.${slug}`) : slug);
const rechazada = computed(() => props.slug === 'rechazado');
const etapas = computed(() => estadosDelLifecycle(props.slug));

const ESTILO = {
    completed: 'border-success bg-success-soft text-success-text',
    current: 'border-navy bg-navy/5 text-navy',
    future: 'border-line bg-surface-2 text-ink-muted',
};
const TEXTO_SR = {
    completed: 'expediente.etapaCompletada',
    current: 'expediente.etapaActual',
    future: 'expediente.etapaFutura',
};
</script>

<template>
    <section class="rounded-xl border border-line bg-surface p-4" :aria-label="$t('expediente.lifecycle')">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('expediente.lifecycle') }}</h2>
            <BaseBadge v-if="rechazada" variant="danger">{{ etiqueta('rechazado') }}</BaseBadge>
        </div>

        <p v-if="rechazada" class="mb-3 text-ds-body text-ink-secondary">{{ $t('expediente.rechazadoFuera') }}</p>

        <ol class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
            <li
                v-for="(etapa, i) in etapas"
                :key="etapa.slug"
                :aria-current="etapa.situacion === 'current' ? 'step' : undefined"
                class="flex flex-col gap-1 rounded-lg border px-3 py-2"
                :class="ESTILO[etapa.situacion]"
                :data-situacion="etapa.situacion"
            >
                <span class="flex items-center gap-1.5 text-ds-caption">
                    <span v-if="etapa.situacion === 'completed'" aria-hidden="true">✓</span>
                    <span v-else aria-hidden="true">{{ i + 1 }}</span>
                    <span class="sr-only">{{ $t(TEXTO_SR[etapa.situacion]) }}</span>
                </span>
                <span class="text-ds-control" :class="etapa.situacion === 'current' ? 'font-semibold' : ''">{{ etiqueta(etapa.slug) }}</span>
            </li>
        </ol>
    </section>
</template>
