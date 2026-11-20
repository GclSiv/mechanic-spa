<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    cotizaciones: Array,
    resumen: Object,
    filtros: Object,
});

const estado = ref(props.filtros.estado);
const buscar = ref(props.filtros.buscar);
let timer = null;

const aplicar = () => router.get(
    route('quotes.follow-up'),
    { estado: estado.value, buscar: buscar.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true }
);

watch(estado, aplicar);
watch(buscar, () => {
    clearTimeout(timer);
    timer = setTimeout(aplicar, 400);
});

const pestanas = [
    { valor: 'todas', texto: 'Todas' },
    { valor: 'pendientes', texto: 'Pendientes' },
    { valor: 'aceptadas', texto: 'Aceptadas' },
    { valor: 'rechazadas', texto: 'Rechazadas' },
];

const etiquetaEstado = (s) => ({ enviada: 'Pendiente', aprobada: 'Aceptada', rechazada: 'Rechazada' }[s] ?? s);
const claseEstado = (s) => ({
    enviada: 'bg-yellow-100 text-yellow-800',
    aprobada: 'bg-emerald-100 text-emerald-800',
    rechazada: 'bg-red-100 text-red-700',
}[s] ?? 'bg-gray-100 text-gray-600');

const dinero = (n) => '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Cotizaciones" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-[#10213E]">Seguimiento de cotizaciones</h2>
        </template>

        <div class="py-6 px-4 max-w-7xl mx-auto space-y-6">
            <!-- Resumen -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                    <p class="text-xs uppercase font-bold text-gray-400">Enviadas</p>
                    <p class="text-2xl font-black text-[#10213E]">{{ resumen.enviadas }}</p>
                </div>
                <div class="bg-yellow-50 rounded-xl shadow-sm p-4 text-center">
                    <p class="text-xs uppercase font-bold text-yellow-700">Pendientes</p>
                    <p class="text-2xl font-black text-yellow-800">{{ resumen.pendientes }}</p>
                </div>
                <div class="bg-emerald-50 rounded-xl shadow-sm p-4 text-center">
                    <p class="text-xs uppercase font-bold text-emerald-700">Aceptadas</p>
                    <p class="text-2xl font-black text-emerald-800">{{ resumen.aceptadas }}</p>
                </div>
                <div class="bg-red-50 rounded-xl shadow-sm p-4 text-center">
                    <p class="text-xs uppercase font-bold text-red-700">Rechazadas</p>
                    <p class="text-2xl font-black text-red-700">{{ resumen.rechazadas }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                    <p class="text-xs uppercase font-bold text-gray-400">Tasa de aceptación</p>
                    <p class="text-2xl font-black text-[#10213E]">{{ resumen.tasa }}%</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-xl shadow-sm p-4 flex flex-col md:flex-row md:items-center gap-3 justify-between">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="p in pestanas" :key="p.valor"
                        @click="estado = p.valor"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide transition"
                        :class="estado === p.valor ? 'bg-[#10213E] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                    >
                        {{ p.texto }}
                    </button>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <input
                        v-model="buscar" type="text"
                        placeholder="Buscar por cliente, teléfono o folio"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full md:w-72 focus:ring-2 focus:ring-[#EE2857] focus:outline-none"
                    />
                    <a
                        :href="route('quotes.follow-up.export', { estado, buscar: buscar || undefined })"
                        class="whitespace-nowrap bg-emerald-600 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide hover:bg-emerald-700 transition"
                    >
                        Exportar Excel
                    </a>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Folio</th>
                            <th class="px-4 py-3 text-left">Cliente</th>
                            <th class="px-4 py-3 text-left">Vehículo</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3 text-left">Enviada</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Respuesta</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in cotizaciones" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-bold text-[#10213E] whitespace-nowrap">
                                {{ c.folio }}
                                <span class="block text-xs font-normal text-gray-400">
                                    {{ c.kind === 'adicional' ? 'Trabajo adicional' : 'Principal' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[#10213E]">{{ c.cliente }}</p>
                                <p class="text-xs text-gray-400">{{ c.telefono ?? 'Sin teléfono' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ c.vehiculo }}</p>
                                <p class="text-xs text-gray-400">{{ c.placa ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-black text-[#10213E] whitespace-nowrap">{{ dinero(c.total) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ c.enviada ?? '—' }}
                                <span v-if="c.dias !== null" class="block text-xs text-gray-400">hace {{ c.dias }} días</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 rounded-full text-xs font-bold uppercase" :class="claseEstado(c.status)">
                                    {{ etiquetaEstado(c.status) }}
                                </span>
                                <span v-if="c.vencida" class="block text-xs text-red-600 font-bold mt-1">Vencida ({{ c.vence }})</span>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="c.status === 'aprobada'">
                                    <p>{{ c.respuesta_por ?? '—' }}</p>
                                    <p class="text-xs text-gray-400">{{ c.respondida }} · {{ c.respuesta_metodo ?? '' }}</p>
                                </template>
                                <template v-else-if="c.status === 'rechazada'">
                                    <p class="text-xs text-gray-400">{{ c.respondida }}</p>
                                    <p class="text-xs text-red-700">{{ c.motivo ?? 'Sin motivo capturado' }}</p>
                                </template>
                                <span v-else class="text-xs text-gray-400">Sin respuesta</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-col items-end gap-2">
                                    <a v-if="c.whatsapp" :href="c.whatsapp" target="_blank" rel="noopener"
                                       class="whitespace-nowrap bg-green-500 text-white px-3 py-1 rounded-lg text-xs font-bold hover:bg-green-600 transition">
                                        WhatsApp
                                    </a>
                                    <span v-else class="text-xs text-gray-400" title="El cliente no tiene teléfono">Sin teléfono</span>
                                    <Link :href="route('repair-orders.show', c.order_id)" class="text-xs font-bold text-[#EE2857] hover:underline">
                                        Ver orden
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="cotizaciones.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400 italic">No hay cotizaciones con este filtro.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
