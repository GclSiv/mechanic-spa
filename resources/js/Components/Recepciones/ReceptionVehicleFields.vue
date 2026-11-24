<script setup>
// Datos del vehículo compartidos por Crear y Editar. Modelos dependen de la marca elegida.
// Solo representa el formulario recibido por props; los errores llegan de Laravel.
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    form: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    // Color solo se captura al crear (Editar no lo envía al backend).
    showColor: { type: Boolean, default: false },
    // Marca y modelo en texto de un registro anterior (sin FK). Solo Editar lo recibe.
    legacy: { type: Object, default: null },
});
const { t } = useI18n();

const input = 'ds-focus h-control w-full rounded-lg border border-line-strong bg-surface px-3 text-ds-control text-ink placeholder:text-ink-subtle disabled:cursor-not-allowed disabled:bg-surface-2';
const inputError = 'border-danger';
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorText = 'mt-1 text-ds-caption text-danger-text';
const cls = (key) => [input, props.form.errors[key] ? inputError : ''];

// Registro histórico: sin FK de marca, pero con texto. Se muestra como dato real y no como "Seleccione...".
const legacyText = computed(() => [props.legacy?.brand, props.legacy?.model].filter(Boolean).join(' '));
const modoLegacy = ref(!!legacyText.value && !props.form.brand_id);

const availableModels = computed(() => {
    if (!props.form.brand_id) return [];
    const brand = props.brands.find((b) => b.id == props.form.brand_id);
    return brand ? brand.vehicle_models : [];
});

// Cambiar de marca invalida el modelo elegido.
watch(() => props.form.brand_id, (nuevo, anterior) => {
    if (anterior !== undefined && nuevo !== anterior) props.form.vehicle_model_id = '';
});

// Años: rango reciente más el año guardado si quedara fuera del rango (Editar).
const years = computed(() => {
    const top = new Date().getFullYear() + 1;
    const list = Array.from({ length: 35 }, (_, i) => String(top - i));
    const actual = props.form.year ? String(props.form.year) : '';
    if (actual && !list.includes(actual)) list.push(actual);
    return list;
});
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div v-if="modoLegacy" class="rounded-lg border border-line bg-surface-2 p-3 md:col-span-2">
                <p :class="label">{{ t('recepcionUi.marcaLegacyTitulo') }}</p>
                <p class="text-ds-control font-semibold text-navy">{{ legacyText }}</p>
                <p class="mt-1 text-ds-caption text-ink-muted">{{ t('recepcionUi.marcaLegacyAyuda') }}</p>
                <button
                    type="button"
                    class="ds-focus mt-2 h-control-compact rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2"
                    @click="modoLegacy = false"
                >{{ t('recepcionUi.cambiarMarcaModelo') }}</button>
            </div>
            <template v-else>
            <div>
                <label for="rv-brand" :class="label">{{ t('recepcion_form.marca') }} *</label>
                <select id="rv-brand" v-model="form.brand_id" :aria-invalid="!!form.errors.brand_id" :class="cls('brand_id')">
                    <option value="" disabled>{{ t('recepcion_form.seleccione') }}</option>
                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                </select>
                <p v-if="form.errors.brand_id" :class="errorText" role="alert">{{ form.errors.brand_id }}</p>
            </div>
            <div>
                <label for="rv-model" :class="label">{{ t('recepcion_form.modelo') }} *</label>
                <select id="rv-model" v-model="form.vehicle_model_id" :disabled="!form.brand_id" :aria-invalid="!!form.errors.vehicle_model_id" :class="cls('vehicle_model_id')">
                    <option value="" disabled>{{ form.brand_id ? t('recepcion_form.seleccione') : t('recepcion_form.primerMarca') }}</option>
                    <option v-for="m in availableModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <p v-if="form.errors.vehicle_model_id" :class="errorText" role="alert">{{ form.errors.vehicle_model_id }}</p>
            </div>
            </template>
            <div>
                <label for="rv-year" :class="label">{{ t('recepcion_form.anio') }} *</label>
                <select id="rv-year" v-model="form.year" :aria-invalid="!!form.errors.year" :class="cls('year')">
                    <option value="" disabled>{{ t('recepcion_form.anio') }}</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
                <p v-if="form.errors.year" :class="errorText" role="alert">{{ form.errors.year }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="rv-plate" :class="label">{{ t('recepcion_form.placas') }}</label>
                <input id="rv-plate" v-model="form.plate" type="text" :placeholder="t('recepcion_form.placasPh')" :aria-invalid="!!form.errors.plate" :class="[...cls('plate'), 'uppercase']" />
                <p v-if="form.errors.plate" :class="errorText" role="alert">{{ form.errors.plate }}</p>
            </div>
            <div>
                <label for="rv-vin" :class="label">{{ t('recepcion_form.serie') }}</label>
                <input id="rv-vin" v-model="form.vin" type="text" maxlength="17" :placeholder="t('recepcionUi.seriePh')" :aria-invalid="!!form.errors.vin" :class="[...cls('vin'), 'uppercase']" />
                <p v-if="form.errors.vin" :class="errorText" role="alert">{{ form.errors.vin }}</p>
            </div>
            <div>
                <label for="rv-engine" :class="label">{{ t('recepcion_form.motor') }}</label>
                <input id="rv-engine" v-model="form.engine" type="text" :placeholder="t('recepcion_form.motorPh')" :aria-invalid="!!form.errors.engine" :class="[...cls('engine'), 'uppercase']" />
                <p v-if="form.errors.engine" :class="errorText" role="alert">{{ form.errors.engine }}</p>
            </div>
            <div v-if="showColor">
                <label for="rv-color" :class="label">{{ t('recepcion_form.colorLabel') }}</label>
                <input id="rv-color" v-model="form.color" type="text" :placeholder="t('recepcion_form.colorPh')" :aria-invalid="!!form.errors.color" :class="cls('color')" />
                <p v-if="form.errors.color" :class="errorText" role="alert">{{ form.errors.color }}</p>
            </div>
        </div>
    </div>
</template>
