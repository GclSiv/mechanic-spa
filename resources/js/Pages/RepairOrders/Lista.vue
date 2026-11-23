<script setup>
// Órdenes (listado de admin/owner). El backend (RepairOrderListController) filtra, pagina y calcula el saldo.
// La vista no calcula dinero: solo muestra lo que llega por props.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import InlineStatus from '@/Components/UI/InlineStatus.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { orderStatusVariant } from '@/Support/orderStatus.js';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ordenes:               Object,
    filtros:               Object,
    estados:               Array,
    mecanicos:             Array,
    resumen:               Object,
    autorizacionPendiente: Number,
});

const { t, te } = useI18n();

// Cinco estados operativos del flujo del taller (los mismos del Dashboard). Entregado/Rechazado solo en filtro.
const FLUJO_SLUGS = ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

const estadoLabel = (slug) => (te(`estadoUi.${slug}`) ? t(`estadoUi.${slug}`) : slug);

const formatMoney = (n) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n ?? 0);

// ── Filtros: mismos parámetros que ya acepta el backend ───────────────
const CAMPOS_FILTRO = ['buscar', 'estado', 'mecanico', 'saldo', 'desde', 'hasta'];
const f = ref(Object.fromEntries(CAMPOS_FILTRO.map((k) => [k, props.filtros?.[k] ?? ''])));
let timer = null;

const filtrosActivos = computed(() => CAMPOS_FILTRO.some((k) => f.value[k] !== '' && f.value[k] !== null));

const aplicar = () => {
    const params = {};
    Object.entries(f.value).forEach(([k, v]) => { if (v !== '' && v !== null) params[k] = v; });
    router.get(route('repair-orders.lista'), params, { preserveState: true, preserveScroll: true, replace: true });
};

watch(() => [f.value.estado, f.value.mecanico, f.value.saldo, f.value.desde, f.value.hasta], aplicar);
watch(() => f.value.buscar, () => {
    clearTimeout(timer);
    timer = setTimeout(aplicar, 400);
});

const limpiar = () => {
    f.value = Object.fromEntries(CAMPOS_FILTRO.map((k) => [k, '']));
    aplicar();
};

// Indicador de estado: pulsarlo filtra por ese estado (usa el filtro existente); pulsarlo otra vez lo quita.
const alternarEstado = (slug) => {
    f.value.estado = f.value.estado === slug ? '' : slug;
};

const diasTexto = (n) => t('ordenesLista.hace', { n: n ?? 0 });

const pagina = (url) => router.get(url, {}, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head :title="$t('ordenesLista.title')" />

    <AppShellLayout>
        <PageHeader :title="$t('ordenesLista.title')" :description="$t('ordenesLista.description')" />

        <div class="mt-6 space-y-6">
            <!-- Resumen: cinco estados operativos y, separada, la autorización pendiente derivada -->
            <section class="rounded-xl border border-line bg-surface p-4" :aria-label="$t('ordenesLista.resumen')">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    <button
                        v-for="slug in FLUJO_SLUGS"
                        :key="slug"
                        type="button"
                        class="ds-focus flex flex-col items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-surface-2"
                        :class="f.estado === slug ? 'border-navy bg-surface-2' : 'border-line bg-surface'"
                        :aria-pressed="f.estado === slug ? 'true' : 'false'"
                        @click="alternarEstado(slug)"
                    >
                        <BaseBadge :variant="orderStatusVariant(slug)">{{ estadoLabel(slug) }}</BaseBadge>
                        <span class="text-ds-metric tabular-nums text-navy">{{ resumen?.[slug] ?? 0 }}</span>
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-line pt-4">
                    <InlineStatus variant="warning">
                        {{ $t('ordenesLista.autorizacionPendiente') }}: {{ autorizacionPendiente ?? 0 }}
                    </InlineStatus>
                    <span class="text-ds-caption text-ink-muted">{{ $t('ordenesLista.autorizacionHint') }}</span>
                </div>
            </section>

            <!-- Filtros -->
            <section class="rounded-xl border border-line bg-surface p-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12">
                    <div class="relative md:col-span-2 xl:col-span-4">
                        <label for="ordenes-buscar" class="sr-only">{{ $t('ordenesLista.buscar') }}</label>
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-muted" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                        </span>
                        <input
                            id="ordenes-buscar"
                            v-model="f.buscar"
                            type="search"
                            :placeholder="$t('ordenesLista.buscar')"
                            class="ds-focus h-control w-full rounded-lg border border-line bg-surface pl-9 pr-3 text-ds-control text-ink placeholder:text-ink-muted"
                        />
                    </div>
                    <label class="sr-only" for="ordenes-estado">{{ $t('ordenesLista.colEstado') }}</label>
                    <select id="ordenes-estado" v-model="f.estado" class="ds-focus h-control rounded-lg border border-line bg-surface px-3 text-ds-control text-ink xl:col-span-2">
                        <option value="">{{ $t('ordenesLista.todosEstados') }}</option>
                        <option v-for="e in estados" :key="e.slug" :value="e.slug">{{ estadoLabel(e.slug) }}</option>
                    </select>
                    <label class="sr-only" for="ordenes-mecanico">{{ $t('ordenesLista.colMecanico') }}</label>
                    <select id="ordenes-mecanico" v-model="f.mecanico" class="ds-focus h-control rounded-lg border border-line bg-surface px-3 text-ds-control text-ink xl:col-span-2">
                        <option value="">{{ $t('ordenesLista.todosMecanicos') }}</option>
                        <option v-for="m in mecanicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <label class="sr-only" for="ordenes-saldo">{{ $t('ordenesLista.colSaldo') }}</label>
                    <select id="ordenes-saldo" v-model="f.saldo" class="ds-focus h-control rounded-lg border border-line bg-surface px-3 text-ds-control text-ink xl:col-span-2">
                        <option value="">{{ $t('ordenesLista.cualquierSaldo') }}</option>
                        <option value="pendiente">{{ $t('ordenesLista.conSaldo') }}</option>
                        <option value="liquidado">{{ $t('ordenesLista.liquidadas') }}</option>
                    </select>
                    <div class="flex gap-2 xl:col-span-2">
                        <label class="sr-only" for="ordenes-desde">{{ $t('ordenesLista.desde') }}</label>
                        <input id="ordenes-desde" lang="es-MX" v-model="f.desde" type="date" :title="$t('ordenesLista.desde')" class="ds-focus h-control min-w-0 flex-1 rounded-lg border border-line bg-surface px-2 text-ds-control text-ink" />
                        <label class="sr-only" for="ordenes-hasta">{{ $t('ordenesLista.hasta') }}</label>
                        <input id="ordenes-hasta" lang="es-MX" v-model="f.hasta" type="date" :title="$t('ordenesLista.hasta')" class="ds-focus h-control min-w-0 flex-1 rounded-lg border border-line bg-surface px-2 text-ds-control text-ink" />
                    </div>
                </div>
                <div v-if="filtrosActivos" class="mt-3 flex justify-end">
                    <button type="button" class="ds-focus rounded-lg px-3 py-2 text-ds-control font-semibold text-navy hover:bg-surface-2" @click="limpiar">
                        {{ $t('ordenesLista.limpiar') }}
                    </button>
                </div>
            </section>

            <!-- Tabla o estado vacío -->
            <EmptyState
                v-if="!ordenes.data?.length"
                :title="filtrosActivos ? $t('ordenesLista.sinResultados') : $t('ordenesLista.sinOrdenes')"
                :description="filtrosActivos ? $t('ordenesLista.sinResultadosDesc') : $t('ordenesLista.sinOrdenesDesc')"
            />

            <section v-else class="overflow-hidden rounded-xl border border-line bg-surface">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-ds-body">
                        <thead class="border-b border-line bg-surface-2 text-ds-label text-ink-muted">
                            <tr>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold xl:px-5">{{ $t('ordenesLista.colFolio') }}</th>
                                <th scope="col" class="hidden px-4 py-3 font-semibold xl:table-cell xl:px-5">{{ $t('ordenesLista.colCliente') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold xl:px-5">{{ $t('ordenesLista.colVehiculo') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold xl:px-5">{{ $t('ordenesLista.colEstado') }}</th>
                                <th scope="col" class="hidden px-4 py-3 font-semibold xl:table-cell xl:px-5">{{ $t('ordenesLista.colMecanico') }}</th>
                                <th scope="col" class="hidden px-4 py-3 font-semibold xl:table-cell xl:px-5">{{ $t('ordenesLista.colIngreso') }}</th>
                                <th scope="col" class="hidden px-4 py-3 text-right font-semibold lg:table-cell xl:px-5">{{ $t('ordenesLista.colSaldo') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold xl:px-5">
                                    <span class="sr-only">{{ $t('ordenesLista.colAccion') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="o in ordenes.data" :key="o.id" class="hover:bg-surface-2">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-navy xl:px-5">{{ o.folio }}</td>
                                <td class="hidden px-4 py-3 text-ink-secondary xl:table-cell xl:px-5">
                                    <p class="font-semibold text-navy">{{ o.cliente }}</p>
                                </td>
                                <td class="px-4 py-3 xl:px-5">
                                    <p class="font-mono text-ds-label uppercase text-ink-secondary">{{ o.placa ?? '—' }}</p>
                                    <p class="text-ds-caption text-ink-muted">{{ o.vehiculo }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 xl:px-5">
                                    <BaseBadge :variant="orderStatusVariant(o.estado_slug)">{{ estadoLabel(o.estado_slug) }}</BaseBadge>
                                </td>
                                <td class="hidden px-4 py-3 text-ink-secondary xl:table-cell xl:px-5">{{ o.mecanico ?? $t('ordenesLista.sinMecanico') }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 xl:table-cell xl:px-5">
                                    <p class="text-ink-secondary">{{ o.entrada }}</p>
                                    <p class="text-ds-caption text-ink-muted">{{ diasTexto(o.dias) }}</p>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-right lg:table-cell xl:px-5">
                                    <p v-if="o.saldo > 0" class="font-semibold tabular-nums text-navy">{{ formatMoney(o.saldo) }}</p>
                                    <p v-else class="text-ds-label font-semibold text-success-text">{{ $t('ordenesLista.liquidada') }}</p>
                                    <div v-if="o.pendiente > 0" class="mt-1 flex justify-end">
                                        <InlineStatus variant="warning">{{ $t('ordenesLista.autorizacionPendiente') }}</InlineStatus>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right xl:px-5">
                                    <Link
                                        :href="route('repair-orders.show', o.id)"
                                        class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                                    >{{ $t('ordenesLista.verOrden') }}</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Paginación de backend (sin cargar todas las órdenes) -->
            <nav v-if="ordenes.data?.length" class="flex flex-col gap-3 text-ds-caption text-ink-muted sm:flex-row sm:items-center sm:justify-between" aria-label="Paginación">
                <div class="flex items-center gap-2">
                    <button type="button" :disabled="!ordenes.prev_page_url" class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40" @click="pagina(ordenes.prev_page_url)">
                        {{ $t('ordenesLista.anterior') }}
                    </button>
                    <button type="button" :disabled="!ordenes.next_page_url" class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40" @click="pagina(ordenes.next_page_url)">
                        {{ $t('ordenesLista.siguiente') }}
                    </button>
                </div>
                <span>{{ $t('ordenesLista.paginaDe', { actual: ordenes.current_page, total: ordenes.last_page }) }} · {{ $t('ordenesLista.cuenta', { n: ordenes.total }) }}</span>
            </nav>
        </div>
    </AppShellLayout>
</template>
