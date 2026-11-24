<script setup>
// Directorio de clientes (solo admin/owner). La lista y la búsqueda llegan del servidor (paginator de Laravel).
// La vista solo navega y representa. Sin finanzas: esas viven en el expediente.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    clients: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const { t } = useI18n();

const filas = computed(() => props.clients?.data ?? []);
const hayBusqueda = computed(() => (props.filters?.search ?? '') !== '');

const buscar = ref(props.filters?.search ?? '');

// La búsqueda vive en el servidor y se conserva al paginar mediante el query string `search`.
function aplicarBusqueda() {
    const texto = buscar.value.trim();
    router.get(
        route('clients.index'),
        texto ? { search: texto } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function limpiarBusqueda() {
    buscar.value = '';
    aplicarBusqueda();
}

const irAPagina = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const nombre = (c) => [c.first_name, c.last_name].filter(Boolean).join(' ') || '—';
const rfcCliente = (c) => c.rfc || c.fiscal_profiles?.[0]?.rfc || null;
const vehiculoEtiqueta = (v) => [v.display_brand, v.display_model].filter(Boolean).join(' ') || t('clientesUi.vehiculoNoRegistrado');
</script>

<template>
    <Head :title="t('clientesUi.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('clientesUi.titulo')" :description="t('clientesUi.descripcion')">
            <Link
                :href="route('recepcion.create')"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('clientesUi.nuevaRecepcion') }}</Link>
        </PageHeader>

        <form class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center" role="search" @submit.prevent="aplicarBusqueda">
            <label for="clientes-buscar" class="sr-only">{{ t('clientesUi.buscar') }}</label>
            <input
                id="clientes-buscar"
                v-model="buscar"
                type="search"
                :placeholder="t('clientesUi.buscarPlaceholder')"
                class="ds-focus h-control w-full min-w-0 rounded-lg border border-line bg-surface px-3 text-ds-body text-ink sm:max-w-md"
            />
            <div class="flex gap-2">
                <button type="submit" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2">
                    {{ t('clientesUi.buscar') }}
                </button>
                <button
                    v-if="hayBusqueda || buscar"
                    type="button"
                    class="ds-focus h-control rounded-lg px-3 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
                    @click="limpiarBusqueda"
                >{{ t('clientesUi.limpiar') }}</button>
            </div>
        </form>

        <div class="mt-4 min-w-0 space-y-4">
            <p v-if="filas.length" class="text-ds-caption text-ink-muted tabular-nums">
                {{ t('clientesUi.cuenta', { n: clients.total }) }}
            </p>

            <EmptyState v-if="filas.length === 0" :title="hayBusqueda ? t('clientesUi.sinCoincidencias') : t('clientesUi.sinRegistros')" :description="hayBusqueda ? '' : t('clientesUi.sinRegistrosDescripcion')" />

            <!-- Escritorio: tabla -->
            <div v-else class="hidden min-w-0 overflow-x-auto rounded-xl border border-line bg-surface lg:block">
                <table class="w-full min-w-[760px] text-left text-ds-body">
                    <caption class="sr-only">{{ t('clientesUi.titulo') }}</caption>
                    <thead class="bg-surface-2 text-ds-label uppercase text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.colCliente') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.colContacto') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.colRfc') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ t('clientesUi.colVehiculos') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('clientesUi.colAcciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="c in filas" :key="c.id" class="align-top">
                            <td class="px-4 py-3 font-semibold text-navy">{{ nombre(c) }}</td>
                            <td class="px-4 py-3 text-ink-secondary">
                                <p>{{ c.phone || t('clientesUi.sinContacto') }}</p>
                                <p v-if="c.email" class="break-all text-ds-caption text-ink-muted">{{ c.email }}</p>
                            </td>
                            <td class="px-4 py-3 font-mono uppercase text-ink-secondary">{{ rfcCliente(c) || t('clientesUi.sinRfc') }}</td>
                            <td class="px-4 py-3">
                                <p v-if="!c.vehicles?.length" class="text-ds-caption text-ink-muted">{{ t('clientesUi.sinVehiculos') }}</p>
                                <ul v-else class="space-y-1">
                                    <li v-for="v in c.vehicles" :key="v.id" class="flex flex-wrap items-center gap-x-2 text-ds-caption">
                                        <span class="text-ink">{{ vehiculoEtiqueta(v) }}</span>
                                        <span class="font-mono uppercase text-ink-secondary">{{ v.plate }}</span>
                                        <a :href="route('vehicles.history', v.id)" class="ds-focus font-semibold text-navy underline-offset-2 hover:underline">{{ t('clientesUi.historial') }}</a>
                                    </li>
                                </ul>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Link :href="route('clients.show', c.id)" class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2">{{ t('clientesUi.verExpediente') }}</Link>
                                    <Link :href="route('recepcion.create', { client_id: c.id })" class="ds-focus inline-flex h-control-compact items-center whitespace-nowrap rounded-lg border border-line bg-surface px-3 text-ds-control text-ink-secondary hover:bg-surface-2">{{ t('clientesUi.nuevaRecepcion') }}</Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tablet y móvil: tarjetas -->
            <ul v-if="filas.length" class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:hidden">
                <li v-for="c in filas" :key="c.id" class="min-w-0 rounded-xl border border-line bg-surface p-4">
                    <p class="break-words font-semibold text-navy">{{ nombre(c) }}</p>
                    <p class="mt-1 text-ds-caption text-ink-muted">
                        <span class="font-mono uppercase">{{ rfcCliente(c) || t('clientesUi.sinRfc') }}</span>
                        · {{ c.phone || t('clientesUi.sinContacto') }}
                    </p>
                    <ul v-if="c.vehicles?.length" class="mt-3 space-y-1 border-t border-line pt-3 text-ds-caption">
                        <li v-for="v in c.vehicles" :key="v.id" class="flex flex-wrap items-center gap-x-2">
                            <span class="break-words text-ink">{{ vehiculoEtiqueta(v) }}</span>
                            <span class="font-mono uppercase text-ink-secondary">{{ v.plate }}</span>
                            <a :href="route('vehicles.history', v.id)" class="ds-focus font-semibold text-navy underline-offset-2 hover:underline">{{ t('clientesUi.historial') }}</a>
                        </li>
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link :href="route('clients.show', c.id)" class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2">{{ t('clientesUi.verExpediente') }}</Link>
                        <Link :href="route('recepcion.create', { client_id: c.id })" class="ds-focus inline-flex h-control-compact items-center rounded-lg border border-line bg-surface px-3 text-ds-control text-ink-secondary hover:bg-surface-2">{{ t('clientesUi.nuevaRecepcion') }}</Link>
                    </div>
                </li>
            </ul>

            <nav
                v-if="filas.length"
                class="flex flex-col gap-3 text-ds-caption text-ink-muted sm:flex-row sm:items-center sm:justify-between"
                :aria-label="t('clientesUi.paginacion')"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="!clients.prev_page_url"
                        class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="irAPagina(clients.prev_page_url)"
                    >{{ t('clientesUi.anterior') }}</button>
                    <button
                        type="button"
                        :disabled="!clients.next_page_url"
                        class="ds-focus h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="irAPagina(clients.next_page_url)"
                    >{{ t('clientesUi.siguiente') }}</button>
                </div>
                <span aria-live="polite">{{ t('clientesUi.pagina', { actual: clients.current_page, total: clients.last_page }) }}</span>
            </nav>
        </div>
    </AppShellLayout>
</template>
