<script setup>
// Expediente del cliente (solo admin/owner). Datos ya resueltos por ClientController::show; esta vista solo los renderiza.
// Montos financieros y saldos vienen del backend; no se calculan en Vue.
import { computed, ref } from 'vue';
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import ContentTabs from '@/Components/UI/ContentTabs.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import CotizacionesResumen from '@/Components/Clients/CotizacionesResumen.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    client:    { type: Object, required: true },
    resumen:   { type: Object, required: true },
    vehiculos: { type: Array, default: () => [] },
    resumen_financiero: { type: Object, default: () => ({}) },
    servicios: { type: Object, default: null },
});

const { t } = useI18n();

const tabs = computed(() => [
    { key: 'resumen',   label: t('clientesUi.resumen') },
    { key: 'vehiculos', label: t('clientesUi.tabVehiculos') },
    { key: 'servicios', label: t('clientesUi.tabServicios') },
]);
const activa = ref('resumen');

const nombre = () => `${props.client.first_name ?? ''} ${props.client.last_name ?? ''}`.trim();
const dato = (v) => v || '—';
// Formateo de presentación: el backend ya entrega los montos; aquí solo se muestran.
const mxn = (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0));

const tarjetasFinancieras = computed(() => [
    { clave: 'total_autorizado',       etiqueta: t('clientesUi.finAutorizado'),  nota: t('clientesUi.finAutorizadoNota') },
    { clave: 'total_pagado',           etiqueta: t('clientesUi.finPagado'),      nota: t('clientesUi.finPagadoNota') },
    { clave: 'saldo_autorizado',       etiqueta: t('clientesUi.finSaldo'),       nota: t('clientesUi.finSaldoNota') },
    { clave: 'pendiente_autorizacion', etiqueta: t('clientesUi.finPendiente'),   nota: t('clientesUi.finPendienteNota') },
    { clave: 'excedente_registrado',   etiqueta: t('clientesUi.finExcedente'),   nota: t('clientesUi.finExcedenteNota') },
]);

const vehiculoTexto = (v) => {
    if (!v) return t('clientesUi.vehiculoNoRegistrado');
    const auto = [v.marca, v.modelo, v.year].filter(Boolean).join(' ');
    return [auto, v.plate].filter(Boolean).join(' · ') || t('clientesUi.vehiculoNoRegistrado');
};
// Solo resalta visualmente; el monto lo calcula el backend.
const pendienteClase = (v) => Number(v) > 0 ? 'text-danger-text font-semibold' : 'text-ink-muted';

const abiertas = ref({});
const alternarCotizaciones = (id) => { abiertas.value[id] = !abiertas.value[id]; };
const irPagina = (url) => {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true, only: ['servicios'] });
};

const claseEstado = (estado) => estado?.color_class ?? 'bg-surface-2 text-ink-secondary';
</script>

<template>
    <Head :title="t('clientesUi.expedienteTitulo') + ' · ' + nombre()" />

    <AppShellLayout>
        <Link :href="route('clients.index')" class="ds-focus inline-flex h-control-compact items-center rounded-md text-ds-control font-semibold text-ink-secondary hover:text-navy">
            ← {{ t('clientesUi.volverDirectorio') }}
        </Link>

        <PageHeader class="mt-3" :title="nombre() || '—'" :description="t('clientesUi.clienteNumero', { n: dato(client.client_number) })">
            <Link
                :href="route('recepcion.create', { client_id: client.id })"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('clientesUi.nuevaRecepcion') }}</Link>
        </PageHeader>

        <!-- Datos de contacto: siempre visibles para admin/owner (la protección vive en el backend) -->
        <dl class="mt-6 grid min-w-0 grid-cols-1 gap-x-6 gap-y-3 rounded-xl border border-line bg-surface p-4 text-ds-body sm:grid-cols-3">
            <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.telefono') }}</dt><dd class="break-words text-ink">{{ dato(client.phone) }}</dd></div>
            <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.correo') }}</dt><dd class="break-all text-ink">{{ dato(client.email) }}</dd></div>
            <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.rfc') }}</dt><dd class="break-all font-mono uppercase text-ink">{{ dato(client.rfc) }}</dd></div>
        </dl>

        <div class="mt-6 min-w-0 space-y-6">
            <ContentTabs v-model="activa" :tabs="tabs" :label="t('clientesUi.tabsExpediente')" />

            <div :id="`panel-${activa}`" role="tabpanel" :aria-labelledby="`tab-${activa}`" class="min-w-0 space-y-6">

            <!-- Resumen financiero: montos ya calculados por el backend (EstadosFinancierosOrdenesAction) -->
            <div v-if="activa === 'resumen'" class="grid min-w-0 grid-cols-2 gap-3 lg:grid-cols-5">
                <div v-for="tj in tarjetasFinancieras" :key="tj.clave" class="min-w-0 rounded-xl border border-line bg-surface p-4">
                    <p class="text-ds-label uppercase text-ink-muted">{{ tj.etiqueta }}</p>
                    <p class="break-words text-ds-section-title text-navy tabular-nums">{{ mxn(resumen_financiero[tj.clave]) }}</p>
                    <p class="mt-1 text-ds-caption leading-snug text-ink-muted">{{ tj.nota }}</p>
                </div>
            </div>

            <div v-if="activa === 'resumen'" class="grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="min-w-0 rounded-xl border border-line bg-surface p-5 lg:col-span-2">
                    <h2 class="mb-3 text-ds-label uppercase text-ink-muted">{{ t('clientesUi.datosGenerales') }}</h2>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-ds-body sm:grid-cols-2">
                        <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.direccion') }}</dt><dd class="break-words text-ink">{{ dato(client.address) }}</dd></div>
                        <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.pais') }}</dt><dd class="text-ink">{{ dato(client.country) }}</dd></div>
                    </dl>

                    <h2 class="mb-3 mt-6 text-ds-label uppercase text-ink-muted">{{ t('clientesUi.datosFiscales') }}</h2>
                    <ul v-if="client.fiscal_profiles.length" class="space-y-2 text-ds-body">
                        <li v-for="f in client.fiscal_profiles" :key="f.id" class="min-w-0 rounded-lg bg-surface-2 px-3 py-2">
                            <span class="break-all font-mono font-semibold uppercase text-navy">{{ f.rfc }}</span>
                            <span class="break-words text-ink-secondary"> · {{ dato(f.razon_social) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-ds-body text-ink-muted">{{ t('clientesUi.sinPerfilFiscal') }}</p>
                </div>

                <div class="min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5">
                    <h2 class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.resumen') }}</h2>
                    <div>
                        <p class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.vehiculos') }}</p>
                        <p class="text-ds-page-title text-navy tabular-nums">{{ resumen.vehiculos }}</p>
                    </div>
                    <div>
                        <p class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.ordenes') }}</p>
                        <p class="text-ds-page-title text-navy tabular-nums">{{ resumen.ordenes }}</p>
                    </div>
                    <div>
                        <p class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.ultimaVisita') }}</p>
                        <p class="text-ds-body text-ink">{{ resumen.ultima_visita ?? t('clientesUi.sinVisitas') }}</p>
                    </div>
                </div>
            </div>

            <!-- Vehículos: tarjetas (la base es legible en cualquier ancho) -->
            <div v-else-if="activa === 'vehiculos'">
                <EmptyState v-if="vehiculos.length === 0" :title="t('clientesUi.sinVehiculosRegistrados')" />

                <ul v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <li v-for="v in vehiculos" :key="v.id" class="flex min-w-0 flex-col gap-3 rounded-xl border border-line bg-surface p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words font-semibold text-navy">{{ dato(v.marca) }} {{ dato(v.modelo) }}</p>
                                <p class="text-ds-caption text-ink-muted">{{ dato(v.year) }}</p>
                            </div>
                            <span class="shrink-0 rounded bg-surface-2 px-2 py-1 font-mono text-ds-caption font-semibold uppercase text-ink-secondary">{{ dato(v.plate) }}</span>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-ds-body">
                            <div class="min-w-0 col-span-2"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.vin') }}</dt><dd class="break-all font-mono text-ds-caption text-ink">{{ dato(v.vin) }}</dd></div>
                            <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.color') }}</dt><dd class="text-ink">{{ dato(v.color) }}</dd></div>
                            <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.kilometraje') }}</dt><dd class="text-ink tabular-nums">{{ v.kilometraje != null ? t('clientesUi.kmValor', { n: Number(v.kilometraje).toLocaleString('es-MX') }) : '—' }}</dd></div>
                            <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.visitas') }}</dt><dd class="text-ink tabular-nums">{{ v.visitas }}</dd></div>
                            <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.ultimaVisita') }}</dt><dd class="text-ink">{{ dato(v.ultima_visita) }}</dd></div>
                        </dl>

                        <div class="mt-auto flex flex-col gap-2 border-t border-line pt-3 sm:flex-row">
                            <Link :href="route('vehicles.history', v.id)" class="ds-focus inline-flex h-control flex-1 items-center justify-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2">{{ t('clientesUi.historial') }}</Link>
                            <Link :href="route('recepcion.create', { client_id: client.id, vehicle_id: v.id })" class="ds-focus inline-flex h-control flex-1 items-center justify-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90">{{ t('clientesUi.nuevaVisita') }}</Link>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Servicios: órdenes del cliente (página actual). Estado financiero calculado en lote en el backend -->
            <div v-else-if="activa === 'servicios'" class="min-w-0 space-y-4">
                <EmptyState v-if="!servicios || servicios.data.length === 0" :title="t('clientesUi.sinServicios')">
                    <Link :href="route('recepcion.create', { client_id: client.id })" class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90">{{ t('clientesUi.nuevaRecepcion') }}</Link>
                </EmptyState>

                <template v-else>
                    <!-- Escritorio: tabla -->
                    <div class="relative hidden min-w-0 overflow-x-auto rounded-xl border border-line bg-surface lg:block">
                        <table class="w-full min-w-[980px] text-left text-ds-body">
                            <caption class="sr-only">{{ t('clientesUi.tabServicios') }}</caption>
                            <thead class="bg-surface-2 text-ds-label uppercase text-ink-muted">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.servicio') }}</th>
                                    <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.vehiculos') }}</th>
                                    <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.fecha') }}</th>
                                    <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.estado') }}</th>
                                    <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.mecanico') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('clientesUi.tecnico') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('clientesUi.autorizado') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('clientesUi.pagado') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('clientesUi.saldo') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('clientesUi.pendiente') }}</th>
                                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ t('clientesUi.accion') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <template v-for="s in servicios.data" :key="s.id">
                                    <tr class="align-top">
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <p class="font-semibold text-navy">{{ dato(s.folio) }}</p>
                                            <button v-if="s.cotizaciones.length" type="button" @click="alternarCotizaciones(s.id)"
                                                    class="ds-focus mt-1 inline-flex min-h-[44px] items-center text-ds-caption font-semibold text-navy underline-offset-2 hover:underline">
                                                {{ abiertas[s.id] ? t('clientesUi.ocultarCotizaciones', { n: s.cotizaciones.length }) : t('clientesUi.verCotizaciones', { n: s.cotizaciones.length }) }}
                                            </button>
                                            <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('clientesUi.sinCotizaciones') }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-ink-secondary">{{ vehiculoTexto(s.vehiculo) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-ink-muted">{{ dato(s.fecha) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-ds-label uppercase" :class="claseEstado(s.estado)">
                                                {{ s.estado ? $t('status.' + s.estado.slug) : '—' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-ink-secondary">{{ s.mecanico ?? t('clientesUi.sinAsignar') }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ mxn(s.financiero.total_tecnico) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ mxn(s.financiero.total_autorizado) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ mxn(s.financiero.total_pagado) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-navy">{{ mxn(s.financiero.saldo_autorizado) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums" :class="pendienteClase(s.financiero.pendiente_autorizacion)">{{ mxn(s.financiero.pendiente_autorizacion) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <Link :href="route('repair-orders.show', s.id)" class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2">{{ t('clientesUi.abrirOrden') }}</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="abiertas[s.id] && s.cotizaciones.length" class="bg-surface-2">
                                        <td colspan="11" class="px-4 py-3">
                                            <CotizacionesResumen :cotizaciones="s.cotizaciones" />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tablet y móvil: tarjetas por servicio -->
                    <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:hidden">
                        <li v-for="s in servicios.data" :key="s.id" class="flex min-w-0 flex-col gap-3 rounded-xl border border-line bg-surface p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold text-navy">{{ dato(s.folio) }}</p>
                                    <p class="break-words text-ds-body text-ink-secondary">{{ vehiculoTexto(s.vehiculo) }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-ds-label uppercase" :class="claseEstado(s.estado)">
                                    {{ s.estado ? $t('status.' + s.estado.slug) : '—' }}
                                </span>
                            </div>

                            <div>
                                <p class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.saldoPorCobrar') }}</p>
                                <p class="text-ds-section-title font-semibold text-navy tabular-nums">{{ mxn(s.financiero.saldo_autorizado) }}</p>
                            </div>

                            <div>
                                <p class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.pendienteAutorizar') }}</p>
                                <p class="font-semibold tabular-nums" :class="pendienteClase(s.financiero.pendiente_autorizacion)">{{ mxn(s.financiero.pendiente_autorizacion) }}</p>
                            </div>

                            <dl class="grid grid-cols-3 gap-x-3 text-ds-body">
                                <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.tecnico') }}</dt><dd class="tabular-nums">{{ mxn(s.financiero.total_tecnico) }}</dd></div>
                                <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.autorizado') }}</dt><dd class="tabular-nums">{{ mxn(s.financiero.total_autorizado) }}</dd></div>
                                <div class="min-w-0"><dt class="text-ds-label uppercase text-ink-muted">{{ t('clientesUi.pagado') }}</dt><dd class="tabular-nums">{{ mxn(s.financiero.total_pagado) }}</dd></div>
                            </dl>

                            <p class="text-ds-caption text-ink-muted">{{ s.mecanico ?? t('clientesUi.sinAsignar') }} · {{ dato(s.fecha) }}</p>

                            <div>
                                <button v-if="s.cotizaciones.length" type="button" @click="alternarCotizaciones(s.id)"
                                        class="ds-focus inline-flex min-h-[44px] items-center text-ds-caption font-semibold text-navy underline-offset-2 hover:underline">
                                    {{ abiertas[s.id] ? t('clientesUi.ocultarCotizaciones', { n: s.cotizaciones.length }) : t('clientesUi.verCotizaciones', { n: s.cotizaciones.length }) }}
                                </button>
                                <p v-else class="text-ds-caption text-ink-muted">{{ t('clientesUi.sinCotizaciones') }}</p>
                                <CotizacionesResumen v-if="abiertas[s.id]" :cotizaciones="s.cotizaciones" class="mt-2" />
                            </div>

                            <Link :href="route('repair-orders.show', s.id)" class="ds-focus mt-auto inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2">{{ t('clientesUi.abrirOrden') }}</Link>
                        </li>
                    </ul>

                    <!-- Paginación: conserva cliente, pestaña y query string -->
                    <nav class="flex items-center justify-between gap-3 text-ds-caption text-ink-muted" :aria-label="t('clientesUi.paginacionServicios')">
                        <button type="button" :disabled="!servicios.prev_page_url"
                                @click="irPagina(servicios.prev_page_url)"
                                class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40">
                            {{ t('clientesUi.anterior') }}
                        </button>
                        <span aria-live="polite">{{ t('clientesUi.pagina', { actual: servicios.current_page, total: servicios.last_page }) }}</span>
                        <button type="button" :disabled="!servicios.next_page_url"
                                @click="irPagina(servicios.next_page_url)"
                                class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40">
                            {{ t('clientesUi.siguiente') }}
                        </button>
                    </nav>
                </template>
            </div>
            </div>
        </div>
    </AppShellLayout>
</template>
