<script setup>
// Formulario compartido de Refacciones (alta y edición). Solo presenta; el backend valida y es la autoridad.
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    submitLabel: { type: String, required: true },
    submittingLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
    // En edición el stock no se escribe aquí: se muestra y se cambia desde el Kardex.
    stockActual: { type: Number, default: null },
    kardexHref: { type: String, default: null },
});

defineEmits(['submit']);

const input = 'ds-focus h-control w-full min-w-0 rounded-lg border bg-surface px-3 text-ds-body text-ink';
const inputClase = (campo, form) => [input, form.errors[campo] ? 'border-danger' : 'border-line'];
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorTexto = 'mt-1 text-ds-caption text-danger-text';
</script>

<template>
    <form class="space-y-6" novalidate @submit.prevent="$emit('submit')">
        <section class="space-y-4 rounded-xl border border-line bg-surface p-5" aria-labelledby="pf-identificacion">
            <h2 id="pf-identificacion" class="text-ds-section-title font-semibold text-navy">{{ t('refaccionesUi.formIdentificacion') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="pf-nombre" :class="label">{{ t('refaccionesUi.formNombre') }}</label>
                    <input id="pf-nombre" v-model="form.name" type="text" autocomplete="off" :aria-invalid="!!form.errors.name" :class="inputClase('name', form)" />
                    <p v-if="form.errors.name" :class="errorTexto" role="alert">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="pf-sku" :class="label">{{ t('refaccionesUi.formSku') }}</label>
                    <input id="pf-sku" v-model="form.sku" type="text" autocomplete="off" class="font-mono" :aria-invalid="!!form.errors.sku" :class="inputClase('sku', form)" />
                    <p v-if="form.errors.sku" :class="errorTexto" role="alert">{{ form.errors.sku }}</p>
                    <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('refaccionesUi.formSkuAyuda') }}</p>
                </div>
                <div>
                    <label for="pf-barcode" :class="label">{{ t('refaccionesUi.formBarcode') }}</label>
                    <input id="pf-barcode" v-model="form.barcode" type="text" autocomplete="off" class="font-mono" :aria-invalid="!!form.errors.barcode" :class="inputClase('barcode', form)" />
                    <p v-if="form.errors.barcode" :class="errorTexto" role="alert">{{ form.errors.barcode }}</p>
                    <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('refaccionesUi.formBarcodeAyuda') }}</p>
                </div>
                <div>
                    <label for="pf-categoria" :class="label">{{ t('refaccionesUi.formCategoria') }}</label>
                    <select id="pf-categoria" v-model="form.category_id" :aria-invalid="!!form.errors.category_id" :class="inputClase('category_id', form)">
                        <option value="">{{ t('refaccionesUi.sinCategoria') }}</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <p v-if="form.errors.category_id" :class="errorTexto" role="alert">{{ form.errors.category_id }}</p>
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-line bg-surface p-5" aria-labelledby="pf-inventario">
            <h2 id="pf-inventario" class="text-ds-section-title font-semibold text-navy">{{ t('refaccionesUi.formInventario') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div v-if="stockActual === null">
                    <label for="pf-stock" :class="label">{{ t('refaccionesUi.formStock') }}</label>
                    <input id="pf-stock" v-model="form.stock" type="number" min="0" step="1" inputmode="numeric" :aria-invalid="!!form.errors.stock" :class="inputClase('stock', form)" />
                    <p v-if="form.errors.stock" :class="errorTexto" role="alert">{{ form.errors.stock }}</p>
                    <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('refaccionesUi.formStockInicialAyuda') }}</p>
                </div>
                <div v-else class="md:col-span-2 flex flex-col gap-2 rounded-lg border border-line bg-surface-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-ds-body text-ink-secondary">
                        {{ t('refaccionesUi.formStockSoloKardex') }}
                        <span class="ml-1 font-semibold tabular-nums text-ink">{{ stockActual }}</span>
                    </p>
                    <Link v-if="kardexHref" :href="kardexHref" class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface">
                        {{ t('refaccionesUi.kardexVer') }}
                    </Link>
                </div>
                <div>
                    <label for="pf-minimo" :class="label">{{ t('refaccionesUi.formStockMinimo') }}</label>
                    <input id="pf-minimo" v-model="form.low_stock_threshold" type="number" min="0" step="1" inputmode="numeric" :aria-invalid="!!form.errors.low_stock_threshold" :class="inputClase('low_stock_threshold', form)" />
                    <p v-if="form.errors.low_stock_threshold" :class="errorTexto" role="alert">{{ form.errors.low_stock_threshold }}</p>
                    <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('refaccionesUi.formStockMinimoAyuda') }}</p>
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-line bg-surface p-5" aria-labelledby="pf-precios">
            <h2 id="pf-precios" class="text-ds-section-title font-semibold text-navy">{{ t('refaccionesUi.formPrecios') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="pf-costo" :class="label">{{ t('refaccionesUi.formCosto') }}</label>
                    <input id="pf-costo" v-model="form.cost_price" type="number" min="0" step="0.01" inputmode="decimal" :aria-invalid="!!form.errors.cost_price" :class="inputClase('cost_price', form)" />
                    <p v-if="form.errors.cost_price" :class="errorTexto" role="alert">{{ form.errors.cost_price }}</p>
                </div>
                <div>
                    <label for="pf-venta" :class="label">{{ t('refaccionesUi.formVenta') }}</label>
                    <input id="pf-venta" v-model="form.sale_price" type="number" min="0" step="0.01" inputmode="decimal" :aria-invalid="!!form.errors.sale_price" :class="inputClase('sale_price', form)" />
                    <p v-if="form.errors.sale_price" :class="errorTexto" role="alert">{{ form.errors.sale_price }}</p>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-4 sm:flex-row sm:justify-end">
            <Link :href="cancelHref" class="ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2">
                {{ t('refaccionesUi.formCancelar') }}
            </Link>
            <button type="submit" :disabled="form.processing" class="ds-focus inline-flex h-control items-center justify-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50">
                {{ form.processing ? submittingLabel : submitLabel }}
            </button>
        </div>
    </form>
</template>
