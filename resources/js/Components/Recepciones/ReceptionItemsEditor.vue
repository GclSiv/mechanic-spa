<script setup>
// Elementos recibidos (Recepción V2): filas libres con nombre, cantidad, unidad y observación.
// Solo edita el arreglo que llega por props. Al guardar, el backend reemplaza la lista completa.
import { useI18n } from 'vue-i18n';

const props = defineProps({ form: { type: Object, required: true } });
const { t } = useI18n();

const input = 'ds-focus h-control w-full rounded-lg border border-line-strong bg-surface px-3 text-ds-control text-ink placeholder:text-ink-subtle';
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorText = 'mt-1 text-ds-caption text-danger-text';

const agregar = () => props.form.items.push({ name: '', quantity: 1, unit: '', observation: '' });
const quitar = (i) => props.form.items.splice(i, 1);
const errorDe = (i, campo) => props.form.errors[`items.${i}.${campo}`];
</script>

<template>
    <div>
        <h3 class="text-ds-section-title text-navy">{{ t('recepcionV2.elemTitulo') }}</h3>
        <p class="mt-1 text-ds-caption text-ink-muted">{{ t('recepcionV2.elemAyuda') }}</p>

        <p v-if="!form.items.length" class="mt-3 text-ds-caption text-ink-muted">{{ t('recepcionV2.elemVacio') }}</p>

        <ul v-else class="mt-3 space-y-3">
            <li v-for="(fila, i) in form.items" :key="i" class="grid grid-cols-1 gap-3 rounded-lg border border-line p-3 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label :for="`ie-name-${i}`" :class="label">{{ t('recepcionV2.elemNombre') }}</label>
                    <input :id="`ie-name-${i}`" v-model="fila.name" type="text" maxlength="120" :aria-invalid="!!errorDe(i, 'name')"
                        :class="[input, errorDe(i, 'name') ? 'border-danger' : '']" />
                    <p v-if="errorDe(i, 'name')" :class="errorText" role="alert">{{ errorDe(i, 'name') }}</p>
                </div>
                <div class="md:col-span-2">
                    <label :for="`ie-qty-${i}`" :class="label">{{ t('recepcionV2.elemCantidad') }}</label>
                    <input :id="`ie-qty-${i}`" v-model="fila.quantity" type="number" min="1" max="9999" inputmode="numeric" :aria-invalid="!!errorDe(i, 'quantity')"
                        :class="[input, errorDe(i, 'quantity') ? 'border-danger' : '']" />
                    <p v-if="errorDe(i, 'quantity')" :class="errorText" role="alert">{{ errorDe(i, 'quantity') }}</p>
                </div>
                <div class="md:col-span-2">
                    <label :for="`ie-unit-${i}`" :class="label">{{ t('recepcionV2.elemUnidad') }}</label>
                    <input :id="`ie-unit-${i}`" v-model="fila.unit" type="text" maxlength="30" :placeholder="t('recepcionV2.elemUnidadPh')" :class="input" />
                </div>
                <div class="md:col-span-3">
                    <label :for="`ie-obs-${i}`" :class="label">{{ t('recepcionV2.elemObs') }}</label>
                    <input :id="`ie-obs-${i}`" v-model="fila.observation" type="text" maxlength="255" :class="input" />
                </div>
                <div class="flex items-end md:col-span-1">
                    <button type="button" class="ds-focus h-control-compact w-full rounded-lg border border-line px-3 text-ds-control font-semibold text-danger-text hover:bg-danger-soft"
                        :aria-label="t('recepcionV2.elemQuitarAria', { n: i + 1 })" @click="quitar(i)">{{ t('recepcionV2.elemQuitar') }}</button>
                </div>
            </li>
        </ul>

        <p v-if="form.errors.items" :class="errorText" role="alert">{{ form.errors.items }}</p>

        <button type="button" class="ds-focus mt-3 h-control-compact rounded-lg border border-line-strong bg-surface px-3 text-ds-control font-semibold text-navy hover:bg-surface-2" @click="agregar">
            {{ t('recepcionV2.elemAgregar') }}
        </button>
    </div>
</template>
