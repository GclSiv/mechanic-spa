<script setup>
// Formulario del catálogo de servicios (alta y edición). Solo presenta y envía; el backend valida.
// Cambiar la comisión o el nombre no altera conceptos ya registrados: cada uno conserva su snapshot.
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    form: { type: Object, required: true },
    enUso: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
    submitBusy: { type: String, required: true },
});

const emit = defineEmits(['submit']);

const input = 'ds-focus h-control w-full min-w-0 rounded-lg border bg-surface px-3 text-ds-body text-ink';
const inputClase = (campo) => [input, props.form.errors[campo] ? 'border-danger' : 'border-line'];
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorTexto = 'mt-1 text-ds-caption text-danger-text';
const seccion = 'min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5';
</script>

<template>
    <form class="space-y-6" novalidate @submit.prevent="emit('submit')">
        <section :class="seccion" aria-labelledby="sf-datos">
            <h2 id="sf-datos" class="text-ds-section-title font-semibold text-navy">{{ t('serviciosUi.datosServicio') }}</h2>
            <p v-if="enUso" class="text-ds-caption text-ink-muted">{{ t('serviciosUi.enUsoAviso') }}</p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="sf-nombre" :class="label">{{ t('serviciosUi.nombre') }}</label>
                    <input id="sf-nombre" v-model="form.name" type="text" :placeholder="t('serviciosUi.nombrePh')" :aria-invalid="!!form.errors.name" :class="inputClase('name')" />
                    <p v-if="form.errors.name" :class="errorTexto" role="alert">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="sf-comision" :class="label">{{ t('serviciosUi.comision') }}</label>
                    <input id="sf-comision" v-model="form.commission_amount" type="number" min="0" max="99999999.99" step="0.01" inputmode="decimal" :aria-invalid="!!form.errors.commission_amount" :class="inputClase('commission_amount')" />
                    <p v-if="form.errors.commission_amount" :class="errorTexto" role="alert">{{ form.errors.commission_amount }}</p>
                    <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('serviciosUi.comisionAyuda') }}</p>
                </div>
                <div class="flex items-end">
                    <label class="ds-focus inline-flex h-control items-center gap-2 text-ds-body text-ink">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-line text-navy" />
                        {{ t('serviciosUi.activo') }}
                    </label>
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-4 sm:flex-row sm:justify-end">
            <Link :href="route('services.index')" class="ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2">
                {{ t('acciones.cancelar') }}
            </Link>
            <button type="submit" :disabled="form.processing" class="ds-focus inline-flex h-control items-center justify-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50">
                {{ form.processing ? submitBusy : submitLabel }}
            </button>
        </div>
    </form>
</template>
