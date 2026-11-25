<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    rows: Array,
    totals: Object,
    month: Number,
    year: Number,
});

const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const selectedMonth = ref(props.month);
const selectedYear = ref(props.year);

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 6 }, (_, i) => currentYear - i);

// Recarga los datos del servidor cuando cambia el período
watch([selectedMonth, selectedYear], ([m, y]) => {
    router.get(route('reports.mechanics'), { month: m, year: y }, { preserveState: true, replace: true });
});

const money = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
</script>

<template>
    <Head title="Productividad de mecánicos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-bold text-[#10213E] leading-tight">👷 Productividad de mecánicos</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Órdenes terminadas, mano de obra y comisiones del período.</p>
                </div>
                <Link :href="route('reports.index')" class="text-sm font-bold text-[#10213E] hover:underline">← Volver a reportes</Link>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Selectores de período -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#10213E] uppercase tracking-wide mb-1.5">Mes</label>
                        <select v-model="selectedMonth" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm">
                            <option v-for="(m, i) in meses" :key="i" :value="i + 1">{{ m }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#10213E] uppercase tracking-wide mb-1.5">Año</label>
                        <select v-model="selectedYear" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <p class="text-xs text-gray-500 uppercase font-bold">Órdenes terminadas</p>
                        <p class="text-2xl font-black text-[#10213E] mt-1">{{ totals.orders }}</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <p class="text-xs text-gray-500 uppercase font-bold">Mano de obra (sin IVA)</p>
                        <p class="text-2xl font-black text-[#10213E] mt-1">{{ money(totals.labor_total) }}</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <p class="text-xs text-gray-500 uppercase font-bold">Comisiones a pagar</p>
                        <p class="text-2xl font-black text-emerald-700 mt-1">{{ money(totals.commission) }}</p>
                        <p class="text-xs text-gray-400 mt-1">Fija {{ money(totals.commission_fixed) }} · Histórica {{ money(totals.commission_legacy) }}</p>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Mecánico</th>
                                <th class="px-4 py-3 text-right">Órdenes</th>
                                <th class="px-4 py-3 text-right">Mano de obra</th>
                                <th class="px-4 py-3 text-right">% histórico</th>
                                <th class="px-4 py-3 text-right">Comisión histórica</th>
                                <th class="px-4 py-3 text-right">Comisión fija</th>
                                <th class="px-4 py-3 text-right">Comisión total</th>
                                <th class="px-4 py-3 text-right">Días promedio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in rows" :key="row.name" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-bold text-[#10213E]">{{ row.name }}</td>
                                <td class="px-4 py-3 text-right">{{ row.orders }}</td>
                                <td class="px-4 py-3 text-right">{{ money(row.labor_total) }}</td>
                                <td class="px-4 py-3 text-right">{{ row.commission_rate }}%</td>
                                <td class="px-4 py-3 text-right">{{ money(row.commission_legacy) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(row.commission_fixed) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ money(row.commission) }}</td>
                                <td class="px-4 py-3 text-right">{{ row.avg_days ?? '—' }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-gray-400">No hay mecánicos registrados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-400">
                    Las comisiones fijas se contabilizan en el período en que se registró el trabajo (fecha del concepto), no en la última actualización de la orden. Es un monto por servicio, tomado del concepto al crearlo (una vez, sin multiplicar por horas), y se atribuye al mecánico que ejecutó el trabajo. El porcentaje histórico mantiene el criterio anterior: órdenes en estado Reparado o Entregado cuya última actualización cae en el mes, sobre conceptos anteriores a la transición. Los días promedio van de la fecha de entrada a la fecha de entrega.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
