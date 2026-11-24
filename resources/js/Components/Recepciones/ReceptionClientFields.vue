<script setup>
// Campos del cliente compartidos por Crear y Editar. Solo representa el formulario recibido por props:
// no valida ni calcula; los errores llegan de Laravel (form.errors) y se muestran junto al campo.
import { useI18n } from 'vue-i18n';

const props = defineProps({
    form: { type: Object, required: true },
    // Solo en Crear con cliente nuevo: RFC genérico y razón social (Editar no los envía).
    fiscal: { type: Boolean, default: false },
});
const emit = defineEmits(['sinFactura']);
const { t } = useI18n();

const input = 'ds-focus h-control w-full rounded-lg border border-line-strong bg-surface px-3 text-ds-control text-ink placeholder:text-ink-subtle disabled:cursor-not-allowed disabled:bg-surface-2';
const inputError = 'border-danger';
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorText = 'mt-1 text-ds-caption text-danger-text';
const cls = (key) => [input, props.form.errors[key] ? inputError : ''];
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label for="rc-first-name" :class="label">{{ t('recepcion_form.nombre') }} *</label>
            <input id="rc-first-name" v-model="form.first_name" type="text" autocomplete="given-name" :placeholder="t('recepcion_form.nombrePh')"
                :aria-invalid="!!form.errors.first_name" :class="cls('first_name')" />
            <p v-if="form.errors.first_name" :class="errorText" role="alert">{{ form.errors.first_name }}</p>
        </div>
        <div>
            <label for="rc-last-name" :class="label">{{ t('recepcion_form.apellidos') }} *</label>
            <input id="rc-last-name" v-model="form.last_name" type="text" autocomplete="family-name" :placeholder="t('recepcion_form.apellidosPh')"
                :aria-invalid="!!form.errors.last_name" :class="cls('last_name')" />
            <p v-if="form.errors.last_name" :class="errorText" role="alert">{{ form.errors.last_name }}</p>
        </div>
        <div>
            <label for="rc-phone" :class="label">{{ t('recepcion_form.telefono') }}</label>
            <input id="rc-phone" v-model="form.phone" type="tel" autocomplete="tel" :placeholder="t('recepcion_form.telefonoPh')"
                :aria-invalid="!!form.errors.phone" :class="cls('phone')" />
            <p v-if="form.errors.phone" :class="errorText" role="alert">{{ form.errors.phone }}</p>
        </div>
        <div>
            <label for="rc-rfc" :class="label">RFC</label>
            <input id="rc-rfc" v-model="form.rfc" type="text" maxlength="13" :placeholder="t('recepcion_form.rfcPh')"
                :aria-invalid="!!form.errors.rfc" :class="[...cls('rfc'), 'uppercase']" :disabled="fiscal && form.sin_factura" />
            <p v-if="form.errors.rfc" :class="errorText" role="alert">{{ form.errors.rfc }}</p>
            <label v-if="fiscal" class="mt-2 flex items-center gap-2 text-ds-control text-ink-secondary">
                <input v-model="form.sin_factura" type="checkbox" class="ds-focus h-4 w-4 rounded border-line-strong text-navy" @change="emit('sinFactura')" />
                {{ t('recepcionUi.sinFactura') }}
            </label>
        </div>
        <div v-if="fiscal">
            <label for="rc-razon-social" :class="label">{{ t('recepcionUi.razonSocial') }}</label>
            <input id="rc-razon-social" v-model="form.razon_social" type="text" maxlength="255" :placeholder="t('recepcionUi.razonSocialPh')"
                :disabled="form.sin_factura" :aria-invalid="!!form.errors.razon_social" :class="cls('razon_social')" />
            <p v-if="form.errors.razon_social" :class="errorText" role="alert">{{ form.errors.razon_social }}</p>
        </div>
        <div class="md:col-span-2">
            <label for="rc-address" :class="label">{{ t('recepcion_form.direccion') }}</label>
            <input id="rc-address" v-model="form.address" type="text" :placeholder="t('recepcion_form.direccionPh')"
                :aria-invalid="!!form.errors.address" :class="cls('address')" />
            <p v-if="form.errors.address" :class="errorText" role="alert">{{ form.errors.address }}</p>
        </div>
    </div>
</template>
