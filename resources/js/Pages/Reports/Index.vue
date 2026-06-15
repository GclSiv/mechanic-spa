<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const { t, locale } = useI18n();

// ── Estado reactivo ────────────────────────────────────────────────────
const selectedMonth = ref(new Date().getMonth() + 1); // mes actual
const selectedYear  = ref(new Date().getFullYear());
const generating    = ref(false);

// ── Rango de años: 5 atrás hasta el actual ────────────────────────────
const years = computed(() => {
    const current = new Date().getFullYear();
    const list = [];
    for (let y = current; y >= current - 5; y--) list.push(y);
    return list;
});

// ── Lista de meses desde i18n ─────────────────────────────────────────
const months = computed(() =>
    Array.from({ length: 12 }, (_, i) => ({
        value: i + 1,
        label: t(`reportes.meses.${i + 1}`),
    }))
);

// ── Nombre del mes seleccionado para la vista previa ──────────────────
const selectedMonthLabel = computed(
    () => months.value.find(m => m.value === selectedMonth.value)?.label ?? ''
);

// ── Generación del PDF ────────────────────────────────────────────────
function generatePdf() {
    if (!selectedMonth.value || !selectedYear.value) return;

    generating.value = true;

    // Lee el locale activo desde localStorage (patrón del proyecto)
    const lang = localStorage.getItem('jk_locale') || locale.value || 'es';

    const params = new URLSearchParams({
        month: selectedMonth.value,
        year:  selectedYear.value,
        lang,
    });

    // Abre en pestaña nueva; el PDF hace stream, no descarga forzada
    window.open(`/reportes/pdf?${params.toString()}`, '_blank');

    // Breve delay visual antes de restablecer el botón
    setTimeout(() => { generating.value = false; }, 1500);
}
</script>

<template>
    <Head :title="$t('reportes.titulo')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <span class="text-2xl">📊</span>
                <div>
                    <h2 class="text-xl font-bold text-[#10213E] leading-tight">
                        {{ $t('reportes.titulo') }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $t('reportes.subtitulo') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ── Tarjeta principal ─────────────────────────────── -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                    <!-- Franja de color corporativo -->
                    <div class="h-1.5 bg-[#10213E]"></div>

                    <div class="p-8 space-y-7">

                        <!-- ── Selectores ────────────────────────────── -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                            <!-- Mes -->
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-[#10213E] uppercase tracking-wide">
                                    {{ $t('reportes.mes') }}
                                </label>
                                <select
                                    v-model="selectedMonth"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:ring-2 focus:ring-[#10213E]/30 focus:border-[#10213E]
                                           transition cursor-pointer"
                                >
                                    <option value="" disabled>{{ $t('reportes.seleccionaMes') }}</option>
                                    <option
                                        v-for="m in months"
                                        :key="m.value"
                                        :value="m.value"
                                    >
                                        {{ m.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Año -->
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-[#10213E] uppercase tracking-wide">
                                    {{ $t('reportes.anio') }}
                                </label>
                                <select
                                    v-model="selectedYear"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:ring-2 focus:ring-[#10213E]/30 focus:border-[#10213E]
                                           transition cursor-pointer"
                                >
                                    <option value="" disabled>{{ $t('reportes.seleccionaAnio') }}</option>
                                    <option
                                        v-for="y in years"
                                        :key="y"
                                        :value="y"
                                    >
                                        {{ y }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- ── Vista previa del período seleccionado ─── -->
                        <div
                            v-if="selectedMonth && selectedYear"
                            class="flex items-center gap-3 rounded-lg bg-[#10213E]/5 border border-[#10213E]/10 px-4 py-3"
                        >
                            <span class="text-xl shrink-0">📅</span>
                            <div>
                                <p class="text-xs text-gray-500 leading-none mb-0.5">
                                    {{ $t('reportes.mes') }} / {{ $t('reportes.anio') }}
                                </p>
                                <p class="text-sm font-bold text-[#10213E]">
                                    {{ selectedMonthLabel }} {{ selectedYear }}
                                </p>
                            </div>
                        </div>

                        <!-- ── Botón principal ───────────────────────── -->
                        <button
                            type="button"
                            :disabled="!selectedMonth || !selectedYear || generating"
                            @click="generatePdf"
                            class="w-full flex items-center justify-center gap-2 rounded-xl px-6 py-3.5
                                   text-sm font-bold text-white bg-[#10213E]
                                   hover:bg-[#1a3260] active:scale-[0.98]
                                   disabled:opacity-40 disabled:cursor-not-allowed
                                   transition-all duration-150 shadow-sm"
                        >
                            <svg
                                v-if="generating"
                                class="animate-spin h-4 w-4 text-white shrink-0"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"/>
                                <path  class="opacity-75" fill="currentColor"
                                       d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ generating ? $t('reportes.generando') : $t('reportes.generar') }}
                        </button>

                    </div>
                </div>

                <!-- ── Tarjeta informativa ───────────────────────────── -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex gap-3">
                        <div class="shrink-0 mt-0.5">
                            <div class="w-7 h-7 rounded-full bg-[#10213E]/10 flex items-center justify-center text-sm">
                                ℹ️
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-[#10213E] mb-1">
                                {{ $t('reportes.infoTitulo') }}
                            </p>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ $t('reportes.infoDetalle') }}
                            </p>

                            <!-- Columnas del reporte -->
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span
                                    v-for="col in ['Folio', 'Fecha', 'Cliente / Vehículo', 'Partes', 'Tax / IVA', 'Mano de Obra', 'Total']"
                                    :key="col"
                                    class="inline-block rounded-full bg-[#10213E]/8 text-[#10213E]
                                           text-[10px] font-semibold px-2.5 py-0.5"
                                >
                                    {{ col }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
