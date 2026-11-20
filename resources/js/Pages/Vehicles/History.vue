<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    vehicle: Object,
    visitas: Array,
});

const money = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
const km = (n) => (n === null || n === undefined ? '—' : new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2 }).format(Number(n)));
</script>

<template>
    <Head :title="'Historial · ' + vehicle.brand + ' ' + (vehicle.model ?? '')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-bold text-[#10213E] leading-tight">🕘 Historial de servicios</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ vehicle.brand }} {{ vehicle.model }} {{ vehicle.year }} · Placas {{ vehicle.plate || '—' }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <a v-if="vehicle.client_id" :href="route('recepcion.create', { client_id: vehicle.client_id, vehicle_id: vehicle.id })"
                        class="text-sm font-bold bg-[#EE2857] text-white px-3 py-2 rounded-lg hover:bg-red-700">➕ Nueva visita</a>
                    <Link :href="route('clients.index')" class="text-sm font-bold text-[#10213E] hover:underline">← Volver a clientes</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-sm text-gray-700 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><span class="block text-xs font-bold text-gray-400 uppercase">Cliente</span>{{ vehicle.client || '—' }}</div>
                    <div><span class="block text-xs font-bold text-gray-400 uppercase">VIN</span>{{ vehicle.vin || '—' }}</div>
                    <div><span class="block text-xs font-bold text-gray-400 uppercase">Visitas</span>{{ visitas.length }}</div>
                </div>

                <div v-if="visitas.length === 0" class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-400 italic">
                    Este vehículo aún no tiene servicios terminados.
                </div>

                <div v-for="v in visitas" :key="v.folio" class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] text-white px-5 py-3 flex flex-wrap justify-between gap-2 text-sm">
                        <span class="font-bold">{{ vehicle.brand }} {{ vehicle.model }} · {{ v.fecha }}</span>
                        <span class="font-bold">Comprobante {{ v.folio }}</span>
                        <span class="text-xs font-bold px-2 py-1 rounded-full"
                              :class="v.terminado ? 'bg-emerald-100 text-emerald-800' : 'bg-yellow-100 text-yellow-800'">
                            {{ v.terminado ? v.estado : 'En proceso · ' + v.estado }}
                        </span>
                        <div class="flex gap-3 text-xs font-bold">
                            <a v-if="v.ultimo_pago_id" :href="route('payments.ticket', { order: v.id, payment: v.ultimo_pago_id })" target="_blank"
                                class="underline hover:text-gray-200">🧾 Ticket</a>
                            <a v-if="v.saldo > 0" :href="route('repair-orders.show', v.id) + '#pagos'"
                                class="underline hover:text-gray-200">💳 Registrar pago</a>
                        </div>
                        <span v-if="v.saldo > 0" class="w-full sm:w-auto bg-red-600 text-white text-xs font-black uppercase px-2.5 py-1 rounded-full">
                            Saldo pendiente {{ money(v.saldo) }}
                        </span>
                        <span v-else class="bg-green-600 text-white text-xs font-black uppercase px-2.5 py-1 rounded-full">Pagado</span>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                            <div><span class="block text-xs font-bold text-gray-400 uppercase">Odómetro</span>{{ km(v.odometro) }} km</div>
                            <div><span class="block text-xs font-bold text-gray-400 uppercase">Fecha</span>{{ v.fecha || '—' }}</div>
                            <div><span class="block text-xs font-bold text-gray-400 uppercase">Total</span><span class="font-black text-[#10213E]">{{ money(v.total) }}</span></div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-xs uppercase text-gray-400 border-b">
                                    <tr>
                                        <th class="py-2 text-right pr-3">Cant.</th>
                                        <th class="py-2 text-left">Concepto</th>
                                        <th class="py-2 text-right">Importe</th>
                                        <th class="py-2 text-right">Impuestos</th>
                                        <th class="py-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(c, i) in v.conceptos" :key="i">
                                        <td class="py-2 text-right pr-3">{{ c.cantidad.toFixed(2) }}</td>
                                        <td class="py-2">{{ c.codigo ? c.codigo + ' - ' : '' }}{{ c.descripcion }}</td>
                                        <td class="py-2 text-right">{{ money(c.importe) }}</td>
                                        <td class="py-2 text-right">{{ money(c.impuesto) }}</td>
                                        <td class="py-2 text-right font-semibold">{{ money(c.total) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="border-t text-sm">
                                    <tr>
                                        <td colspan="2" class="py-2 text-right font-bold text-gray-500 uppercase text-xs">Totales</td>
                                        <td class="py-2 text-right">{{ money(v.importe) }}</td>
                                        <td class="py-2 text-right">{{ money(v.impuestos) }}</td>
                                        <td class="py-2 text-right font-black text-[#10213E]">{{ money(v.total) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
