<script setup>
import { ref, watch } from 'vue';
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
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

    <AppShellLayout>
        <div class="space-y-6">
            <PageHeader title="Productividad de mecánicos" description="Órdenes terminadas, mano de obra y comisiones del período.">
                <Link :href="route('reports.index')" class="text-ds-body font-semibold text-navy hover:underline">← Volver a reportes</Link>
            </PageHeader>

            <!-- Selectores de período -->
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-line bg-surface p-4 sm:grid-cols-2">
                <div>
                    <label class="block text-ds-label text-ink-muted mb-1.5">Mes</label>
                    <select v-model="selectedMonth" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body">
                        <option v-for="(m, i) in meses" :key="i" :value="i + 1">{{ m }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-ds-label text-ink-muted mb-1.5">Año</label>
                    <select v-model="selectedYear" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- Resumen -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-line bg-surface p-4">
                    <p class="text-ds-label text-ink-muted">Órdenes terminadas</p>
                    <p class="mt-1 text-ds-metric text-navy tabular-nums">{{ totals.orders }}</p>
                </div>
                <div class="rounded-xl border border-line bg-surface p-4">
                    <p class="text-ds-label text-ink-muted">Mano de obra (sin IVA)</p>
                    <p class="mt-1 text-ds-metric text-navy tabular-nums">{{ money(totals.labor_total) }}</p>
                </div>
                <div class="rounded-xl border border-line bg-surface p-4">
                    <p class="text-ds-label text-ink-muted">Comisiones a pagar</p>
                    <p class="mt-1 text-ds-metric text-success-text tabular-nums">{{ money(totals.commission) }}</p>
                    <p class="mt-1 text-ds-caption text-ink-muted">Fija {{ money(totals.commission_fixed) }} · Histórica {{ money(totals.commission_legacy) }}</p>
                </div>
            </div>

            <!-- Tabla -->
            <div class="rounded-xl border border-line bg-surface overflow-x-auto">
                <table class="min-w-full text-ds-body">
                    <thead class="bg-surface-2 text-ds-label text-ink-muted">
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
                    <tbody class="divide-y divide-line">
                        <tr v-for="row in rows" :key="row.name" class="hover:bg-surface-2">
                            <td class="px-4 py-3 font-semibold text-navy">{{ row.name }}</td>
                            <td class="px-4 py-3 text-right">{{ row.orders }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money(row.labor_total) }}</td>
                            <td class="px-4 py-3 text-right">{{ row.commission_rate }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money(row.commission_legacy) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money(row.commission_fixed) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ money(row.commission) }}</td>
                            <td class="px-4 py-3 text-right">{{ row.avg_days ?? '—' }}</td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-ink-muted">No hay mecánicos registrados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-ds-caption text-ink-muted">
                Las comisiones fijas se contabilizan en el período en que se registró el trabajo (fecha del concepto), no en la última actualización de la orden. Es un monto por servicio, tomado del concepto al crearlo (una vez, sin multiplicar por horas), y se atribuye al mecánico que ejecutó el trabajo. El porcentaje histórico mantiene el criterio anterior: órdenes en estado Reparado o Entregado cuya última actualización cae en el mes, sobre conceptos anteriores a la transición. Los días promedio van de la fecha de entrada a la fecha de entrega.
            </p>
        </div>
    </AppShellLayout>
</template>
