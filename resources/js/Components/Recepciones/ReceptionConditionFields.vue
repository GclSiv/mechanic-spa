<script setup>
// Condición y accesorios al ingreso: testigos del tablero e inventario. Solo representa el formulario por props.
// Los testigos vienen del catálogo warning_lights (backend). Son independientes: se pueden marcar varios a la vez.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { iconoTestigo } from '@/Components/Icons/registroTestigos.js';

const props = defineProps({
    form: { type: Object, required: true },
    // Catálogo de testigos: [{ code, name, active? }]. Un testigo desactivado solo aparece si la recepción ya lo tenía.
    lights: { type: Array, default: () => [] },
});
const { t, te } = useI18n();

const witnessOptions = computed(() => props.lights
    .filter((w) => w.active === undefined || w.active || props.form.witnesses.includes(w.code))
    .map((w) => ({
        key: w.code,
        label: te('inspeccion.' + w.code) ? t('inspeccion.' + w.code) : w.name,
        // Icono por clave del catálogo, con respaldo genérico: sin condicionales por código
        icon: iconoTestigo(w.icon),
    })));

const toggle = (list, key) => {
    const idx = list.indexOf(key);
    if (idx === -1) list.push(key);
    else list.splice(idx, 1);
};

const label = 'mb-1 block text-ds-label font-semibold text-ink-secondary';
</script>

<template>
    <div class="space-y-6">
        <div>
            <span :class="label">{{ t('recepcion_form.testigos') }}</span>
            <div class="mt-2 flex flex-wrap gap-3" role="group" :aria-label="t('recepcion_form.testigos')">
                <button v-for="w in witnessOptions" :key="w.key" type="button" :aria-pressed="form.witnesses.includes(w.key)"
                    @click="toggle(form.witnesses, w.key)"
                    class="ds-focus flex w-24 flex-col items-center gap-2 rounded-xl border py-3 text-ds-caption font-semibold transition-colors"
                    :class="form.witnesses.includes(w.key) ? 'border-danger bg-danger-soft text-danger-text' : 'border-line-strong bg-surface text-ink-muted hover:bg-surface-2'">
                    <span class="h-8 w-8" aria-hidden="true">
                        <component :is="w.icon" />
                    </span>
                    <span>{{ w.label }}</span>
                </button>
            </div>
        </div>

    </div>
</template>
