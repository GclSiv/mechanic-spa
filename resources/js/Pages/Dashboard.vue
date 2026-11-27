<script setup>
import AppShellLayout from "@/Layouts/AppShellLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import Stat from "@/Components/UI/Stat.vue";
import BaseBadge from "@/Components/UI/BaseBadge.vue";
import { orderStatusVariant } from "@/Support/orderStatus.js";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    stats:            Object,
    recentRecepcions: Object,
    filters:          Object,
});

const page    = usePage();
const { t, te } = useI18n();
const isAdmin = computed(() => page.props.auth?.isAdmin === true);

const isSearching = ref(false);
const search      = ref(props.filters.search || "");
let timeout       = null;

const showPhotoModal = ref(false);
const selectedPhotos = ref([]);

const selectedRecepcionId = ref(null);
const openPhotoGallery = (rec) => {
    selectedRecepcionId.value = rec?.id ?? null;
    const photos = rec?.photos ?? [];
    selectedPhotos.value = Array.isArray(photos) ? photos : JSON.parse(photos || '[]');
    showPhotoModal.value = true;
};

watch(search, (value) => {
    isSearching.value = true;
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route("dashboard"), { search: value }, {
            preserveState: true, replace: true, preserveScroll: true,
            onFinish: () => (isSearching.value = false),
        });
    }, 500);
});

// Etiqueta de estado para el panel: usa la etiqueta corta del Dashboard si existe.
// Flujo del taller: cinco estados operativos, en el orden del proceso. "Autorización" no es un estado.
const FLUJO_SLUGS = ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];
const flujoItems = computed(() => FLUJO_SLUGS.map((slug) => ({
    slug,
    label: estadoLabel(slug),
    count: props.stats.flujo?.[slug] ?? 0,
    variant: orderStatusVariant(slug),
})));

// Detalle: admin abre la recepción (ruta solo admin); mecánico abre la orden asignada.
const enlaceDetalle = (rec) => {
    if (isAdmin.value) return route('recepcion.show', rec.id);
    return rec.repair_order ? route('repair-orders.show', rec.repair_order.id) : null;
};

const estadoLabel = (slug) => (te(`estadoUi.${slug}`) ? t(`estadoUi.${slug}`) : t(`status.${slug}`));

const formatCurrency = (val) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val ?? 0);
</script>

<template>
    <Head :title="$t('common.panelControl')" />

    <AppShellLayout>
        <PageHeader :title="isAdmin ? $t('dashboard.title') : $t('shell.miTrabajo')">
            <Link v-if="isAdmin" :href="route('recepcion.create')"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90">
                {{ $t('dashboard.nuevaRecepcion') }}
            </Link>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <!-- Indicadores principales -->
            <section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Indicadores">
                <Stat
                    :label="$t('dashboard.vehiculosEnTaller')"
                    :value="stats.ordenesActivas ?? 0"
                    :hint="$t('dashboard.enProgreso')"
                />
                <Stat
                    v-if="isAdmin"
                    :label="$t('dashboard.esperandoAutorizacion')"
                    :value="stats.esperandoAutorizacion ?? 0"
                    :hint="$t('dashboard.hintEsperandoAutorizacion')"
                    :tone="stats.esperandoAutorizacion > 0 ? 'warning' : 'neutral'"
                    :href="route('repair-orders.index')"
                />
                <Stat
                    :label="$t('dashboard.esperandoPiezas')"
                    :value="stats.esperandoPiezas ?? 0"
                    :hint="$t('dashboard.hintEsperandoPiezas')"
                    :href="route('repair-orders.index')"
                />
                <Stat
                    :label="$t('dashboard.listosRecoger')"
                    :value="stats.listosRecoger ?? 0"
                    :hint="$t('dashboard.hintListosRecoger')"
                    :href="route('repair-orders.index')"
                />

            <!-- Indicadores secundarios (misma rejilla que los principales) -->
                <Stat
                    :label="$t('dashboard.recepcionesHoy')"
                    :value="stats.today ?? 0"
                    :hint="`${$t('dashboard.de')} ${stats.total} ${$t('dashboard.totalLabel')}`"
                    :href="isAdmin ? route('recepcion.create') : null"
                />
                <Stat
                    v-if="isAdmin"
                    :label="$t('dashboard.porRecoger')"
                    :value="stats.pendingPickupCount ?? 0"
                    :hint="$t('dashboard.porRecogerDesc')"
                    :tone="stats.pendingPickupCount > 0 ? 'warning' : 'neutral'"
                    :href="route('repair-orders.index')"
                />
                <Stat
                    v-if="isAdmin"
                    :label="$t('dashboard.alertas')"
                    :value="stats.stockAlertas ?? 0"
                    :hint="stats.stockAlertas > 0 ? $t('inventario.stockBajo') : $t('dashboard.sinAlertas')"
                    :tone="stats.stockAlertas > 0 ? 'danger' : 'neutral'"
                    :href="route('parts.index')"
                />
                <Stat
                    v-if="isAdmin"
                    :label="$t('dashboard.cobradoMes')"
                    :value="formatCurrency(stats.ingresosMes)"
                    :hint="$t('dashboard.pagosRegistrados')"
                    :href="route('reports.index')"
                />
            </section>

            <!-- Flujo del taller: cinco estados operativos con conteo real del alcance visible -->
            <section aria-labelledby="flujo-taller-titulo" class="rounded-xl border border-line bg-surface p-5">
                <h2 id="flujo-taller-titulo" class="text-ds-section-title text-navy">{{ $t('dashboard.flujoTaller') }}</h2>
                <ol class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    <li v-for="item in flujoItems" :key="item.slug" class="flex flex-col gap-3 rounded-lg border border-line bg-surface-2 p-4">
                        <div class="min-w-0">
                            <BaseBadge :variant="item.variant">{{ item.label }}</BaseBadge>
                        </div>
                        <p class="text-ds-metric tabular-nums text-navy">{{ item.count }}</p>
                    </li>
                </ol>
            </section>

            <!-- Recepciones recientes -->
            <section class="overflow-hidden rounded-xl border border-line bg-surface">
                <div class="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-ds-section-title text-navy">{{ $t('dashboard.recientes') }}</h2>
                    <div class="relative w-full sm:w-72">
                        <label for="buscar-recepciones" class="sr-only">{{ $t('dashboard.buscar') }}</label>
                        <input
                            id="buscar-recepciones"
                            v-model="search"
                            type="search"
                            :placeholder="$t('dashboard.buscar')"
                            class="ds-focus h-control w-full rounded-lg border border-line bg-surface px-3 text-ds-control text-ink placeholder:text-ink-muted"
                        />
                        <span v-if="isSearching" class="absolute right-3 top-1/2 -translate-y-1/2 text-ds-caption text-ink-muted" aria-live="polite">…</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-ds-body">
                        <thead class="border-b border-line bg-surface-2 text-ds-label text-ink-muted">
                            <tr>
                                <th scope="col" class="hidden px-4 py-3 font-semibold xl:table-cell xl:px-5">#</th>
                                <th scope="col" class="px-4 py-3 font-semibold xl:px-5">{{ $t('tabla.cliente') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold xl:px-5">{{ $t('tabla.vehiculo') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold xl:px-5">{{ $t('tabla.placa') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold xl:px-5">{{ $t('tabla.estado') }}</th>
                                <th scope="col" class="hidden px-4 py-3 font-semibold xl:table-cell xl:px-5">{{ $t('tabla.fecha') }}</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-right font-semibold xl:px-5">{{ $t('tabla.acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="rec in recentRecepcions.data" :key="rec.id" class="hover:bg-surface-2">
                                <td class="hidden px-4 py-3 font-mono text-ds-caption text-ink-muted xl:table-cell xl:px-5">{{ rec.id }}</td>
                                <td class="px-4 py-3 xl:px-5">
                                    <p class="font-semibold text-navy">{{ rec.client?.first_name }} {{ rec.client?.last_name }}</p>
                                    <p class="text-ds-caption text-ink-muted">{{ rec.client?.phone ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-ink-secondary xl:px-5">
                                    {{ rec.vehicle?.display_brand ?? 'S/M' }} {{ rec.vehicle?.display_model ?? '' }}
                                    <span class="text-ds-caption text-ink-muted">({{ rec.vehicle?.year ?? '—' }})</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-ds-label uppercase text-ink-secondary xl:px-5">{{ rec.vehicle?.plate ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 xl:px-5">
                                    <BaseBadge v-if="rec.repair_order?.status" :variant="orderStatusVariant(rec.repair_order.status.slug)">
                                        {{ estadoLabel(rec.repair_order.status.slug) }}
                                    </BaseBadge>
                                    <BaseBadge v-else variant="neutral">{{ $t('dashboard.sinOrden') }}</BaseBadge>
                                </td>
                                <td class="hidden px-4 py-3 text-ds-caption text-ink-muted xl:table-cell xl:px-5">
                                    {{ new Date(rec.created_at).toLocaleDateString('es-MX') }}
                                </td>
                                <td class="px-4 py-3 xl:px-5">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            :aria-label="$t('dashboard.verFotos')"
                                            :title="$t('dashboard.verFotos')"
                                            class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                                            @click="openPhotoGallery(rec)"
                                        >{{ $t('dashboard.fotos') }}</button>
                                        <Link
                                            v-if="enlaceDetalle(rec)"
                                            :href="enlaceDetalle(rec)"
                                            class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                                        >{{ $t('acciones.ver') }}</Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!recentRecepcions.data?.length">
                                <td colspan="7" class="px-4 py-12 xl:px-5 text-center text-ds-body text-ink-muted">
                                    {{ search ? $t('dashboard.sinResultadosBusqueda') + ' "' + search + '"' : $t('dashboard.sinRecepciones') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-line px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link :href="route('recepcion.index')" class="ds-focus rounded text-ds-control font-semibold text-navy hover:underline">
                        {{ $t('dashboard.verTodos') }}
                    </Link>
                    <nav v-if="recentRecepcions.last_page > 1" class="flex flex-wrap items-center gap-2 text-ds-caption text-ink-muted" aria-label="Paginación">
                        <span>{{ $t('dashboard.mostrando') }} {{ recentRecepcions.from }}–{{ recentRecepcions.to }} {{ $t('dashboard.de') }} {{ recentRecepcions.total }} {{ $t('dashboard.recepciones') }}</span>
                        <div class="flex gap-1">
                            <template v-for="(link, idx) in recentRecepcions.links" :key="idx">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    :aria-current="link.active ? 'page' : undefined"
                                    class="ds-focus inline-flex h-control-compact min-w-[2.25rem] items-center justify-center rounded-lg border px-3 text-ds-control"
                                    :class="link.active ? 'border-navy bg-navy font-semibold text-white' : 'border-line bg-surface text-ink-secondary hover:bg-surface-2'"
                                >{{ idx === 0 ? $t('dashboard.paginaAnterior') : idx === recentRecepcions.links.length - 1 ? $t('dashboard.paginaSiguiente') : link.label }}</Link>
                                <span
                                    v-else
                                    class="inline-flex h-control-compact min-w-[2.25rem] items-center justify-center rounded-lg border border-line px-3 text-ds-control text-ink-muted"
                                >{{ idx === 0 ? $t('dashboard.paginaAnterior') : idx === recentRecepcions.links.length - 1 ? $t('dashboard.paginaSiguiente') : link.label }}</span>
                            </template>
                        </div>
                    </nav>
                </div>
            </section>
        </div>

        <!-- Galería de fotos -->
        <div v-if="showPhotoModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @click.self="showPhotoModal = false">
            <div role="dialog" aria-modal="true" class="max-h-[80vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-surface p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-ds-section-title text-navy">{{ $t('recepcion.evidencia') }}</h2>
                    <button type="button" class="ds-focus h-control-compact rounded-lg px-3 text-ds-control text-ink-secondary hover:bg-surface-2"
                        :aria-label="$t('dashboard.cerrarGaleria')" @click="showPhotoModal = false">✕</button>
                </div>
                <div v-if="selectedPhotos.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <img v-for="(photo, i) in selectedPhotos" :key="i"
                        :src="route('recepcion.foto', [selectedRecepcionId, i])"
                        class="aspect-square w-full rounded-lg object-cover" />
                </div>
                <p v-else class="py-8 text-center text-ds-body text-ink-muted">{{ $t('recepcion.sinFotos') }}</p>
            </div>
        </div>
    </AppShellLayout>
</template>
