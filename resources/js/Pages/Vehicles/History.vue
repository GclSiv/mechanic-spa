<script setup>
// Expediente técnico de un vehículo (solo admin/owner). Datos resueltos por VehicleHistoryController;
// esta vista solo los presenta. Montos y saldos vienen del backend; no se calculan aquí.
import { computed } from 'vue';
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import Stat from '@/Components/UI/Stat.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    vehicle: Object,
    resumen: Object,
    visitas: Array,
});

const { t, te, locale } = useI18n();

const localeFecha = computed(() => (locale.value === 'en' ? 'en-US' : 'es-MX'));

function fmtFecha(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return Number.isNaN(d.getTime())
        ? '—'
        : d.toLocaleDateString(localeFecha.value, { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtMoneda(n) {
    return new Intl.NumberFormat(localeFecha.value, { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
}

function fmtKm(n) {
    return n === null || n === undefined ? t('vehiculosUi.sinDato') : `${new Intl.NumberFormat(localeFecha.value).format(n)} km`;
}

function fmtCantidad(n) {
    return Number(n || 0).toFixed(2);
}

const tituloVehiculo = computed(() => [props.vehicle.marca, props.vehicle.modelo, props.vehicle.year].filter(Boolean).join(' ') || '—');

function varianteOrden(orden) {
    if (!orden) return 'neutral';
    if (orden.rechazada) return 'danger';
    if (orden.terminada) return 'success';
    return 'info';
}

// Nombre de estado desde el catálogo i18n (estadoUi); si no existe, el nombre guardado en el backend.
function nombreEstado(slug, nombre) {
    return slug && te(`estadoUi.${slug}`) ? t(`estadoUi.${slug}`) : (nombre || '—');
}

function estadoVisita(visita) {
    return visita.orden ? nombreEstado(visita.orden.slug, visita.orden.estado) : t('vehiculosUi.sinOrden');
}
</script>

<template>
    <Head :title="t('vehiculosUi.historialTitulo') + ' · ' + tituloVehiculo" />

    <AppShellLayout>
        <PageHeader
            :title="tituloVehiculo"
            :description="[
                t('vehiculosUi.placa') + ' ' + (vehicle.plate || '—'),
                t('vehiculosUi.vin') + ' ' + (vehicle.vin || t('vehiculosUi.sinVin')),
                t('clasificacionUi.tipo') + ': ' + (vehicle.tipo || t('clasificacionUi.tipoNoEspecificado')),
                vehicle.propietario ? t('vehiculosUi.propietario') + ': ' + vehicle.propietario.nombre : null,
            ].filter(Boolean).join(' · ')"
        >
            <Link
                v-if="vehicle.propietario"
                :href="route('clients.show', vehicle.propietario.id)"
                class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2"
            >{{ t('vehiculosUi.verCliente') }}</Link>
            <Link
                :href="vehicle.nueva_recepcion_url"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('vehiculosUi.nuevaRecepcion') }}</Link>
        </PageHeader>

        <section class="mt-6" :aria-label="t('vehiculosUi.resumen')">
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <Stat :label="t('vehiculosUi.visitasTotales')" :value="resumen.visitas_totales" />
                <Stat :label="t('vehiculosUi.ultimaVisita')" :value="fmtFecha(resumen.ultima_visita)" />
                <Stat :label="t('vehiculosUi.ultimoKilometraje')" :value="fmtKm(resumen.ultimo_kilometraje)" />
                <Stat
                    :label="t('vehiculosUi.estadoUltimaOrden')"
                    :value="resumen.estado_ultima_orden ? nombreEstado(resumen.estado_ultima_orden_slug, resumen.estado_ultima_orden) : t('vehiculosUi.sinOrden')"
                />
            </div>
        </section>

        <section class="mt-8" :aria-label="t('vehiculosUi.timeline')">
            <h2 class="text-ds-section-title text-navy">{{ t('vehiculosUi.timeline') }}</h2>

            <EmptyState
                v-if="visitas.length === 0"
                class="mt-4"
                :title="t('vehiculosUi.vacioTitulo')"
                :description="t('vehiculosUi.vacioDescripcion')"
            >
                <Link
                    :href="vehicle.nueva_recepcion_url"
                    class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
                >{{ t('vehiculosUi.nuevaRecepcion') }}</Link>
            </EmptyState>

            <ol v-else class="mt-4 space-y-4">
                <li
                    v-for="visita in visitas"
                    :key="visita.id"
                    class="rounded-xl border border-line bg-surface p-5"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-ds-section-title text-navy">
                                {{ t('vehiculosUi.recepcionNumero', { n: visita.numero }) }}
                                <span class="text-ds-body font-normal text-ink-muted">· {{ fmtFecha(visita.fecha) }}</span>
                            </p>
                            <p v-if="visita.orden?.folio" class="text-ds-caption text-ink-muted">
                                {{ t('vehiculosUi.orden') }} {{ visita.orden.folio }}
                            </p>
                        </div>
                        <BaseBadge :variant="varianteOrden(visita.orden)">{{ estadoVisita(visita) }}</BaseBadge>
                    </div>

                    <dl class="mt-4 grid grid-cols-1 gap-3 text-ds-body sm:grid-cols-2">
                        <div>
                            <dt class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.kilometraje') }}</dt>
                            <dd class="text-ink">{{ fmtKm(visita.kilometraje) }}</dd>
                        </div>
                        <div>
                            <dt class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.problema') }}</dt>
                            <dd class="text-ink">{{ visita.problema || t('vehiculosUi.sinProblema') }}</dd>
                        </div>
                        <template v-if="visita.orden">
                            <div>
                                <dt class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.mecanico') }}</dt>
                                <dd class="text-ink">{{ visita.orden.mecanico || '—' }}</dd>
                            </div>
                            <div v-if="!visita.orden.rechazada">
                                <dt class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.ejecutores') }}</dt>
                                <dd class="text-ink">
                                    {{ visita.orden.ejecutores.length ? visita.orden.ejecutores.join(', ') : t('vehiculosUi.sinEjecutor') }}
                                </dd>
                            </div>
                        </template>
                    </dl>

                    <div v-if="visita.orden && visita.orden.rechazada" class="mt-4 rounded-lg bg-danger-soft px-4 py-3 text-ds-body text-danger-text">
                        {{ t('vehiculosUi.rechazadaAviso') }}
                    </div>

                    <template v-else-if="visita.orden">
                        <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                            <div>
                                <h3 class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.trabajos') }}</h3>
                                <ul v-if="visita.orden.trabajos.length" class="mt-2 space-y-1 text-ds-body text-ink">
                                    <li v-for="(tr, i) in visita.orden.trabajos" :key="'t' + i" class="flex flex-wrap justify-between gap-2">
                                        <span>{{ tr.descripcion }}</span>
                                        <span class="text-ink-muted">
                                            {{ fmtCantidad(tr.cantidad) }}<span v-if="tr.ejecutor"> · {{ tr.ejecutor }}</span>
                                        </span>
                                    </li>
                                </ul>
                                <p v-else class="mt-2 text-ds-body text-ink-muted">{{ t('vehiculosUi.sinTrabajos') }}</p>
                            </div>
                            <div>
                                <h3 class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.refacciones') }}</h3>
                                <ul v-if="visita.orden.refacciones.length" class="mt-2 space-y-1 text-ds-body text-ink">
                                    <li v-for="(rf, i) in visita.orden.refacciones" :key="'r' + i" class="flex flex-wrap justify-between gap-2">
                                        <span>{{ rf.codigo ? rf.codigo + ' · ' : '' }}{{ rf.descripcion }}</span>
                                        <span class="text-ink-muted">{{ fmtCantidad(rf.cantidad) }}</span>
                                    </li>
                                </ul>
                                <p v-else class="mt-2 text-ds-body text-ink-muted">{{ t('vehiculosUi.sinRefacciones') }}</p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <h3 class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.bitacora') }}</h3>
                            <ul v-if="visita.orden.notas.length" class="mt-2 space-y-2 text-ds-body text-ink">
                                <li v-for="(n, i) in visita.orden.notas" :key="'n' + i" class="rounded-lg bg-surface-2 px-3 py-2">
                                    <span class="text-ds-caption text-ink-muted">{{ fmtFecha(n.fecha) }}<span v-if="n.autor"> · {{ n.autor }}</span></span>
                                    <p>{{ n.texto }}</p>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-ds-body text-ink-muted">{{ t('vehiculosUi.sinNotas') }}</p>
                        </div>

                        <div v-if="visita.orden.finanzas" class="mt-5 grid grid-cols-2 gap-3 rounded-lg border border-line p-4 text-ds-body sm:grid-cols-4">
                            <div>
                                <p class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.total') }}</p>
                                <p class="font-semibold text-navy">{{ fmtMoneda(visita.orden.finanzas.total) }}</p>
                            </div>
                            <div>
                                <p class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.pagado') }}</p>
                                <p class="text-ink">{{ fmtMoneda(visita.orden.finanzas.pagado) }}</p>
                            </div>
                            <div>
                                <p class="text-ds-label uppercase text-ink-muted">{{ t('vehiculosUi.saldo') }}</p>
                                <p class="text-ink">{{ fmtMoneda(visita.orden.finanzas.saldo) }}</p>
                            </div>
                        </div>
                    </template>

                    <div class="mt-5 flex flex-wrap gap-4 border-t border-line pt-4 text-ds-body font-semibold">
                        <Link :href="route('recepcion.show', visita.id)" class="ds-focus text-navy underline-offset-2 hover:underline">
                            {{ t('vehiculosUi.verRecepcion') }}
                        </Link>
                        <Link
                            v-if="visita.orden"
                            :href="route('repair-orders.show', visita.orden.id)"
                            class="ds-focus text-navy underline-offset-2 hover:underline"
                        >{{ t('vehiculosUi.verOrden') }}</Link>
                    </div>
                </li>
            </ol>
        </section>
    </AppShellLayout>
</template>
