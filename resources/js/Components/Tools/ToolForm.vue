<script setup>
// Formulario de herramienta (alta y edición). Solo presenta y envía; el backend valida y es la autoridad.
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CLAVE_CONDICION, CLAVE_ESTADO } from '@/Components/Tools/presentacion.js';

const { t } = useI18n();

const props = defineProps({
    tool:        { type: Object, default: null },
    categories:  { type: Array, default: () => [] },
    locations:   { type: Array, default: () => [] },
    estados:     { type: Array, default: () => [] },
    condiciones: { type: Array, default: () => [] },
});

const form = useForm({
    category_id:   props.tool?.category_id ?? '',
    location_id:   props.tool?.location_id ?? '',
    name:          props.tool?.name ?? '',
    brand:         props.tool?.brand ?? '',
    model:         props.tool?.model ?? '',
    serial_number: props.tool?.serial_number ?? '',
    internal_code: props.tool?.internal_code ?? '',
    purchase_date: props.tool?.purchase_date ? String(props.tool.purchase_date).slice(0, 10) : '',
    purchase_cost: props.tool?.purchase_cost ?? '',
    status:        props.tool?.status ?? 'disponible',
    condition:     props.tool?.condition ?? '',
    notes:         props.tool?.notes ?? '',
    evidencia:     null,
});

// Editar usa PUT, pero un archivo solo llega con POST: el método real viaja en _method.
function submit() {
    if (props.tool) {
        form.transform((datos) => ({ ...datos, _method: 'PUT' })).post(route('tools.update', props.tool.id));
    } else {
        form.post(route('tools.store'));
    }
}

const ACEPTADOS = 'image/jpeg,image/png,image/webp,video/mp4,video/webm';
const input = 'ds-focus h-control w-full min-w-0 rounded-lg border bg-surface px-3 text-ds-body text-ink';
const inputClase = (campo) => [input, form.errors[campo] ? 'border-danger' : 'border-line'];
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorTexto = 'mt-1 text-ds-caption text-danger-text';
const seccion = 'min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5';
const tituloSeccion = 'text-ds-section-title font-semibold text-navy';
</script>

<template>
    <form class="space-y-6" novalidate @submit.prevent="submit">
        <section :class="seccion" aria-labelledby="tf-identificacion">
            <h2 id="tf-identificacion" :class="tituloSeccion">{{ t('herramientasUi.seccionIdentificacion') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="tf-nombre" :class="label">{{ t('herramientasUi.campoNombre') }}</label>
                    <input id="tf-nombre" v-model="form.name" type="text" :aria-invalid="!!form.errors.name" :class="inputClase('name')" />
                    <p v-if="form.errors.name" :class="errorTexto" role="alert">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="tf-codigo" :class="label">{{ t('herramientasUi.campoCodigo') }}</label>
                    <input id="tf-codigo" v-model="form.internal_code" type="text" class="font-mono uppercase" :aria-invalid="!!form.errors.internal_code" :class="inputClase('internal_code')" />
                    <p v-if="form.errors.internal_code" :class="errorTexto" role="alert">{{ form.errors.internal_code }}</p>
                    <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('herramientasUi.codigoAyuda') }}</p>
                </div>
                <div>
                    <label for="tf-serie" :class="label">{{ t('herramientasUi.campoSerie') }}</label>
                    <input id="tf-serie" v-model="form.serial_number" type="text" class="font-mono" :aria-invalid="!!form.errors.serial_number" :class="inputClase('serial_number')" />
                    <p v-if="form.errors.serial_number" :class="errorTexto" role="alert">{{ form.errors.serial_number }}</p>
                </div>
                <div>
                    <label for="tf-marca" :class="label">{{ t('herramientasUi.campoMarca') }}</label>
                    <input id="tf-marca" v-model="form.brand" type="text" :aria-invalid="!!form.errors.brand" :class="inputClase('brand')" />
                    <p v-if="form.errors.brand" :class="errorTexto" role="alert">{{ form.errors.brand }}</p>
                </div>
                <div>
                    <label for="tf-modelo" :class="label">{{ t('herramientasUi.campoModelo') }}</label>
                    <input id="tf-modelo" v-model="form.model" type="text" :aria-invalid="!!form.errors.model" :class="inputClase('model')" />
                    <p v-if="form.errors.model" :class="errorTexto" role="alert">{{ form.errors.model }}</p>
                </div>
                <div>
                    <label for="tf-categoria" :class="label">{{ t('herramientasUi.campoCategoria') }}</label>
                    <select id="tf-categoria" v-model="form.category_id" :aria-invalid="!!form.errors.category_id" :class="inputClase('category_id')">
                        <option value="">{{ t('herramientasUi.sinCategoria') }}</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="form.errors.category_id" :class="errorTexto" role="alert">{{ form.errors.category_id }}</p>
                </div>
                <div>
                    <label for="tf-ubicacion" :class="label">{{ t('herramientasUi.campoUbicacion') }}</label>
                    <select id="tf-ubicacion" v-model="form.location_id" :aria-invalid="!!form.errors.location_id" :class="inputClase('location_id')">
                        <option value="">{{ t('herramientasUi.sinUbicacion') }}</option>
                        <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                    <p v-if="form.errors.location_id" :class="errorTexto" role="alert">{{ form.errors.location_id }}</p>
                </div>
            </div>
        </section>

        <section :class="seccion" aria-labelledby="tf-compra">
            <h2 id="tf-compra" :class="tituloSeccion">{{ t('herramientasUi.seccionAdministrativos') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="tf-fecha" :class="label">{{ t('herramientasUi.campoFechaCompra') }}</label>
                    <input id="tf-fecha" v-model="form.purchase_date" type="date" :aria-invalid="!!form.errors.purchase_date" :class="inputClase('purchase_date')" />
                    <p v-if="form.errors.purchase_date" :class="errorTexto" role="alert">{{ form.errors.purchase_date }}</p>
                </div>
                <div>
                    <label for="tf-costo" :class="label">{{ t('herramientasUi.campoCosto') }}</label>
                    <input id="tf-costo" v-model="form.purchase_cost" type="number" min="0" step="0.01" inputmode="decimal" :aria-invalid="!!form.errors.purchase_cost" :class="inputClase('purchase_cost')" />
                    <p v-if="form.errors.purchase_cost" :class="errorTexto" role="alert">{{ form.errors.purchase_cost }}</p>
                    <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('herramientasUi.costoAyuda') }}</p>
                </div>
                <div>
                    <label for="tf-estado" :class="label">{{ t('herramientasUi.campoEstado') }}</label>
                    <select id="tf-estado" v-model="form.status" :aria-invalid="!!form.errors.status" :class="inputClase('status')">
                        <option v-for="e in estados" :key="e" :value="e">{{ t(`herramientasUi.${CLAVE_ESTADO[e] ?? 'estado_disponible'}`) }}</option>
                    </select>
                    <p v-if="form.errors.status" :class="errorTexto" role="alert">{{ form.errors.status }}</p>
                </div>
                <div>
                    <label for="tf-condicion" :class="label">{{ t('herramientasUi.campoCondicion') }}</label>
                    <select id="tf-condicion" v-model="form.condition" :aria-invalid="!!form.errors.condition" :class="inputClase('condition')">
                        <option value="">{{ t('herramientasUi.sinEspecificar') }}</option>
                        <option v-for="c in condiciones" :key="c" :value="c">{{ t(`herramientasUi.${CLAVE_CONDICION[c] ?? 'condicion_buena'}`) }}</option>
                    </select>
                    <p v-if="form.errors.condition" :class="errorTexto" role="alert">{{ form.errors.condition }}</p>
                </div>
                <div class="md:col-span-2">
                    <label for="tf-notas" :class="label">{{ t('herramientasUi.campoNotas') }}</label>
                    <textarea id="tf-notas" v-model="form.notes" rows="3" :aria-invalid="!!form.errors.notes" :class="[input, 'h-auto py-2', form.errors.notes ? 'border-danger' : 'border-line']"></textarea>
                    <p v-if="form.errors.notes" :class="errorTexto" role="alert">{{ form.errors.notes }}</p>
                </div>
            </div>
        </section>

        <section :class="seccion" aria-labelledby="tf-evidencia">
            <h2 id="tf-evidencia" :class="tituloSeccion">{{ t('herramientasUi.evidenciaTitulo') }}</h2>
            <p class="text-ds-caption text-ink-muted">{{ t('herramientasUi.evidenciaAyuda') }}</p>
            <label for="tf-evidencia-archivo" class="sr-only">{{ t('herramientasUi.evidenciaElegir') }}</label>
            <input
                id="tf-evidencia-archivo"
                type="file"
                :accept="ACEPTADOS"
                :aria-invalid="!!form.errors.evidencia"
                class="ds-focus block w-full min-w-0 text-ds-body text-ink file:mr-3 file:rounded-lg file:border file:border-line file:bg-surface file:px-3 file:py-2 file:font-semibold file:text-navy"
                @change="form.evidencia = $event.target.files[0] ?? null"
            />
            <p v-if="form.errors.evidencia" :class="errorTexto" role="alert">{{ form.errors.evidencia }}</p>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-4 sm:flex-row sm:justify-end">
            <Link :href="route('tools.index')" class="ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2">
                {{ t('herramientasUi.formCancelar') }}
            </Link>
            <button type="submit" :disabled="form.processing" class="ds-focus inline-flex h-control items-center justify-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50">
                {{ form.processing ? t('herramientasUi.formGuardando') : (tool ? t('herramientasUi.formActualizar') : t('herramientasUi.formGuardar')) }}
            </button>
        </div>
    </form>
</template>
