<script setup>
// Cabecera del Expediente: folio real, estado, condición derivada (solo admin) y datos operativos.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InlineStatus from '@/Components/UI/InlineStatus.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    orden: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    pendiente: { type: Number, default: 0 },
});

const { t } = useI18n();

const folio = computed(() => props.orden.folio ?? 'Q-' + String(props.orden.id).padStart(4, '0'));
const vehiculo = computed(() => {
    const v = props.orden.recepcion?.vehicle;
    // Marca y modelo ya resueltos en el servidor (catálogo o texto histórico).
    return [v?.display_brand, v?.display_model, v?.year ? `(${v.year})` : null].filter(Boolean).join(' ') || t('expediente.sinDato');
});
const placa = computed(() => props.orden.recepcion?.vehicle?.plate ?? t('expediente.sinDato'));
const cliente = computed(() => {
    const c = props.orden.recepcion?.client;
    return [c?.first_name, c?.last_name].filter(Boolean).join(' ') || t('expediente.sinDato');
});
const ingreso = computed(() => (props.orden.created_at
    ? new Date(props.orden.created_at).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
    : t('expediente.sinDato')));
</script>

<template>
    <header class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-ds-label uppercase tracking-wider text-ink-muted">{{ $t('expediente.titulo') }}</p>
                <h1 class="truncate text-ds-page-title font-semibold text-navy">{{ folio }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="acciones" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <StatusBadge :orden="orden" :statuses="statuses" />
            <InlineStatus v-if="isAdmin && pendiente > 0" variant="warning">{{ $t('ordenesLista.autorizacionPendiente') }}</InlineStatus>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-ds-body md:grid-cols-5">
            <div class="min-w-0">
                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.vehiculo') }}</dt>
                <dd class="truncate text-ink">{{ vehiculo }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.placa') }}</dt>
                <dd class="font-mono uppercase text-ink">{{ placa }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.cliente') }}</dt>
                <dd class="truncate text-ink">{{ cliente }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.mecanico') }}</dt>
                <dd class="truncate text-ink">{{ orden.mechanic?.name ?? $t('taller.sinAsignar') }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.ingreso') }}</dt>
                <dd class="text-ink">{{ ingreso }}</dd>
            </div>
        </dl>
    </header>
</template>
