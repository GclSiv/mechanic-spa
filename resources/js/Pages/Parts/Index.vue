<script setup>
// Catálogo de refacciones (solo admin/owner). Búsqueda, filtro y paginación viven en el servidor:
// la vista solo navega y presenta. El estado de stock se deriva de campos reales; no se guarda.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    parts: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', category_id: '' }) },
});

const filas = computed(() => props.parts?.data ?? []);
const hayFiltro = computed(() => (props.filters?.search ?? '') !== '' || (props.filters?.category_id ?? '') !== '');

const buscar = ref(props.filters?.search ?? '');
const categoria = ref(String(props.filters?.category_id ?? ''));

// Filtros enviados al servidor; la paginación conserva search y category_id en la URL.
function aplicarFiltros() {
    const datos = {};
    const texto = buscar.value.trim();
    if (texto) datos.search = texto;
    if (categoria.value) datos.category_id = categoria.value;
    router.get(route('parts.index'), datos, { preserveState: true, preserveScroll: true, replace: true });
}

function limpiarFiltros() {
    buscar.value = '';
    categoria.value = '';
    aplicarFiltros();
}

// Escaneo: el servidor busca por código de barras y, si no hay, por SKU, y lleva al Kardex.
const codigoEscaneo = ref('');
function escanear() {
    const codigo = codigoEscaneo.value.trim();
    if (!codigo) return;
    router.get(route('parts.scan'), { codigo }, { preserveScroll: true });
}

const irAPagina = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

// Estado derivado solo para presentación: agotado (stock <= 0), stock bajo (is_low_stock) o disponible.
const estadoStock = (p) => {
    if (p.stock <= 0) return 'agotado';
    if (p.is_low_stock) return 'bajo';
    return 'disponible';
};
const varianteStock = { agotado: 'danger', bajo: 'warning', disponible: 'success' };
const etiquetaStock = (p) => t(`refaccionesUi.estado_${estadoStock(p)}`);
const precio = (valor) => `$${Number(valor).toFixed(2)}`;

// Borrado con confirmación del DS. El backend bloquea refacciones con uso histórico y responde con mensaje.
const confirmDel = ref({ show: false, id: null });
function pedirBorrado(id) { confirmDel.value = { show: true, id }; }
function confirmarBorrado() {
    router.delete(route('parts.destroy', confirmDel.value.id), { preserveScroll: true });
    confirmDel.value = { show: false, id: null };
}
</script>

<template>
    <Head :title="t('refaccionesUi.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('refaccionesUi.titulo')" :description="t('refaccionesUi.descripcion')">
            <Link
                :href="route('parts.create')"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('refaccionesUi.nueva') }}</Link>
        </PageHeader>

        <form class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center" role="search" @submit.prevent="escanear">
            <label for="refacciones-escaner" class="sr-only">{{ t('refaccionesUi.escanearEtiqueta') }}</label>
            <input
                id="refacciones-escaner"
                v-model="codigoEscaneo"
                type="text"
                autocomplete="off"
                :placeholder="t('refaccionesUi.escanearPlaceholder')"
                class="ds-focus h-control w-full min-w-0 rounded-lg border border-line bg-surface px-3 font-mono text-ds-body text-ink sm:max-w-xs"
            />
            <button type="submit" class="ds-focus h-control rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90">
                {{ t('refaccionesUi.escanear') }}
            </button>
        </form>

        <form class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center" role="search" @submit.prevent="aplicarFiltros">
            <label for="refacciones-buscar" class="sr-only">{{ t('refaccionesUi.buscar') }}</label>
            <input
                id="refacciones-buscar"
                v-model="buscar"
                type="search"
                :placeholder="t('refaccionesUi.buscarPlaceholder')"
                class="ds-focus h-control w-full min-w-0 rounded-lg border border-line bg-surface px-3 text-ds-body text-ink sm:max-w-md"
            />
            <label for="refacciones-categoria" class="sr-only">{{ t('refaccionesUi.colCategoria') }}</label>
            <select
                id="refacciones-categoria"
                v-model="categoria"
                class="ds-focus h-control w-full min-w-0 rounded-lg border border-line bg-surface px-3 text-ds-body text-ink sm:w-56"
                @change="aplicarFiltros"
            >
                <option value="">{{ t('refaccionesUi.todasCategorias') }}</option>
                <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2">
                    {{ t('refaccionesUi.buscar') }}
                </button>
                <button
                    v-if="hayFiltro || buscar || categoria"
                    type="button"
                    class="ds-focus h-control rounded-lg px-3 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
                    @click="limpiarFiltros"
                >{{ t('refaccionesUi.limpiar') }}</button>
            </div>
        </form>

        <div class="mt-4 min-w-0 space-y-4">
            <p v-if="filas.length" class="text-ds-caption text-ink-muted tabular-nums">
                {{ t('refaccionesUi.cuenta', { n: parts.total }) }}
            </p>

            <EmptyState
                v-if="filas.length === 0"
                :title="hayFiltro ? t('refaccionesUi.sinCoincidencias') : t('refaccionesUi.sinRegistros')"
                :description="hayFiltro ? '' : t('refaccionesUi.sinRegistrosDescripcion')"
            />

            <!-- Escritorio: tabla -->
            <div v-else class="hidden min-w-0 overflow-x-auto rounded-xl border border-line bg-surface lg:block">
                <table class="w-full min-w-[920px] text-left text-ds-body">
                    <caption class="sr-only">{{ t('refaccionesUi.titulo') }}</caption>
                    <thead class="bg-surface-2 text-ds-label uppercase text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.colSku') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.colRefaccion') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.colCategoria') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.colStock') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('refaccionesUi.colMinimo') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('refaccionesUi.colCosto') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('refaccionesUi.colVenta') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('refaccionesUi.colAcciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="p in filas" :key="p.id" class="align-top">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-ds-caption uppercase text-ink-secondary">{{ p.sku }}</td>
                            <td class="px-4 py-3 font-semibold text-navy">{{ p.name }}</td>
                            <td class="px-4 py-3 text-ink-secondary">
                                <span v-if="p.category">{{ p.category.name }}</span>
                                <span v-else class="text-ink-muted">{{ t('refaccionesUi.sinCategoria') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="font-semibold tabular-nums text-ink">{{ p.stock }}</span>
                                    <BaseBadge :variant="varianteStock[estadoStock(p)]">{{ etiquetaStock(p) }}</BaseBadge>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-ink-secondary">{{ p.low_stock_threshold }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-ink-secondary">{{ precio(p.cost_price) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-ink">{{ precio(p.sale_price) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Link
                                        :href="route('parts.kardex', p.id)"
                                        class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                                    >{{ t('refaccionesUi.kardexVer') }}</Link>
                                    <Link
                                        :href="route('parts.edit', p.id)"
                                        class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                                    >{{ t('refaccionesUi.editar') }}</Link>
                                    <button
                                        type="button"
                                        class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-danger-text hover:bg-danger-soft"
                                        @click="pedirBorrado(p.id)"
                                    >{{ t('refaccionesUi.eliminar') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tablet y móvil: tarjetas -->
            <ul v-if="filas.length" class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:hidden">
                <li v-for="p in filas" :key="p.id" class="min-w-0 rounded-xl border border-line bg-surface p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-words font-semibold text-navy">{{ p.name }}</p>
                            <p class="mt-1 font-mono text-ds-caption uppercase text-ink-muted">{{ p.sku }}</p>
                        </div>
                        <BaseBadge :variant="varianteStock[estadoStock(p)]">{{ etiquetaStock(p) }}</BaseBadge>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-line pt-3 text-ds-caption">
                        <div><dt class="text-ink-muted">{{ t('refaccionesUi.colCategoria') }}</dt><dd class="text-ink">{{ p.category?.name ?? t('refaccionesUi.sinCategoria') }}</dd></div>
                        <div><dt class="text-ink-muted">{{ t('refaccionesUi.colStock') }}</dt><dd class="tabular-nums text-ink">{{ p.stock }} / {{ p.low_stock_threshold }}</dd></div>
                        <div><dt class="text-ink-muted">{{ t('refaccionesUi.colCosto') }}</dt><dd class="tabular-nums text-ink">{{ precio(p.cost_price) }}</dd></div>
                        <div><dt class="text-ink-muted">{{ t('refaccionesUi.colVenta') }}</dt><dd class="tabular-nums font-semibold text-ink">{{ precio(p.sale_price) }}</dd></div>
                    </dl>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link
                            :href="route('parts.kardex', p.id)"
                            class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                        >{{ t('refaccionesUi.kardexVer') }}</Link>
                        <Link
                            :href="route('parts.edit', p.id)"
                            class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                        >{{ t('refaccionesUi.editar') }}</Link>
                        <button
                            type="button"
                            class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-danger-text hover:bg-danger-soft"
                            @click="pedirBorrado(p.id)"
                        >{{ t('refaccionesUi.eliminar') }}</button>
                    </div>
                </li>
            </ul>

            <nav
                v-if="filas.length"
                class="flex flex-col gap-3 text-ds-caption text-ink-muted sm:flex-row sm:items-center sm:justify-between"
                :aria-label="t('refaccionesUi.paginacion')"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="!parts.prev_page_url"
                        class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="irAPagina(parts.prev_page_url)"
                    >{{ t('refaccionesUi.anterior') }}</button>
                    <button
                        type="button"
                        :disabled="!parts.next_page_url"
                        class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="irAPagina(parts.next_page_url)"
                    >{{ t('refaccionesUi.siguiente') }}</button>
                </div>
                <span aria-live="polite">{{ t('refaccionesUi.pagina', { actual: parts.current_page, total: parts.last_page }) }}</span>
            </nav>
        </div>

        <ConfirmModal
            :show="confirmDel.show"
            :title="t('refaccionesUi.eliminarTitulo')"
            :message="t('refaccionesUi.eliminarMensaje')"
            :confirm-text="t('refaccionesUi.eliminarConfirmar')"
            @confirm="confirmarBorrado"
            @cancel="confirmDel.show = false"
        />
    </AppShellLayout>
</template>
