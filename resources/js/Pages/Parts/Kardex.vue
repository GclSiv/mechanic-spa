<script setup>
// Kardex de una refacción (solo admin/owner; el backend aplica role:admin).
// Muestra el libro de movimientos y registra entradas, salidas y ajustes. La vista solo presenta y envía.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    part: { type: Object, required: true },
    movimientos: { type: Object, required: true },
});

const filas = computed(() => props.movimientos?.data ?? []);

const TIPOS_MANUALES = ['entrada', 'salida', 'ajuste'];
const CLAVE_TIPO = {
    inicial: 'kardex_tipo_inicial',
    entrada: 'kardex_tipo_entrada',
    salida: 'kardex_tipo_salida',
    ajuste: 'kardex_tipo_ajuste',
    consumo_orden: 'kardex_tipo_consumo',
    reversa_orden: 'kardex_tipo_reversa',
};
const VARIANTE_TIPO = {
    inicial: 'info',
    entrada: 'success',
    salida: 'warning',
    ajuste: 'neutral',
    consumo_orden: 'warning',
    reversa_orden: 'success',
};

const etiquetaTipo = (tipo) => t(`refaccionesUi.${CLAVE_TIPO[tipo] ?? 'kardex_tipo_ajuste'}`);
const cantidadTexto = (n) => (n > 0 ? `+${n}` : `${n}`);

const form = useForm({ type: 'entrada', quantity: '', reason: '' });

// Salida y ajuste exigen motivo; el ajuste acepta cantidad negativa.
const requiereMotivo = computed(() => form.type !== 'entrada');

function registrar() {
    form.post(route('parts.movimientos.store', props.part.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('quantity', 'reason'),
    });
}

const irAPagina = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const input = 'ds-focus h-control w-full min-w-0 rounded-lg border bg-surface px-3 text-ds-body text-ink';
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorTexto = 'mt-1 text-ds-caption text-danger-text';
</script>

<template>
    <Head :title="t('refaccionesUi.kardexTitulo', { nombre: part.name })" />

    <AppShellLayout>
        <PageHeader :title="t('refaccionesUi.kardexTitulo', { nombre: part.name })" :description="t('refaccionesUi.kardexDescripcion')">
            <Link :href="route('parts.index')" class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2">
                {{ t('refaccionesUi.kardexVolver') }}
            </Link>
            <Link :href="route('parts.edit', part.id)" class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90">
                {{ t('refaccionesUi.editar') }}
            </Link>
        </PageHeader>

        <div class="mt-6 grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="min-w-0 space-y-3 rounded-xl border border-line bg-surface p-5 lg:col-span-1" aria-labelledby="kardex-resumen">
                <h2 id="kardex-resumen" class="text-ds-section-title font-semibold text-navy">{{ t('refaccionesUi.kardexResumen') }}</h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('refaccionesUi.colStock') }}</dt>
                        <dd class="mt-1 text-ds-section-title font-semibold tabular-nums text-ink">{{ part.stock }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('refaccionesUi.colMinimo') }}</dt>
                        <dd class="mt-1 text-ds-section-title font-semibold tabular-nums text-ink">{{ part.low_stock_threshold }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('refaccionesUi.colSku') }}</dt>
                        <dd class="mt-1 font-mono text-ds-body uppercase text-ink">{{ part.sku }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('refaccionesUi.formBarcode') }}</dt>
                        <dd class="mt-1 font-mono text-ds-body text-ink">{{ part.barcode || t('refaccionesUi.sinCodigoBarras') }}</dd>
                    </div>
                </dl>

                <form class="space-y-3 border-t border-line pt-4" novalidate @submit.prevent="registrar">
                    <h3 class="text-ds-body font-semibold text-navy">{{ t('refaccionesUi.kardexRegistrar') }}</h3>
                    <div>
                        <label for="kardex-tipo" :class="label">{{ t('refaccionesUi.kardexTipo') }}</label>
                        <select id="kardex-tipo" v-model="form.type" :class="input">
                            <option v-for="tipo in TIPOS_MANUALES" :key="tipo" :value="tipo">{{ etiquetaTipo(tipo) }}</option>
                        </select>
                        <p v-if="form.errors.type" :class="errorTexto" role="alert">{{ form.errors.type }}</p>
                    </div>
                    <div>
                        <label for="kardex-cantidad" :class="label">{{ t('refaccionesUi.kardexCantidad') }}</label>
                        <input id="kardex-cantidad" v-model="form.quantity" type="number" step="1" inputmode="numeric" :aria-invalid="!!form.errors.quantity" :class="[input, form.errors.quantity ? 'border-danger' : 'border-line']" />
                        <p v-if="form.errors.quantity" :class="errorTexto" role="alert">{{ form.errors.quantity }}</p>
                        <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ form.type === 'ajuste' ? t('refaccionesUi.kardexAjusteAyuda') : t('refaccionesUi.kardexPositivaAyuda') }}</p>
                    </div>
                    <div>
                        <label for="kardex-motivo" :class="label">{{ t('refaccionesUi.kardexMotivo') }}{{ requiereMotivo ? ' *' : '' }}</label>
                        <input id="kardex-motivo" v-model="form.reason" type="text" maxlength="255" :aria-invalid="!!form.errors.reason" :class="[input, form.errors.reason ? 'border-danger' : 'border-line']" />
                        <p v-if="form.errors.reason" :class="errorTexto" role="alert">{{ form.errors.reason }}</p>
                    </div>
                    <button type="submit" :disabled="form.processing" class="ds-focus h-control w-full rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ form.processing ? t('refaccionesUi.formGuardando') : t('refaccionesUi.kardexRegistrar') }}
                    </button>
                </form>
            </section>

            <section class="min-w-0 space-y-4 lg:col-span-2" aria-labelledby="kardex-movimientos">
                <h2 id="kardex-movimientos" class="text-ds-section-title font-semibold text-navy">{{ t('refaccionesUi.kardexMovimientos') }}</h2>

                <EmptyState v-if="filas.length === 0" :title="t('refaccionesUi.kardexSinMovimientos')" :description="t('refaccionesUi.kardexSinMovimientosDescripcion')" />

                <div v-else class="hidden min-w-0 overflow-x-auto rounded-xl border border-line bg-surface lg:block">
                    <table class="w-full min-w-[640px] text-left text-ds-body">
                        <caption class="sr-only">{{ t('refaccionesUi.kardexMovimientos') }}</caption>
                        <thead class="bg-surface-2 text-ds-label uppercase text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.kardexColFecha') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.kardexColTipo') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('refaccionesUi.kardexColCantidad') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('refaccionesUi.kardexColAntes') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">{{ t('refaccionesUi.kardexColDespues') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.kardexColMotivo') }}</th>
                                <th scope="col" class="px-4 py-3 font-semibold">{{ t('refaccionesUi.kardexColUsuario') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="m in filas" :key="m.id" class="align-top">
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-ink-secondary">{{ m.created_at }}</td>
                                <td class="px-4 py-3"><BaseBadge :variant="VARIANTE_TIPO[m.type] ?? 'neutral'">{{ etiquetaTipo(m.type) }}</BaseBadge></td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums" :class="m.quantity < 0 ? 'text-danger-text' : 'text-success-text'">{{ cantidadTexto(m.quantity) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-ink-secondary">{{ m.stock_before }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-ink">{{ m.stock_after }}</td>
                                <td class="px-4 py-3 text-ink-secondary">{{ m.reason || '—' }}</td>
                                <td class="px-4 py-3 text-ink-secondary">{{ m.user || t('refaccionesUi.kardexSistema') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul v-if="filas.length" class="grid gap-3 lg:hidden">
                    <li v-for="m in filas" :key="m.id" class="min-w-0 rounded-xl border border-line bg-surface p-4">
                        <div class="flex items-start justify-between gap-3">
                            <BaseBadge :variant="VARIANTE_TIPO[m.type] ?? 'neutral'">{{ etiquetaTipo(m.type) }}</BaseBadge>
                            <span class="font-semibold tabular-nums" :class="m.quantity < 0 ? 'text-danger-text' : 'text-success-text'">{{ cantidadTexto(m.quantity) }}</span>
                        </div>
                        <p class="mt-2 text-ds-caption text-ink-muted tabular-nums">{{ m.created_at }} · {{ m.user || t('refaccionesUi.kardexSistema') }}</p>
                        <p class="mt-1 text-ds-body text-ink">{{ m.stock_before }} → {{ m.stock_after }}</p>
                        <p v-if="m.reason" class="mt-1 text-ds-caption text-ink-secondary">{{ m.reason }}</p>
                    </li>
                </ul>

                <nav v-if="movimientos.prev_page_url || movimientos.next_page_url" class="flex items-center justify-between gap-3 pt-2" aria-label="Paginación">
                    <button type="button" :disabled="!movimientos.prev_page_url" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40" @click="irAPagina(movimientos.prev_page_url)">{{ t('dashboard.paginaAnterior') }}</button>
                    <span class="text-ds-caption text-ink-muted tabular-nums">{{ movimientos.current_page }} / {{ movimientos.last_page }}</span>
                    <button type="button" :disabled="!movimientos.next_page_url" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40" @click="irAPagina(movimientos.next_page_url)">{{ t('dashboard.paginaSiguiente') }}</button>
                </nav>
            </section>
        </div>
    </AppShellLayout>
</template>
