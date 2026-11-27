<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Stat from '@/Components/UI/Stat.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    filtros: Object,
    reporte: Object,
    estados: Array,
    mecanicos: Array,
    limite_ordenes: Number,
});

const { t, locale } = useI18n();

const money = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
const entero = (n) => new Intl.NumberFormat('es-MX').format(Number(n) || 0);

// ── Filtros ──────────────────────────────────────────────────────────
const form = ref({
    desde: props.filtros.desde,
    hasta: props.filtros.hasta,
    estado_id: props.filtros.estado_id ?? '',
    mecanico_id: props.filtros.mecanico_id ?? '',
});

const aISO = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

function aplicar(desde, hasta) {
    form.value.desde = desde;
    form.value.hasta = hasta;
    enviar();
}

const presets = [
    { label: 'Hoy', rango: () => { const h = new Date(); return [aISO(h), aISO(h)]; } },
    { label: 'Este mes', rango: () => { const h = new Date(); return [aISO(new Date(h.getFullYear(), h.getMonth(), 1)), aISO(new Date(h.getFullYear(), h.getMonth() + 1, 0))]; } },
    { label: 'Mes anterior', rango: () => { const h = new Date(); return [aISO(new Date(h.getFullYear(), h.getMonth() - 1, 1)), aISO(new Date(h.getFullYear(), h.getMonth(), 0))]; } },
    { label: 'Este año', rango: () => { const h = new Date(); return [aISO(new Date(h.getFullYear(), 0, 1)), aISO(new Date(h.getFullYear(), 11, 31))]; } },
];

function enviar() {
    router.get(route('reports.index'), form.value, { preserveState: true, preserveScroll: true, replace: true });
}


// ── Evolución: barra relativa al máximo del período ───────────────────
const maxEvolucion = computed(() => Math.max(1, ...props.reporte.evolucion.map((e) => e.monto)));
const porcentaje = (monto) => Math.round((monto / maxEvolucion.value) * 100);

// ── PDF mensual (sin cambios de lógica: ingreso cobrado por mes) ─────
const meses = computed(() => Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: t(`reportes.meses.${i + 1}`) })));
const anioActual = new Date().getFullYear();
const anios = Array.from({ length: 6 }, (_, i) => anioActual - i);
const pdfMes = ref(new Date().getMonth() + 1);
const pdfAnio = ref(anioActual);

function generarPdf() {
    const lang = localStorage.getItem('jk_locale') || locale.value || 'es';
    const params = new URLSearchParams({ month: pdfMes.value, year: pdfAnio.value, lang });
    window.open(`/reportes/pdf?${params.toString()}`, '_blank');
}

const ind = computed(() => props.reporte.indicadores);
const des = computed(() => props.reporte.desglose);
</script>

<template>
    <Head title="Reportes" />

    <AppShellLayout>
        <div class="space-y-6">
            <PageHeader title="Reportes" description="Ingresos cobrados, trabajo autorizado y saldo pendiente por periodo. Solo para administración." />

            <!-- Filtros -->
            <section class="rounded-xl border border-line bg-surface p-4 space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="p in presets"
                        :key="p.label"
                        type="button"
                        class="ds-focus rounded-full border border-line px-3 py-1 text-ds-caption text-navy hover:bg-surface-2"
                        @click="aplicar(...p.rango())"
                    >
                        {{ p.label }}
                    </button>
                </div>

                <form class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="enviar">
                    <label class="text-ds-label text-ink-muted">
                        Desde
                        <input v-model="form.desde" type="date" class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body" />
                    </label>
                    <label class="text-ds-label text-ink-muted">
                        Hasta
                        <input v-model="form.hasta" type="date" class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body" />
                    </label>
                    <label class="text-ds-label text-ink-muted">
                        Estado de la orden
                        <select v-model="form.estado_id" class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body">
                            <option value="">Todos</option>
                            <option v-for="e in estados" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </label>
                    <label class="text-ds-label text-ink-muted">
                        Mecánico
                        <select v-model="form.mecanico_id" class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body">
                            <option value="">Todos</option>
                            <option v-for="m in mecanicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </label>
                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
                        <button type="submit" class="ds-focus rounded-lg bg-navy px-4 py-2 text-ds-body font-semibold text-white hover:opacity-90">
                            Aplicar filtros
                        </button>
                    </div>
                </form>
                <p class="text-ds-caption text-ink-muted">
                    Periodo: {{ filtros.desde }} a {{ filtros.hasta }}. Pagos: fecha de registro del pago. Órdenes: fecha de creación; reparadas y entregadas son su estado actual. Saldo pendiente actual: no depende del periodo.
                </p>
            </section>

            <!-- Indicadores principales -->
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Ingresos cobrados" :value="money(ind.ingresos_cobrados)" :hint="`${entero(ind.pagos)} pagos registrados`" tone="success" />
                <Stat label="Trabajo autorizado" :value="money(ind.trabajo_autorizado)" hint="Cotizaciones aprobadas del periodo. No es ingreso." />
                <Stat label="Saldo pendiente actual" :value="money(ind.saldo_pendiente)" hint="Órdenes activas al corte de hoy. No depende del rango seleccionado." :tone="ind.saldo_pendiente > 0 ? 'warning' : 'neutral'" />
                <Stat label="Pagos registrados" :value="entero(ind.pagos)" hint="Suma en la tabla de pagos" />
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Órdenes creadas" :value="entero(ind.ordenes_creadas)" hint="Por fecha de creación de la orden" />
                <Stat label="En proceso" :value="entero(ind.ordenes_en_proceso)" />
                <Stat label="Reparadas (estado actual)" :value="entero(ind.ordenes_reparadas_actual)" hint="De órdenes creadas en el periodo. El sistema no guarda la fecha de reparación." />
                <Stat label="Entregadas (estado actual)" :value="entero(ind.ordenes_entregadas_actual)" hint="De órdenes creadas en el periodo. El sistema no guarda la fecha de entrega." />
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Stat label="Clientes atendidos" :value="entero(ind.clientes_atendidos)" />
                <Stat label="Clientes recurrentes" :value="entero(ind.clientes_recurrentes)" hint="Con dos o más órdenes en total" />
                <Stat label="Vehículos atendidos" :value="entero(ind.vehiculos_atendidos)" />
            </section>

            <!-- Desglose -->
            <section class="rounded-xl border border-line bg-surface p-4">
                <h2 class="text-ds-section text-navy mb-3">Desglose de trabajo autorizado y cobro</h2>
                <dl class="grid grid-cols-1 gap-3 text-ds-body sm:grid-cols-2 xl:grid-cols-3">
                    <div class="flex justify-between gap-4 border-b border-line pb-2"><dt class="text-ink-muted">Refacciones (sin IVA)</dt><dd class="tabular-nums">{{ money(des.refacciones) }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-line pb-2"><dt class="text-ink-muted">Mano de obra</dt><dd class="tabular-nums">{{ money(des.mano_obra) }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-line pb-2"><dt class="text-ink-muted">IVA</dt><dd class="tabular-nums">{{ money(des.iva) }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-line pb-2"><dt class="text-ink-muted">Total autorizado</dt><dd class="tabular-nums font-semibold">{{ money(des.total_autorizado) }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-line pb-2"><dt class="text-ink-muted">Total cobrado en el periodo</dt><dd class="tabular-nums font-semibold">{{ money(des.total_cobrado) }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-line pb-2"><dt class="text-ink-muted">Saldo pendiente actual (órdenes activas)</dt><dd class="tabular-nums">{{ money(des.saldo_pendiente) }}</dd></div>
                </dl>
            </section>

            <!-- Evolución -->
            <section class="rounded-xl border border-line bg-surface p-4">
                <h2 class="text-ds-section text-navy mb-3">Evolución de ingresos cobrados</h2>
                <p v-if="reporte.evolucion.length === 0" class="text-ds-body text-ink-muted">Sin cobros en el periodo.</p>
                <ul v-else class="space-y-2">
                    <li v-for="e in reporte.evolucion" :key="e.periodo" class="grid grid-cols-[6rem_1fr_auto] items-center gap-3 text-ds-body">
                        <span class="text-ink-muted tabular-nums">{{ e.periodo }}</span>
                        <div class="h-3 rounded bg-surface-2">
                            <div class="h-3 rounded bg-navy" :style="{ width: porcentaje(e.monto) + '%' }"></div>
                        </div>
                        <span class="tabular-nums">{{ money(e.monto) }} <span class="text-ink-muted">({{ e.pagos }})</span></span>
                    </li>
                </ul>
            </section>

            <!-- Pagos -->
            <section class="rounded-xl border border-line bg-surface overflow-x-auto">
                <h2 class="text-ds-section text-navy p-4 pb-2">Pagos del periodo</h2>
                <table class="min-w-full text-ds-body">
                    <thead class="bg-surface-2 text-ds-label text-ink-muted">
                        <tr>
                            <th class="px-4 py-2 text-left">Fecha</th>
                            <th class="px-4 py-2 text-left">Orden</th>
                            <th class="px-4 py-2 text-left">Cliente</th>
                            <th class="px-4 py-2 text-left">Concepto</th>
                            <th class="px-4 py-2 text-left">Método</th>
                            <th class="px-4 py-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="(p, i) in reporte.pagos" :key="i">
                            <td class="px-4 py-2 tabular-nums">{{ p.fecha }}</td>
                            <td class="px-4 py-2">{{ p.orden ?? '—' }}</td>
                            <td class="px-4 py-2">{{ p.cliente ?? '—' }}</td>
                            <td class="px-4 py-2">{{ p.concepto }}</td>
                            <td class="px-4 py-2">{{ p.metodo ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money(p.monto) }}</td>
                        </tr>
                        <tr v-if="reporte.pagos.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-ink-muted">Sin pagos en el periodo.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Órdenes -->
            <section class="rounded-xl border border-line bg-surface overflow-x-auto">
                <h2 class="text-ds-section text-navy p-4 pb-2">Órdenes del periodo</h2>
                <p class="px-4 pb-2 text-ds-caption text-ink-muted">Se muestran hasta {{ limite_ordenes }} órdenes, las más recientes primero.</p>
                <table class="min-w-full text-ds-body">
                    <thead class="bg-surface-2 text-ds-label text-ink-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">Folio</th>
                            <th class="px-3 py-2 text-left">Fecha</th>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-left">Vehículo</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                            <th class="px-3 py-2 text-right">Refacciones</th>
                            <th class="px-3 py-2 text-right">Mano de obra</th>
                            <th class="px-3 py-2 text-right">IVA</th>
                            <th class="px-3 py-2 text-right">Total autorizado</th>
                            <th class="px-3 py-2 text-right">Pagado</th>
                            <th class="px-3 py-2 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="o in reporte.ordenes" :key="o.folio">
                            <td class="px-3 py-2 font-semibold">{{ o.folio }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ o.fecha }}</td>
                            <td class="px-3 py-2">{{ o.cliente ?? '—' }}</td>
                            <td class="px-3 py-2">{{ o.vehiculo ?? '—' }}</td>
                            <td class="px-3 py-2">{{ o.estado ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ money(o.refacciones) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ money(o.mano_obra) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ money(o.iva) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ money(o.total_autorizado) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ money(o.pagado) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ money(o.saldo) }}</td>
                        </tr>
                        <tr v-if="reporte.ordenes.length === 0">
                            <td colspan="11" class="px-4 py-6 text-center text-ink-muted">Sin órdenes con estos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Productividad y PDF -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Link :href="route('reports.mechanics')" class="ds-focus block rounded-xl border border-line bg-surface p-4 hover:bg-surface-2">
                    <p class="text-ds-body font-semibold text-navy">Productividad de mecánicos</p>
                    <p class="mt-1 text-ds-caption text-ink-muted">Órdenes, mano de obra y comisiones por mes.</p>
                </Link>

                <section class="rounded-xl border border-line bg-surface p-4 space-y-3">
                    <p class="text-ds-body font-semibold text-navy">Reporte mensual de ingresos (PDF)</p>
                    <p class="text-ds-caption text-ink-muted">Ingresos cobrados del mes seleccionado. No incluye montos autorizados como ingreso.</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <select v-model="pdfMes" class="rounded-lg border border-line bg-surface px-3 py-2 text-ds-body">
                            <option v-for="m in meses" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <select v-model="pdfAnio" class="rounded-lg border border-line bg-surface px-3 py-2 text-ds-body">
                            <option v-for="y in anios" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <button type="button" class="ds-focus rounded-lg bg-navy px-4 py-2 text-ds-body font-semibold text-white hover:opacity-90" @click="generarPdf">
                            Generar PDF
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </AppShellLayout>
</template>
