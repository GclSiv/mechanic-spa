<script setup>
// Condición y accesorios al ingreso: testigos del tablero e inventario. Solo representa el formulario por props.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import IconEngine from '@/Components/Icons/IconEngine.vue';
import IconAbs from '@/Components/Icons/IconAbs.vue';
import IconOil from '@/Components/Icons/IconOil.vue';
import IconBattery from '@/Components/Icons/IconBattery.vue';
import IconTemp from '@/Components/Icons/IconTemp.vue';

const props = defineProps({ form: { type: Object, required: true } });
const { t } = useI18n();

const witnessOptions = computed(() => [
    { key: 'engine', label: t('inspeccion.engine') },
    { key: 'abs', label: t('inspeccion.abs') },
    { key: 'oil', label: t('inspeccion.oil') },
    { key: 'battery', label: t('inspeccion.battery') },
    { key: 'temp', label: t('inspeccion.temp') },
]);

const inventoryOptions = computed(() => [
    { key: 'llanta', label: t('recepcion_form.inv_llanta') },
    { key: 'gato', label: t('recepcion_form.inv_gato') },
    { key: 'estereo', label: t('recepcion_form.inv_estereo') },
    { key: 'tapetes', label: t('recepcion_form.inv_tapetes') },
    { key: 'herramienta', label: t('recepcion_form.inv_herramienta') },
]);

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
                        <IconEngine v-if="w.key === 'engine'" />
                        <IconAbs v-if="w.key === 'abs'" />
                        <IconOil v-if="w.key === 'oil'" />
                        <IconBattery v-if="w.key === 'battery'" />
                        <IconTemp v-if="w.key === 'temp'" />
                    </span>
                    <span>{{ w.label }}</span>
                </button>
            </div>
        </div>

        <div>
            <span :class="label">{{ t('recepcion_form.inventarioIngreso') }}</span>
            <p class="text-ds-caption text-ink-muted">{{ t('recepcion_form.inventarioAyuda') }}</p>
            <div class="mt-2 flex flex-wrap gap-2" role="group" :aria-label="t('recepcion_form.inventarioIngreso')">
                <button v-for="item in inventoryOptions" :key="item.key" type="button" :aria-pressed="form.inventory.includes(item.key)"
                    @click="toggle(form.inventory, item.key)"
                    class="ds-focus h-control-compact rounded-lg border px-3 text-ds-control font-semibold transition-colors"
                    :class="form.inventory.includes(item.key) ? 'border-navy bg-navy text-white' : 'border-line-strong bg-surface text-ink-secondary hover:bg-surface-2'">
                    {{ item.label }}
                </button>
            </div>
        </div>
    </div>
</template>
