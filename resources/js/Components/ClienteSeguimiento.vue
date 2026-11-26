<script setup>
// Bloque de comunicación con el cliente (solo owner/admin). Todo el estado llega derivado del backend.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseBadge from '@/Components/UI/BaseBadge.vue';

const props = defineProps({
    comunicacion: { type: Object, required: true },
});

const { t, locale } = useI18n();

const VARIANTE = {
    sin_pendiente: 'neutral',
    esperando_autorizacion: 'warning',
    autorizada: 'success',
    rechazada: 'danger',
};

const cotizacion = computed(() => props.comunicacion.cotizacion_actual);
const localeFecha = () => (locale.value === 'en' ? 'en-US' : 'es-MX');
const money = (v) => '$' + Number(v ?? 0).toLocaleString(localeFecha(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fecha = (v) => (v ? new Date(v).toLocaleDateString(localeFecha(), { day: '2-digit', month: 'short', year: 'numeric' }) : '—');
const folio = (q) => q.folio ?? '#' + q.id;
</script>

<template>
    <section class="rounded-xl border border-line bg-surface p-4" aria-labelledby="cliente-seguimiento-titulo">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="cliente-seguimiento-titulo" class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">
                {{ $t('comunicacion.titulo') }}
            </h2>
            <BaseBadge :variant="VARIANTE[comunicacion.autorizacion] ?? 'neutral'">
                {{ $t('comunicacion.estado.' + comunicacion.autorizacion) }}<span v-if="comunicacion.adicional_rechazado"> · {{ $t('comunicacion.adicionalRechazado') }}</span>
            </BaseBadge>
        </div>

        <dl class="mt-3 grid gap-3 text-ds-body sm:grid-cols-2">
            <div class="min-w-0">
                <dt class="text-ds-caption text-ink-muted">{{ $t('comunicacion.cliente') }}</dt>
                <dd class="truncate text-ink">{{ comunicacion.cliente_nombre ?? $t('expediente.sinDato') }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ds-caption text-ink-muted">{{ $t('comunicacion.vehiculo') }}</dt>
                <dd class="truncate text-ink">{{ comunicacion.vehiculo ?? $t('expediente.sinDato') }}</dd>
            </div>
        </dl>

        <div v-if="cotizacion" class="mt-4 rounded-lg bg-surface-2 p-3 text-ds-body">
            <p class="font-semibold text-ink">
                {{ $t('comunicacion.cotizacionActual', { folio: folio(cotizacion), version: cotizacion.version, tipo: $t('comunicacion.kind.' + cotizacion.kind) }) }}
                <span class="font-normal text-ink-muted">· {{ $t('comunicacion.quoteStatus.' + cotizacion.status) }}</span>
            </p>
            <dl class="mt-2 grid gap-2 sm:grid-cols-4">
                <div>
                    <dt class="text-ds-caption text-ink-muted">{{ $t('comunicacion.total') }}</dt>
                    <dd class="text-ink">{{ money(cotizacion.total) }}</dd>
                </div>
                <div>
                    <dt class="text-ds-caption text-ink-muted">{{ $t('comunicacion.enviada') }}</dt>
                    <dd class="text-ink">{{ fecha(cotizacion.sent_at) }}</dd>
                </div>
                <div v-if="cotizacion.responded_at">
                    <dt class="text-ds-caption text-ink-muted">{{ $t('comunicacion.respuesta') }}</dt>
                    <dd class="text-ink">{{ fecha(cotizacion.responded_at) }}</dd>
                </div>
            </dl>
            <p v-if="cotizacion.status === 'rechazada' && cotizacion.rejection_reason" class="mt-2 text-ink">
                {{ $t('comunicacion.motivo') }}: {{ cotizacion.rejection_reason }}
            </p>
        </div>
        <p v-else class="mt-4 text-ds-caption text-ink-muted">{{ $t('comunicacion.sinCotizacion') }}</p>

        <p class="mt-3 text-ds-caption text-ink-muted">{{ $t('comunicacion.historial', { n: comunicacion.total_cotizaciones }) }}</p>
    </section>
</template>
