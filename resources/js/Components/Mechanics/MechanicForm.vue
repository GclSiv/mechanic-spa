<script setup>
// Formulario de Personal (alta y edición). Solo presenta y envía; el backend valida y es la autoridad.
// No calcula comisiones: commission_rate se muestra como dato histórico y no se usa para comisiones nuevas.
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    form: { type: Object, required: true },
    genders: { type: Array, default: () => [] },
    mechanicTypes: { type: Array, default: () => [] },
    // Alta: muestra rol y contraseña inicial. Edición: la contraseña solo aplica a legacy sin cuenta.
    modo: { type: String, default: 'crear' },
    cuentaVinculada: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
    submitBusy: { type: String, required: true },
});

const emit = defineEmits(['submit']);

const input = 'ds-focus h-control w-full min-w-0 rounded-lg border bg-surface px-3 text-ds-body text-ink';
const inputClase = (campo) => [input, props.form.errors[campo] ? 'border-danger' : 'border-line'];
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorTexto = 'mt-1 text-ds-caption text-danger-text';
const seccion = 'min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5';
const tituloSeccion = 'text-ds-section-title font-semibold text-navy';
const mostrarPassword = () => props.modo === 'crear' || !cuentaVinculada;
</script>

<template>
    <form class="space-y-6" novalidate @submit.prevent="emit('submit')">
        <section :class="seccion" aria-labelledby="mf-datos">
            <h2 id="mf-datos" :class="tituloSeccion">{{ t('mechanics_form.datosMecanico') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="mf-nombre" :class="label">{{ t('mechanics_form.nombre') }}</label>
                    <input id="mf-nombre" v-model="form.name" type="text" :placeholder="t('mechanics_form.nombrePh')" :aria-invalid="!!form.errors.name" :class="inputClase('name')" />
                    <p v-if="form.errors.name" :class="errorTexto" role="alert">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="mf-especialidad" :class="label">{{ t('mechanics_form.especialidad') }}</label>
                    <select id="mf-especialidad" v-model="form.mechanic_type_id" :aria-invalid="!!form.errors.mechanic_type_id" :class="inputClase('mechanic_type_id')">
                        <option value="" disabled>{{ t('mechanics_form.seleccione') }}</option>
                        <option v-for="tipo in mechanicTypes" :key="tipo.id" :value="tipo.id">{{ tipo.name }}</option>
                    </select>
                    <p v-if="form.errors.mechanic_type_id" :class="errorTexto" role="alert">{{ form.errors.mechanic_type_id }}</p>
                </div>
                <div>
                    <label for="mf-genero" :class="label">{{ t('mechanics_form.genero') }}</label>
                    <select id="mf-genero" v-model="form.gender_id" :aria-invalid="!!form.errors.gender_id" :class="inputClase('gender_id')">
                        <option value="" disabled>{{ t('mechanics_form.seleccione') }}</option>
                        <option v-for="g in genders" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                    <p v-if="form.errors.gender_id" :class="errorTexto" role="alert">{{ form.errors.gender_id }}</p>
                </div>
                <div>
                    <label for="mf-telefono" :class="label">{{ t('mechanics_form.telefono') }}</label>
                    <input id="mf-telefono" v-model="form.phone" type="text" inputmode="tel" :placeholder="t('mechanics_form.telefonoPh')" :aria-invalid="!!form.errors.phone" :class="inputClase('phone')" />
                    <p v-if="form.errors.phone" :class="errorTexto" role="alert">{{ form.errors.phone }}</p>
                </div>
            </div>
        </section>

        <section :class="seccion" aria-labelledby="mf-cuenta">
            <h2 id="mf-cuenta" :class="tituloSeccion">{{ t('mechanics_form.cuentaAcceso') }}</h2>
            <p v-if="modo === 'editar' && cuentaVinculada" class="text-ds-caption text-ink-muted">{{ t('mechanics_form.linkedAccount') }}</p>
            <p v-else-if="modo === 'editar'" class="text-ds-caption text-ink-muted">{{ t('mechanics_form.noLinkedAccount') }}</p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="mf-email" :class="label">{{ t('mechanics_form.email') }}</label>
                    <input id="mf-email" v-model="form.email" type="email" autocomplete="off" :placeholder="t('mechanics_form.emailPh')" :aria-invalid="!!form.errors.email" :class="inputClase('email')" />
                    <p v-if="form.errors.email" :class="errorTexto" role="alert">{{ form.errors.email }}</p>
                </div>
                <div v-if="modo === 'crear'">
                    <label for="mf-rol" :class="label">{{ t('mechanics_form.rol') }}</label>
                    <select id="mf-rol" v-model="form.role" :aria-invalid="!!form.errors.role" :class="inputClase('role')">
                        <option value="mechanic">{{ t('mechanics_form.rolMecanico') }}</option>
                        <option value="admin">{{ t('mechanics_form.rolAdmin') }}</option>
                    </select>
                    <p v-if="form.errors.role" :class="errorTexto" role="alert">{{ form.errors.role }}</p>
                </div>
                <template v-if="mostrarPassword()">
                    <div>
                        <label for="mf-password" :class="label">{{ t('mechanics_form.passwordInicial') }}</label>
                        <input id="mf-password" v-model="form.password" type="password" autocomplete="new-password" :aria-invalid="!!form.errors.password" :class="inputClase('password')" />
                        <p v-if="form.errors.password" :class="errorTexto" role="alert">{{ form.errors.password }}</p>
                        <p v-else class="mt-1 text-ds-caption text-ink-muted">{{ t('mechanics_form.passwordAyuda') }}</p>
                    </div>
                    <div>
                        <label for="mf-password-confirm" :class="label">{{ t('mechanics_form.passwordConfirmar') }}</label>
                        <input id="mf-password-confirm" v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="inputClase('password_confirmation')" />
                    </div>
                </template>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-4 sm:flex-row sm:justify-end">
            <Link :href="route('mechanics.index')" class="ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2">
                {{ t('acciones.cancelar') }}
            </Link>
            <button type="submit" :disabled="form.processing" class="ds-focus inline-flex h-control items-center justify-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50">
                {{ form.processing ? submitBusy : submitLabel }}
            </button>
        </div>
    </form>
</template>
