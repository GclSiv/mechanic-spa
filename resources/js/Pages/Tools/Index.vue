<script setup>
// Herramientas (solo admin/owner). Búsqueda, filtros y paginación viven en el servidor: la vista solo navega y presenta.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { CLAVE_ESTADO, VARIANTE_ESTADO } from '@/Components/Tools/presentacion.js';

const { t } = useI18n();

const props = defineProps({
    tools: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    estados: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filas = computed(() => props.tools?.data ?? []);
const hayFiltro = computed(() =>
    !!props.filters?.search || !!props.filters?.status || !!props.filters?.category_id
    || !!props.filters?.location_id || !!props.filters?.mostrar_bajas);

const buscar = ref(props.filters?.search ?? '');
const estado = ref(props.filters?.status ?? '');
const categoria = ref(String(props.filters?.category_id ?? ''));
const ubicacion = ref(String(props.filters?.location_id ?? ''));
const mostrarBajas = ref(!!props.filters?.mostrar_bajas);

// Filtros enviados al servidor. La paginación conserva la misma consulta en la URL.
function aplicarFiltros() {
    const datos = {};
    const texto = buscar.value.trim();
    if (texto) datos.search = texto;
    if (estado.value) datos.status = estado.value;
    if (categoria.value) datos.category_id = categoria.value;
    if (ubicacion.value) datos.location_id = ubicacion.value;
    if (mostrarBajas.value) datos.mostrar_bajas = 1;
    router.get(route('tools.index'), datos, { preserveState: true, preserveScroll: true, replace: true });
}

function limpiarFiltros() {
    buscar.value = '';
    estado.value = '';
    categoria.value = '';
    ubicacion.value = '';
    mostrarBajas.value = false;
    aplicarFiltros();
}

const irAPagina = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const etiquetaEstado = (valor) => t(`herramientasUi.${CLAVE_ESTADO[valor] ?? 'estado_disponible'}`);

// Baja lógica: confirmación del DS. El registro y su evidencia se conservan.
const confirmBaja = ref({ show: false, id: null });
function pedirBaja(id) { confirmBaja.value = { show: true, id }; }
function confirmarBaja() {
    router.delete(route('tools.destroy', confirmBaja.value.id), { preserveScroll: true });
    confirmBaja.value = { show: false, id: null };
}
</script>

<template>
    <Head :title="t('herramientasUi.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('herramientasUi.titulo')" :description="t('herramientasUi.descripcion')">
            <Link
                :href="route('tools.create')"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('herramientasUi.nueva') }}</Link>
        </PageHeader>

        <form class="mt-6 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-5" role="search" @submit.prevent="aplicarFiltros">
            <label for="herramientas-buscar" class="sr-only">{{ t('herramientasUi.buscar') }}</label>
            <input
                id="herramientas-buscar"
                v-model="buscar"
                type="search"
                :placeholder="t('herramientasUi.buscarPlaceholder')"
                class="ds-focus h-control min-w-0 rounded-lg border border-line bg-surface px-3 text-ds-body text-ink md:col-span-2"
            />
            <label for="herramientas-estado" class="sr-only">{{ t('herramientasUi.colEstado') }}</label>
            <select id="herramientas-estado" v-model="estado" class="ds-focus h-control min-w-0 rounded-lg border border-line bg-surface px-3 text-ds-body text-ink" @change="aplicarFiltros">
                <option value="">{{ t('herramientasUi.todosEstados') }}</option>
                <option v-for="e in estados" :key="e" :value="e">{{ t(`herramientasUi.${CLAVE_ESTADO[e]}`) }}</option>
            </select>
            <label for="herramientas-categoria" class="sr-only">{{ t('herramientasUi.colCategoria') }}</label>
            <select id="herramientas-categoria" v-model="categoria" class="ds-focus h-control min-w-0 rounded-lg border border-line bg-surface px-3 text-ds-body text-ink" @change="aplicarFiltros">
                <option value="">{{ t('herramientasUi.todasCategorias') }}</option>
                <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
            </select>
            <label for="herramientas-ubicacion" class="sr-only">{{ t('herramientasUi.colUbicacion') }}</label>
            <select id="herramientas-ubicacion" v-model="ubicacion" class="ds-focus h-control min-w-0 rounded-lg border border-line bg-surface px-3 text-ds-body text-ink" @change="aplicarFiltros">
                <option value="">{{ t('herramientasUi.todasUbicaciones') }}</option>
                <option v-for="l in locations" :key="l.id" :value="String(l.id)">{{ l.name }}</option>
            </select>
            <div class="flex flex-wrap items-center gap-3 md:col-span-2 xl:col-span-5">
                <label class="flex items-center gap-2 text-ds-body text-ink-secondary">
                    <input v-model="mostrarBajas" type="checkbox" class="ds-focus h-4 w-4 rounded border-line text-navy" @change="aplicarFiltros" />
                    {{ t('herramientasUi.mostrarBajas') }}
                </label>
                <button type="submit" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2">
                    {{ t('herramientasUi.buscar') }}
                </button>
                <button
                    v-if="hayFiltro || buscar"
                    type="button"
                    class="ds-focus h-control rounded-lg px-3 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
                    @click="limpiarFiltros"
                >{{ t('herramientasUi.limpiar') }}</button>
            </div>
        </form>

        <div class="mt-4 min-w-0 space-y-4">
            <p v-if="filas.length" class="text-ds-caption text-ink-muted tabular-nums">
                {{ t('herramientasUi.cuenta', { n: tools.total }) }}
            </p>

            <EmptyState
                v-if="filas.length === 0"
                :title="hayFiltro ? t('herramientasUi.sinCoincidencias') : t('herramientasUi.sinRegistros')"
                :description="hayFiltro ? '' : t('herramientasUi.sinRegistrosDescripcion')"
            />

            <!-- Escritorio: tabla -->
            <div v-else class="hidden min-w-0 overflow-x-auto rounded-xl border border-line bg-surface lg:block">
                <table class="w-full min-w-[860px] text-left text-ds-body">
                    <caption class="sr-only">{{ t('herramientasUi.titulo') }}</caption>
                    <thead class="bg-surface-2 text-ds-label uppercase text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('herramientasUi.colCodigo') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('herramientasUi.colHerramienta') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('herramientasUi.colCategoria') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('herramientasUi.colUbicacion') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('herramientasUi.colEstado') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('herramientasUi.colAcciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="h in filas" :key="h.id" class="align-top" :class="{ 'opacity-70': !h.active }">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-ds-caption uppercase text-ink-secondary">{{ h.internal_code }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-navy">{{ h.name }}</div>
                                <div class="text-ds-caption text-ink-muted">{{ [h.brand, h.model].filter(Boolean).join(' ') || t('herramientasUi.sinDato') }}</div>
                            </td>
                            <td class="px-4 py-3 text-ink-secondary">{{ h.category?.name ?? t('herramientasUi.sinCategoria') }}</td>
                            <td class="px-4 py-3 text-ink-secondary">{{ h.location?.name ?? t('herramientasUi.sinUbicacion') }}</td>
                            <td class="px-4 py-3">
                                <BaseBadge :variant="VARIANTE_ESTADO[h.status] ?? 'neutral'">{{ etiquetaEstado(h.status) }}</BaseBadge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3 whitespace-nowrap text-ds-control font-semibold">
                                    <Link :href="route('tools.show', h.id)" class="ds-focus rounded text-navy hover:underline">{{ t('herramientasUi.ver') }}</Link>
                                    <Link :href="route('tools.edit', h.id)" class="ds-focus rounded text-navy hover:underline">{{ t('herramientasUi.editar') }}</Link>
                                    <button v-if="h.active" type="button" class="ds-focus rounded text-danger-text hover:underline" @click="pedirBaja(h.id)">{{ t('herramientasUi.darBaja') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Móvil y tablet: tarjetas -->
            <ul v-if="filas.length" class="grid gap-3 lg:hidden">
                <li v-for="h in filas" :key="h.id" class="min-w-0 rounded-xl border border-line bg-surface p-4" :class="{ 'opacity-70': !h.active }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-ds-caption uppercase text-ink-muted">{{ h.internal_code }}</p>
                            <h2 class="truncate text-ds-section-title font-semibold text-navy">{{ h.name }}</h2>
                            <p class="text-ds-caption text-ink-muted">{{ [h.brand, h.model].filter(Boolean).join(' ') || t('herramientasUi.sinDato') }}</p>
                        </div>
                        <BaseBadge :variant="VARIANTE_ESTADO[h.status] ?? 'neutral'">{{ etiquetaEstado(h.status) }}</BaseBadge>
                    </div>
                    <p class="mt-2 text-ds-caption text-ink-secondary">
                        {{ h.category?.name ?? t('herramientasUi.sinCategoria') }} · {{ h.location?.name ?? t('herramientasUi.sinUbicacion') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4 text-ds-control font-semibold">
                        <Link :href="route('tools.show', h.id)" class="ds-focus rounded text-navy hover:underline">{{ t('herramientasUi.ver') }}</Link>
                        <Link :href="route('tools.edit', h.id)" class="ds-focus rounded text-navy hover:underline">{{ t('herramientasUi.editar') }}</Link>
                        <button v-if="h.active" type="button" class="ds-focus rounded text-danger-text hover:underline" @click="pedirBaja(h.id)">{{ t('herramientasUi.darBaja') }}</button>
                    </div>
                </li>
            </ul>

            <nav v-if="tools.prev_page_url || tools.next_page_url" class="flex items-center justify-between gap-3 pt-2" aria-label="Paginación">
                <button
                    type="button"
                    :disabled="!tools.prev_page_url"
                    class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="irAPagina(tools.prev_page_url)"
                >{{ t('dashboard.paginaAnterior') }}</button>
                <span class="text-ds-caption text-ink-muted tabular-nums">{{ tools.current_page }} / {{ tools.last_page }}</span>
                <button
                    type="button"
                    :disabled="!tools.next_page_url"
                    class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="irAPagina(tools.next_page_url)"
                >{{ t('dashboard.paginaSiguiente') }}</button>
            </nav>
        </div>

        <ConfirmModal
            :show="confirmBaja.show"
            :title="t('herramientasUi.bajaTitulo')"
            :message="t('herramientasUi.bajaMensaje')"
            :confirm-text="t('herramientasUi.bajaConfirmar')"
            :cancel-text="t('herramientasUi.cancelar')"
            @confirm="confirmarBaja"
            @cancel="confirmBaja = { show: false, id: null }"
        />
    </AppShellLayout>
</template>
