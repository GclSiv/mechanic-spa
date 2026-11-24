<script setup>
// Recepciones (solo admin/owner): registro de ingresos antes de generar la orden de reparación.
// La lista llega paginada desde el servidor (paginator de Laravel). La vista solo navega y representa.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { orderStatusVariant } from '@/Support/orderStatus.js';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ recepciones: Object });

const page = usePage();
const { t, te } = useI18n();

const isAdmin = computed(() => page.props.auth?.isAdmin === true);

const filas = computed(() => props.recepciones?.data ?? []);

const estadoLabel = (slug) => (te(`estadoUi.${slug}`) ? t(`estadoUi.${slug}`) : slug);

// Vínculo con la orden: condición derivada de la relación, no un estado persistente de la recepción.
function vinculo(rec) {
    const orden = rec.repair_order;
    if (!orden) {
        return { sinOrden: true, label: t('recepcionUi.sinOrden'), variant: 'neutral' };
    }
    const slug = orden.status?.slug;
    return {
        sinOrden: false,
        label: slug ? estadoLabel(slug) : t('recepcionUi.ordenGenerada'),
        variant: orderStatusVariant(slug),
        ordenId: orden.id,
    };
}

const vehiculo = (rec) => {
    const v = rec.vehicle;
    // Marca y modelo ya resueltos en el servidor (catálogo o texto histórico).
    const marca = v?.display_brand ?? t('recepcionUi.sinMarca');
    const modelo = v?.display_model ?? '';
    return [marca, modelo, v?.year ? `(${v.year})` : null].filter(Boolean).join(' ');
};

const cliente = (rec) => [rec.client?.first_name, rec.client?.last_name].filter(Boolean).join(' ') || '—';

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');

// Navegación por el paginador del servidor: Inertia conserva estado y scroll, sin recarga completa.
const irAPagina = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const confirmDel = ref({ show: false, id: null });
function askDelete(id) { confirmDel.value = { show: true, id }; }
function doDel() {
    router.delete(route('recepcion.destroy', confirmDel.value.id), { preserveScroll: true });
    confirmDel.value = { show: false, id: null };
}
</script>

<template>
    <Head :title="t('recepcionUi.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('recepcionUi.titulo')" :description="t('recepcionUi.descripcion')">
            <Link
                v-if="isAdmin"
                :href="route('recepcion.create')"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('recepcionUi.nueva') }}</Link>
        </PageHeader>

        <div class="mt-6 min-w-0 space-y-4">
            <p v-if="filas.length" class="text-ds-caption text-ink-muted tabular-nums">
                {{ t('recepcionUi.cuenta', { n: recepciones.total }) }}
            </p>

            <EmptyState
                v-if="filas.length === 0"
                :title="t('recepcionUi.sinRegistros')"
            />

            <div v-else class="min-w-0 max-w-full overflow-x-auto rounded-xl border border-line bg-surface">
                <table class="w-full min-w-[640px] text-left text-ds-body">
                    <caption class="sr-only">{{ t('recepcionUi.titulo') }}</caption>
                    <thead class="bg-surface-2 text-ds-label uppercase text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('recepcionUi.colRecepcion') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('recepcionUi.colCliente') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('recepcionUi.colVehiculo') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('recepcionUi.colPlaca') }}</th>
                            <th scope="col" class="hidden px-4 py-3 font-semibold xl:table-cell">{{ t('recepcionUi.colIngreso') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('recepcionUi.colOrden') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('recepcionUi.colAcciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="rec in filas" :key="rec.id" class="align-top">
                            <td class="px-4 py-3 font-mono text-ds-caption text-ink-secondary">#{{ rec.id }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-navy">{{ cliente(rec) }}</p>
                                <p class="text-ds-caption text-ink-muted">{{ rec.client?.phone ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-ink">{{ vehiculo(rec) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono uppercase text-ink-secondary">{{ rec.vehicle?.plate ?? '—' }}</td>
                            <td class="hidden px-4 py-3 text-ds-caption text-ink-muted xl:table-cell">{{ fecha(rec.created_at) }}</td>
                            <td class="px-4 py-3">
                                <BaseBadge v-if="vinculo(rec).sinOrden" :variant="vinculo(rec).variant">{{ vinculo(rec).label }}</BaseBadge>
                                <Link
                                    v-else
                                    :href="route('repair-orders.show', vinculo(rec).ordenId)"
                                    class="ds-focus inline-flex flex-col items-start gap-1 rounded-md"
                                >
                                    <BaseBadge :variant="vinculo(rec).variant">{{ vinculo(rec).label }}</BaseBadge>
                                    <span class="text-ds-caption font-semibold text-navy underline-offset-2 hover:underline">{{ t('recepcionUi.abrirOrden') }}</span>
                                </Link>
                            </td>
                            <td class="min-w-[15rem] px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-1.5 xl:gap-2">
                                    <Link
                                        :href="route('recepcion.show', rec.id)"
                                        class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                                    >{{ t('recepcionUi.ver') }}</Link>
                                    <Link
                                        :href="route('recepcion.edit', rec.id)"
                                        class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control text-ink-secondary hover:bg-surface-2"
                                    >{{ t('recepcionUi.editar') }}</Link>
                                    <a
                                        :href="route('recepcion.pdf', rec.id)"
                                        target="_blank"
                                        rel="noopener"
                                        class="ds-focus hidden xl:inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control text-ink-secondary hover:bg-surface-2"
                                    >{{ t('recepcionUi.imprimir') }}</a>
                                    <a
                                        v-if="rec.vehicle"
                                        :href="route('vehicles.history', rec.vehicle.id)"
                                        class="ds-focus hidden xl:inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control text-ink-secondary hover:bg-surface-2"
                                    >{{ t('recepcionUi.historialVehiculo') }}</a>
                                    <button
                                        v-if="isAdmin"
                                        type="button"
                                        class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-danger-text hover:bg-danger-soft"
                                        @click="askDelete(rec.id)"
                                    >{{ t('recepcionUi.eliminar') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="filas.length"
                class="flex flex-col gap-3 text-ds-caption text-ink-muted sm:flex-row sm:items-center sm:justify-between"
                :aria-label="t('recepcionUi.paginacion')"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="!recepciones.prev_page_url"
                        class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="irAPagina(recepciones.prev_page_url)"
                    >{{ t('recepcionUi.anterior') }}</button>
                    <button
                        type="button"
                        :disabled="!recepciones.next_page_url"
                        class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="irAPagina(recepciones.next_page_url)"
                    >{{ t('recepcionUi.siguiente') }}</button>
                </div>
                <span aria-live="polite">{{ t('recepcionUi.pagina', { actual: recepciones.current_page, total: recepciones.last_page }) }}</span>
            </nav>
        </div>

        <ConfirmModal
            :show="confirmDel.show"
            :title="t('recepcionUi.eliminarTitulo')"
            :message="t('recepcionUi.eliminarMensaje')"
            :confirm-text="t('recepcionUi.eliminarConfirmar')"
            @confirm="doDel"
            @cancel="confirmDel.show = false"
        />
    </AppShellLayout>
</template>
