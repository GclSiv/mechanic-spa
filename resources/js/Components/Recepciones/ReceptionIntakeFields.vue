<script setup>
// Datos de ingreso (kilometraje y gasolina) y síntomas reportados. Son lo que dice el cliente al llegar,
// no el diagnóstico del taller. Solo representa el formulario recibido por props.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ form: { type: Object, required: true } });
const { t } = useI18n();

// Si el registro trae un nivel anterior fuera de la lista (p. ej. "Medio"), se muestra como opción actual.
const fuelLevels = computed(() => {
    const base = ['E', '1/4', '1/2', '3/4', 'F'];
    const actual = props.form.fuel_level;
    return actual && !base.includes(actual) ? [...base, actual] : base;
});
const odometerDisplay = computed(() => String(parseInt(props.form.miles) || 0).padStart(6, '0').split(''));

const input = 'ds-focus h-control rounded-lg border border-line-strong bg-surface px-3 text-ds-control text-ink placeholder:text-ink-subtle';
const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
const errorText = 'mt-1 text-ds-caption text-danger-text';
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="text-ds-section-title text-navy">{{ t('recepcionUi.secIngreso') }}</h3>
            <div class="mt-3 grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="ri-miles" :class="label">{{ t('recepcion_form.kilometraje') }}</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex gap-1" aria-hidden="true">
                            <span v-for="(digit, i) in odometerDisplay" :key="i" class="flex h-10 w-7 items-center justify-center rounded-sm bg-navy font-mono text-ds-control font-bold text-white">{{ digit }}</span>
                        </div>
                        <input id="ri-miles" v-model="form.miles" type="number" min="0" max="999999" placeholder="0" inputmode="numeric"
                            :aria-invalid="!!form.errors.miles" :class="[input, 'w-32 font-mono', form.errors.miles ? 'border-danger' : '']" />
                    </div>
                    <p v-if="form.errors.miles" :class="errorText" role="alert">{{ form.errors.miles }}</p>
                </div>
                <div>
                    <span :class="label">{{ t('recepcion_form.gasolina') }}</span>
                    <div class="flex flex-wrap gap-2" role="group" :aria-label="t('recepcion_form.gasolina')">
                        <button v-for="level in fuelLevels" :key="level" type="button" :aria-pressed="form.fuel_level === level" @click="form.fuel_level = level"
                            class="ds-focus h-control min-w-[3.5rem] rounded-full border px-3 text-ds-control font-semibold transition-colors"
                            :class="form.fuel_level === level ? 'border-navy bg-navy text-white' : 'border-line-strong bg-surface text-ink-secondary hover:bg-surface-2'">
                            {{ level }}
                        </button>
                    </div>
                    <p v-if="form.errors.fuel_level" :class="errorText" role="alert">{{ form.errors.fuel_level }}</p>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-ds-section-title text-navy">{{ t('recepcionUi.secSintomas') }}</h3>
            <p class="mt-1 text-ds-caption text-ink-muted">{{ t('recepcionUi.sintomasAyuda') }}</p>
            <label for="ri-symptoms" class="sr-only">{{ t('recepcion_form.fallas') }}</label>
            <textarea id="ri-symptoms" v-model="form.symptoms" rows="3" :placeholder="t('recepcion_form.fallasPh')"
                :aria-invalid="!!form.errors.symptoms"
                :class="['ds-focus mt-3 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-ds-control text-ink placeholder:text-ink-subtle', form.errors.symptoms ? 'border-danger' : '']"></textarea>
            <p v-if="form.errors.symptoms" :class="errorText" role="alert">{{ form.errors.symptoms }}</p>
        </div>
    </div>
</template>
