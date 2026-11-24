<script setup>
// Taller (owner/admin) y Mis órdenes (mecánico): tablero operativo de cinco etapas.
// El backend decide qué órdenes ve cada rol (scope), qué estados puede asignar (estadosEditables) y
// la autorización. La vista solo representa esos datos; no calcula dinero ni reglas de transición.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import InlineStatus from '@/Components/UI/InlineStatus.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { orderStatusVariant } from '@/Support/orderStatus.js';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    statuses:         Array,
    estadosEditables: Array,
    ordersByStatus:   Object,
    conteos:          Object,
    isAdmin:          Boolean,
});

const { t, te } = useI18n();

// Cinco etapas operativas del tablero. Entregado/Rechazado no son columnas (viven en Órdenes).
const FLUJO_SLUGS  = ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

const estadoLabel = (slug) => (te(`estadoUi.${slug}`) ? t(`estadoUi.${slug}`) : slug);

const columnas = computed(() => FLUJO_SLUGS.map((slug) => {
    const estado = (props.statuses ?? []).find((s) => s.slug === slug);
    return {
        slug,
        statusId: estado?.id ?? null,
        label: estadoLabel(slug),
        variant: orderStatusVariant(slug),
        total: props.conteos?.[slug] ?? 0,
    };
}));

const totalActivas = computed(() => columnas.value.reduce((suma, c) => suma + c.total, 0));

const tarjetas = (statusId) => (statusId == null ? [] : (props.ordersByStatus?.[statusId] ?? []));

// Opciones de cambio de estado: exactamente las que el backend deja asignar a este rol.
const editables = computed(() => new Set(props.estadosEditables ?? []));
const opcionesOperativas = computed(() => (props.statuses ?? []).filter(
    (s) => FLUJO_SLUGS.includes(s.slug) && editables.value.has(s.slug)
));

// Si la orden ya está en un estado que este rol no puede asignar, se muestra como opción deshabilitada
// para que el select refleje el estado real sin permitir elegirlo de nuevo.
const opcionesDe = (order) => {
    const actual = (props.statuses ?? []).find((s) => s.id === order.status_id);
    const incluye = opcionesOperativas.value.some((s) => s.id === order.status_id);
    return incluye || !actual ? [] : [actual];
};

function cambiarEstado(order, valor) {
    const statusId = Number(valor);
    if (statusId === order.status_id) return;
    router.patch(route('repair-orders.status.update', order.id), { status_id: statusId }, { preserveScroll: true });
}

const vehiculo = (o) => {
    const v = o.recepcion?.vehicle;
    // Marca y modelo ya resueltos en el servidor (catálogo o texto histórico).
    return [v?.display_brand, v?.display_model, v?.year ? `(${v.year})` : null].filter(Boolean).join(' ') || 'S/M';
};
const cliente = (o) => [o.recepcion?.client?.first_name, o.recepcion?.client?.last_name].filter(Boolean).join(' ') || '—';
const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }) : '—');

const titulo = computed(() => (props.isAdmin ? t('tallerUi.titulo') : t('tallerUi.tituloMecanico')));
const descripcion = computed(() => (props.isAdmin ? t('tallerUi.descAdmin') : t('tallerUi.descMecanico')));
</script>

<template>
    <Head :title="titulo" />

    <AppShellLayout>
        <PageHeader :title="titulo" :description="descripcion" />

        <div class="mt-6 min-w-0 space-y-4">
            <EmptyState
                v-if="totalActivas === 0"
                :title="isAdmin ? $t('tallerUi.sinOrdenes') : $t('tallerUi.sinOrdenesMecanico')"
            />

            <p class="text-ds-caption text-ink-muted xl:hidden">{{ $t('tallerUi.scrollHint') }}</p>

            <div class="relative min-w-0 max-w-full overflow-x-auto pb-2" role="region" :aria-label="titulo" tabindex="0">
                <div class="grid auto-cols-[minmax(240px,1fr)] grid-flow-col gap-3 xl:auto-cols-auto xl:grid-flow-row xl:grid-cols-5">
                    <section v-for="col in columnas" :key="col.slug" class="flex min-w-0 flex-col gap-3" :aria-labelledby="`col-${col.slug}`">
                        <header class="flex items-center justify-between gap-2 px-1">
                            <h2 :id="`col-${col.slug}`" class="min-w-0">
                                <BaseBadge :variant="col.variant">{{ col.label }}</BaseBadge>
                            </h2>
                            <span class="shrink-0 tabular-nums text-ds-label font-semibold text-ink-muted">{{ col.total }}</span>
                        </header>

                        <div v-if="tarjetas(col.statusId).length === 0"
                            class="rounded-lg border border-dashed border-line-strong px-3 py-4 text-center text-ds-caption text-ink-muted">
                            {{ $t('tallerUi.columnaVacia') }}
                        </div>

                        <article
                            v-for="order in tarjetas(col.statusId)"
                            :key="order.id"
                            class="space-y-2 rounded-xl border border-line bg-surface p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 truncate font-semibold text-navy">{{ order.folio ?? '#' + order.id }}</p>
                                <span class="shrink-0 whitespace-nowrap text-ds-caption text-ink-muted">{{ fechaCorta(order.created_at) }}</span>
                            </div>

                            <div class="min-w-0">
                                <p class="font-mono text-ds-label uppercase text-ink-secondary">{{ order.recepcion?.vehicle?.plate ?? '—' }}</p>
                                <p class="truncate text-ds-caption text-ink-muted">{{ vehiculo(order) }}</p>
                            </div>

                            <p v-if="isAdmin" class="truncate text-ds-body text-ink">
                                <span class="text-ds-caption text-ink-muted">{{ $t('tallerUi.cliente') }}: </span>{{ cliente(order) }}
                            </p>

                            <p class="truncate text-ds-caption text-ink-secondary">
                                <span class="text-ds-caption text-ink-muted">{{ $t('tallerUi.mecanico') }}: </span>{{ order.mechanic?.name ?? $t('ordenesLista.sinMecanico') }}
                            </p>

                            <div v-if="isAdmin && order.pendiente_autorizacion > 0">
                                <InlineStatus variant="warning">{{ $t('ordenesLista.autorizacionPendiente') }}</InlineStatus>
                            </div>


                            <div>
                                <label :for="`estado-${order.id}`" class="sr-only">{{ $t('tallerUi.cambiarEstado') }}</label>
                                <select
                                    :id="`estado-${order.id}`"
                                    :value="order.status_id"
                                    class="ds-focus h-control w-full rounded-lg border border-line bg-surface px-2 text-ds-control text-ink"
                                    @change="cambiarEstado(order, $event.target.value)"
                                >
                                    <option v-for="s in opcionesDe(order)" :key="'actual-' + s.id" :value="s.id" disabled>{{ estadoLabel(s.slug) }}</option>
                                    <option v-for="s in opcionesOperativas" :key="s.id" :value="s.id">{{ estadoLabel(s.slug) }}</option>
                                </select>
                            </div>

                            <div class="flex justify-end pt-1">
                                <Link
                                    :href="route('repair-orders.show', order.id)"
                                    class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                                >{{ $t('tallerUi.verOrden') }}</Link>
                            </div>
                        </article>
                    </section>
                </div>
            </div>
        </div>
    </AppShellLayout>
</template>
