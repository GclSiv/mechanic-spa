<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    statuses:        Array,
    ordersByStatus:  Object, // { status_id: [orders...] }
});

function totalPagado(order) {
    return (order.payments ?? []).reduce((s, p) => s + Number(p.amount), 0);
}

function saldoPendiente(order) {
    return Number(order.estimated_cost ?? 0) - totalPagado(order);
}

function ordersForStatus(statusId) {
    return props.ordersByStatus?.[statusId] ?? [];
}

// Agrupar estados en columnas visuales
const columnas = [
    { slugs: ['recibido', 'diagnostico'],    label: '🔍 Pendientes',     color: 'border-gray-400'   },
    { slugs: ['espera-piezas'],              label: '📦 Esperando Piezas', color: 'border-yellow-400' },
    { slugs: ['reparado'],                   label: '🔧 En Taller',       color: 'border-blue-400'   },
    { slugs: ['entregado'],                  label: '✅ Terminadas',       color: 'border-green-400'  },
];

function ordersForColumn(slugs) {
    return props.statuses
        .filter(s => slugs.includes(s.slug))
        .flatMap(s => ordersForStatus(s.id));
}
</script>

<template>
    <AuthenticatedLayout title="Tablero de Taller">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                🏭 Tablero de Taller
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- KANBAN GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                    <div v-for="col in columnas" :key="col.label"
                        class="flex flex-col">

                        <!-- Header columna -->
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h3 class="font-black text-sm text-gray-700 uppercase tracking-wider">
                                {{ col.label }}
                            </h3>
                            <span class="bg-gray-200 text-gray-600 text-xs font-black px-2 py-0.5 rounded-full">
                                {{ ordersForColumn(col.slugs).length }}
                            </span>
                        </div>

                        <!-- Columna -->
                        <div class="flex-1 min-h-[200px] space-y-3">

                            <!-- Tarjeta de orden -->
                            <a v-for="order in ordersForColumn(col.slugs)" :key="order.id"
                                :href="route('repair-orders.show', order.id)"
                                class="block bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow border-l-4 p-4 cursor-pointer"
                                :class="col.color">

                                <!-- Folio + Estado -->
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-black text-[#10213E] text-sm">
                                        {{ order.folio ?? '#' + order.id }}
                                    </span>
                                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
                                        :class="order.status?.color_class ?? 'bg-gray-100 text-gray-600'">
                                        {{ order.status?.name ?? '—' }}
                                    </span>
                                </div>

                                <!-- Cliente -->
                                <p class="text-sm font-semibold text-gray-800 truncate">
                                    👤 {{ order.recepcion?.client?.first_name ?? '—' }} {{ order.recepcion?.client?.last_name ?? '' }}
                                </p>

                                <!-- Vehículo -->
                                <p class="text-xs text-gray-500 truncate mt-0.5">
                                    🚗 {{ order.recepcion?.vehicle?.brand?.name ?? 'S/M' }}
                                    {{ order.recepcion?.vehicle?.vehicle_model?.name ?? order.recepcion?.vehicle?.vehicleModel?.name ?? '' }}
                                    {{ order.recepcion?.vehicle?.year ? '(' + order.recepcion.vehicle.year + ')' : '' }}
                                </p>

                                <!-- Mecánico -->
                                <p class="text-xs mt-1" :class="order.mechanic ? 'text-blue-600' : 'text-amber-500'">
                                    🔧 {{ order.mechanic?.name ?? 'Sin asignar' }}
                                </p>

                                <!-- Saldo -->
                                <div class="mt-3 pt-2 border-t border-gray-100 flex justify-between items-center">
                                    <span class="text-xs text-gray-400">Saldo pendiente</span>
                                    <span class="font-black text-sm"
                                        :class="saldoPendiente(order) <= 0 ? 'text-green-600' : 'text-[#EE2857]'">
                                        {{ saldoPendiente(order) <= 0 ? '✅ Liquidado' : '$' + saldoPendiente(order).toFixed(2) }}
                                    </span>
                                </div>
                            </a>

                            <!-- Columna vacía -->
                            <div v-if="ordersForColumn(col.slugs).length === 0"
                                class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center text-gray-400 text-xs italic">
                                Sin órdenes
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
