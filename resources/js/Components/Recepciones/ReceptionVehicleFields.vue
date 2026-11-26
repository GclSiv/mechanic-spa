<script setup>
// Datos del vehículo compartidos por Crear y Editar: clasificación, identificación y placa/serie/motor.
// Solo representa el formulario recibido por props; los errores llegan de Laravel. Los catálogos
// de marca y modelo se pueden crear inline (admin/owner); el backend valida duplicados y permisos.
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const props = defineProps({
    form: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    // Color solo se captura al crear (Editar no lo envía al backend).
    showColor: { type: Boolean, default: false },
    // Marca y modelo en texto de un registro anterior (sin FK). Solo Editar lo recibe.
    legacy: { type: Object, default: null },
    vehicleTypes: { type: Array, default: () => [] },
    vehicleSubtypes: { type: Array, default: () => [] },
    fuelTypes: { type: Array, default: () => [] },
    transmissions: { type: Array, default: () => [] },
});
const { t } = useI18n();
const page = usePage();

const input = 'ds-focus h-control w-full rounded-lg border border-line-strong bg-surface px-3 text-ds-control text-ink placeholder:text-ink-subtle disabled:cursor-not-allowed disabled:bg-surface-2';
const inputError = 'border-danger';
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorText = 'mt-1 text-ds-caption text-danger-text';
const seccion = 'text-ds-label font-semibold uppercase tracking-wide text-ink-muted';
const cls = (key) => [input, props.form.errors[key] ? inputError : ''];

// ── Clasificación ─────────────────────────────────────────────────────
const tipoSeleccionado = computed(() => props.vehicleTypes.find((x) => x.id == props.form.vehicle_type_id) ?? null);
const esMotocicleta = computed(() => tipoSeleccionado.value?.code === 'motorcycle');
const subtiposDelTipo = computed(() => props.vehicleSubtypes.filter((s) => s.vehicle_type_id == props.form.vehicle_type_id));

// Cambiar de tipo limpia el subtipo si ya no corresponde al nuevo tipo.
watch(() => props.form.vehicle_type_id, (nuevo, anterior) => {
    if (anterior === undefined || nuevo === anterior) return;
    const compatible = props.vehicleSubtypes.some((s) => s.id == props.form.vehicle_subtype_id && s.vehicle_type_id == nuevo);
    if (!compatible) props.form.vehicle_subtype_id = '';
});

// ── Identificación: marcas y modelos (copia local para poder agregar sin recargar) ──
const marcas = ref(props.brands.map((b) => ({ ...b, vehicle_models: [...(b.vehicle_models ?? [])] })));
watch(() => props.brands, (nuevas) => {
    marcas.value = nuevas.map((b) => ({ ...b, vehicle_models: [...(b.vehicle_models ?? [])] }));
});

// Registro histórico: sin FK de marca, pero con texto. Se muestra como dato real y no como "Seleccione...".
const legacyText = computed(() => [props.legacy?.brand, props.legacy?.model].filter(Boolean).join(' '));
const modoLegacy = ref(!!legacyText.value && !props.form.brand_id);

const availableModels = computed(() => {
    if (!props.form.brand_id) return [];
    const brand = marcas.value.find((b) => b.id == props.form.brand_id);
    return brand ? brand.vehicle_models : [];
});

// Cambiar de marca invalida el modelo elegido.
watch(() => props.form.brand_id, (nuevo, anterior) => {
    if (anterior !== undefined && nuevo !== anterior) props.form.vehicle_model_id = '';
});

const puedeCrearCatalogo = computed(() => !!page.props.auth?.isAdmin);

const marcaAbierta = ref(false);
const marcaNombre = ref('');
const marcaError = ref('');
const creandoMarca = ref(false);

async function crearMarca() {
    marcaError.value = '';
    creandoMarca.value = true;
    try {
        const { data } = await axios.post(route('brands.store'), { name: marcaNombre.value });
        marcas.value = [...marcas.value, data.brand];
        props.form.brand_id = data.brand.id;
        props.form.vehicle_model_id = '';
        marcaAbierta.value = false;
        marcaNombre.value = '';
    } catch (e) {
        marcaError.value = e.response?.data?.errors?.name?.[0] ?? t('clasificacionUi.errorCrear');
    } finally {
        creandoMarca.value = false;
    }
}

const modeloAbierto = ref(false);
const modeloNombre = ref('');
const modeloError = ref('');
const creandoModelo = ref(false);

async function crearModelo() {
    modeloError.value = '';
    creandoModelo.value = true;
    try {
        const { data } = await axios.post(route('brands.models.store', props.form.brand_id), { name: modeloNombre.value });
        const marca = marcas.value.find((b) => b.id == props.form.brand_id);
        if (marca) marca.vehicle_models = [...marca.vehicle_models, data.model];
        props.form.vehicle_model_id = data.model.id;
        modeloAbierto.value = false;
        modeloNombre.value = '';
    } catch (e) {
        modeloError.value = e.response?.data?.errors?.name?.[0] ?? t('clasificacionUi.errorCrear');
    } finally {
        creandoModelo.value = false;
    }
}

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
    <div class="space-y-6">
        <section aria-labelledby="rv-clasificacion">
            <h3 id="rv-clasificacion" :class="seccion">{{ t('clasificacionUi.titulo') }}</h3>
            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="rv-tipo" :class="label">{{ t('clasificacionUi.tipo') }}</label>
                    <select id="rv-tipo" v-model="form.vehicle_type_id" :aria-invalid="!!form.errors.vehicle_type_id" :class="cls('vehicle_type_id')">
                        <option value="">{{ t('clasificacionUi.tipoPh') }}</option>
                        <option v-for="tipo in vehicleTypes" :key="tipo.id" :value="tipo.id">{{ tipo.name }}</option>
                    </select>
                    <p v-if="form.errors.vehicle_type_id" :class="errorText" role="alert">{{ form.errors.vehicle_type_id }}</p>
                </div>
                <div v-if="esMotocicleta">
                    <label for="rv-subtipo" :class="label">{{ t('clasificacionUi.subtipo') }}</label>
                    <select id="rv-subtipo" v-model="form.vehicle_subtype_id" :aria-invalid="!!form.errors.vehicle_subtype_id" :class="cls('vehicle_subtype_id')">
                        <option value="">{{ t('clasificacionUi.seleccione') }}</option>
                        <option v-for="s in subtiposDelTipo" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <p v-if="form.errors.vehicle_subtype_id" :class="errorText" role="alert">{{ form.errors.vehicle_subtype_id }}</p>
                </div>
                <div>
                    <label for="rv-combustible" :class="label">{{ t('clasificacionUi.combustible') }}</label>
                    <select id="rv-combustible" v-model="form.fuel_type_id" :aria-invalid="!!form.errors.fuel_type_id" :class="cls('fuel_type_id')">
                        <option value="">{{ t('clasificacionUi.seleccione') }}</option>
                        <option v-for="f in fuelTypes" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                    <p v-if="form.errors.fuel_type_id" :class="errorText" role="alert">{{ form.errors.fuel_type_id }}</p>
                </div>
                <div>
                    <label for="rv-transmision" :class="label">{{ t('clasificacionUi.transmision') }}</label>
                    <select id="rv-transmision" v-model="form.transmission_id" :aria-invalid="!!form.errors.transmission_id" :class="cls('transmission_id')">
                        <option value="">{{ t('clasificacionUi.seleccione') }}</option>
                        <option v-for="tr in transmissions" :key="tr.id" :value="tr.id">{{ tr.name }}</option>
                    </select>
                    <p v-if="form.errors.transmission_id" :class="errorText" role="alert">{{ form.errors.transmission_id }}</p>
                </div>
            </div>
        </section>

        <section aria-labelledby="rv-identificacion">
            <h3 id="rv-identificacion" :class="seccion">{{ t('clasificacionUi.identificacion') }}</h3>
            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
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
                        <div class="mb-1 flex items-center justify-between">
                            <label for="rv-brand" :class="label.replace('mb-1 block', '')">{{ t('recepcion_form.marca') }} *</label>
                            <button
                                v-if="puedeCrearCatalogo"
                                type="button"
                                class="ds-focus text-ds-caption font-semibold text-navy hover:underline"
                                @click="marcaAbierta = !marcaAbierta"
                            >{{ t('clasificacionUi.nuevaMarca') }}</button>
                        </div>
                        <div v-if="marcaAbierta" class="mb-2 rounded-lg border border-line bg-surface-2 p-3">
                            <label for="rv-nueva-marca" :class="label">{{ t('clasificacionUi.nombreMarca') }}</label>
                            <input id="rv-nueva-marca" v-model="marcaNombre" type="text" maxlength="100" :class="input" @keydown.enter.prevent="crearMarca" />
                            <p v-if="marcaError" :class="errorText" role="alert">{{ marcaError }}</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" :disabled="creandoMarca || !marcaNombre.trim()" class="ds-focus h-control-compact rounded-lg bg-navy px-3 text-ds-control font-semibold text-white disabled:opacity-50" @click="crearMarca">{{ t('clasificacionUi.crear') }}</button>
                                <button type="button" class="ds-focus h-control-compact rounded-lg border border-line px-3 text-ds-control font-semibold text-navy" @click="marcaAbierta = false">{{ t('clasificacionUi.cancelar') }}</button>
                            </div>
                        </div>
                        <select id="rv-brand" v-model="form.brand_id" :aria-invalid="!!form.errors.brand_id" :class="cls('brand_id')">
                            <option value="" disabled>{{ t('recepcion_form.seleccione') }}</option>
                            <option v-for="brand in marcas" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                        </select>
                        <p v-if="form.errors.brand_id" :class="errorText" role="alert">{{ form.errors.brand_id }}</p>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label for="rv-model" :class="label.replace('mb-1 block', '')">{{ t('recepcion_form.modelo') }} *</label>
                            <button
                                v-if="puedeCrearCatalogo && form.brand_id"
                                type="button"
                                class="ds-focus text-ds-caption font-semibold text-navy hover:underline"
                                @click="modeloAbierto = !modeloAbierto"
                            >{{ t('clasificacionUi.nuevoModelo') }}</button>
                        </div>
                        <div v-if="modeloAbierto" class="mb-2 rounded-lg border border-line bg-surface-2 p-3">
                            <label for="rv-nuevo-modelo" :class="label">{{ t('clasificacionUi.nombreModelo') }}</label>
                            <input id="rv-nuevo-modelo" v-model="modeloNombre" type="text" maxlength="100" :class="input" @keydown.enter.prevent="crearModelo" />
                            <p v-if="modeloError" :class="errorText" role="alert">{{ modeloError }}</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" :disabled="creandoModelo || !modeloNombre.trim()" class="ds-focus h-control-compact rounded-lg bg-navy px-3 text-ds-control font-semibold text-white disabled:opacity-50" @click="crearModelo">{{ t('clasificacionUi.crear') }}</button>
                                <button type="button" class="ds-focus h-control-compact rounded-lg border border-line px-3 text-ds-control font-semibold text-navy" @click="modeloAbierto = false">{{ t('clasificacionUi.cancelar') }}</button>
                            </div>
                        </div>
                        <select id="rv-model" v-model="form.vehicle_model_id" :disabled="!form.brand_id" :aria-invalid="!!form.errors.vehicle_model_id" :class="cls('vehicle_model_id')">
                            <option value="" disabled>{{ form.brand_id ? t('recepcion_form.seleccione') : t('recepcion_form.primerMarca') }}</option>
                            <option v-for="m in availableModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <p v-if="form.errors.vehicle_model_id" :class="errorText" role="alert">{{ form.errors.vehicle_model_id }}</p>
                    </div>
                </template>
                <div>
                    <label for="rv-year" :class="label">{{ t('recepcion_form.anio') }}</label>
                    <select id="rv-year" v-model="form.year" :aria-invalid="!!form.errors.year" :class="cls('year')">
                        <option value="">{{ t('recepcion_form.anio') }}</option>
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                    <p v-if="form.errors.year" :class="errorText" role="alert">{{ form.errors.year }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
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
        </section>
    </div>
</template>
