<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    statuses:        Array,
    ordersByStatus:  Object,
    isAdmin:         Boolean,
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
function ordersForColumn(slugs) {
    return props.statuses
        .filter(s => slugs.includes(s.slug))
        .flatMap(s => ordersForStatus(s.id));
}

// Columnas Kanban — incluye Rechazado
const columnas = [
    { slugs: ['recibido', 'diagnostico'], label: '🔍 Pendientes',      color: 'border-gray-400',   bg: 'bg-gray-50'   },
    { slugs: ['espera-piezas'],           label: '📦 Esp. Piezas',     color: 'border-yellow-400', bg: 'bg-yellow-50' },
    { slugs: ['reparado'],                label: '🔧 En Taller',        color: 'border-blue-400',   bg: 'bg-blue-50'   },
    { slugs: ['entregado'],               label: '✅ Terminadas',        color: 'border-green-400',  bg: 'bg-green-50'  },
    { slugs: ['rechazado'],               label: '❌ Rechazadas',        color: 'border-red-400',    bg: 'bg-red-50'    },
];

// Dropdown de estado en tarjeta
const openDropdown = ref(null);

function toggleDropdown(orderId) {
    openDropdown.value = openDropdown.value === orderId ? null : orderId;
}

function changeStatus(order, statusId) {
    openDropdown.value = null;
    if (statusId === order.status_id) return;
    router.patch(route('repair-orders.status.update', order.id), { status_id: statusId }, {
        preserveScroll: true,
    });
}

const confirmDel = ref({ show: false, order: null });
function deleteOrder(order) { confirmDel.value = { show: true, order }; }
function doDel() {
    router.delete(route('recepcion.destroy', confirmDel.value.order.recepcion_id), { preserveScroll: true });
    confirmDel.value = { show: false, order: null };
}
</script>

<template>
    <AuthenticatedLayout title="Tablero de Taller">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">🏭 Tablero de Taller</h2>
                <div class="flex gap-2 text-xs text-gray-500">
                    <span v-for="s in statuses" :key="s.id"
                        class="px-2 py-1 rounded-full font-bold"
                        :class="s.color_class">
                        {{ s.name }}: {{ ordersForStatus(s.id).length }}
                    </span>
                </div>
            </div>
        
    <!-- Modal confirmación -->
    <ConfirmModal :show="confirmDel.show"
        :title="'Eliminar orden #' + (confirmDel.order?.folio ?? confirmDel.order?.id)"
        message="Se eliminará la orden y su recepción asociada permanentemente."
        confirm-text="Sí, eliminar" @confirm="doDel" @cancel="confirmDel.show = false" />
</template>

        <div class="py-6">
            <div class="max-w-[1600px] mx-auto sm:px-4 lg:px-6">
                <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4">
                    <div v-for="col in columnas" :key="col.label" class="flex flex-col min-w-0">

                        <!-- Header columna -->
                        <div class="flex items-center justify-between mb-2 px-1">
                            <h3 class="font-black text-xs text-gray-600 uppercase tracking-wider truncate">{{ col.label }}</h3>
                            <span class="bg-white border text-gray-500 text-xs font-black px-2 py-0.5 rounded-full ml-1 shrink-0">
                                {{ ordersForColumn(col.slugs).length }}
                            </span>
                        </div>

                        <!-- Tarjetas -->
                        <div class="space-y-3 flex-1">
                            <div v-for="order in ordersForColumn(col.slugs)" :key="order.id"
                                class="bg-white rounded-xl shadow-sm border-l-4 p-3 relative"
                                :class="col.color">

                                <!-- Folio + Dropdown estado (solo admin) -->
                                <div class="flex justify-between items-start mb-2">
                                    <a :href="route('repair-orders.show', order.id)"
                                        class="font-black text-[#10213E] text-sm hover:underline">
                                        {{ order.folio ?? '#' + order.id }}
                                    </a>

                                    <!-- Dropdown estado -->
                                    <div v-if="isAdmin" class="relative">
                                        <button @click.stop="toggleDropdown(order.id)"
                                            class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full hover:opacity-75 transition"
                                            :class="order.status?.color_class ?? 'bg-gray-100 text-gray-600'">
                                            {{ order.status?.name ?? '—' }} ▾
                                        </button>

                                        <div v-if="openDropdown === order.id"
                                            class="absolute right-0 top-6 z-50 bg-white rounded-xl shadow-xl border border-gray-100 w-44 py-1 overflow-hidden">
                                            <p class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase border-b">Cambiar estado</p>
                                            <button v-for="s in statuses" :key="s.id"
                                                @click="changeStatus(order, s.id)"
                                                class="w-full text-left flex items-center gap-2 px-3 py-2 text-xs hover:bg-gray-50 transition"
                                                :class="s.id === order.status_id ? 'font-black' : 'font-medium text-gray-700'">
                                                <span class="w-2 h-2 rounded-full shrink-0" :class="s.color_class"></span>
                                                {{ s.name }}
                                                <span v-if="s.id === order.status_id" class="ml-auto">✓</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Badge solo vista para mechanic -->
                                    <span v-else
                                        class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
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
                                    {{ order.recepcion?.vehicle?.vehicleModel?.name ?? '' }}
                                    {{ order.recepcion?.vehicle?.year ? '(' + order.recepcion.vehicle.year + ')' : '' }}
                                </p>

                                <!-- Mecánico -->
                                <p class="text-xs mt-1" :class="order.mechanic ? 'text-blue-600' : 'text-amber-500'">
                                    🔧 {{ order.mechanic?.name ?? 'Sin asignar' }}
                                </p>

                                <!-- Saldo — oculto para mecánico -->
                                <div v-if="isAdmin" class="mt-2 pt-2 border-t border-gray-100 flex justify-between items-center">
                                    <span class="text-xs text-gray-400">Saldo</span>
                                    <span class="font-black text-xs"
                                        :class="saldoPendiente(order) <= 0 ? 'text-green-600' : 'text-[#EE2857]'">
                                        {{ saldoPendiente(order) <= 0 ? '✅ Liquidado' : '$' + saldoPendiente(order).toFixed(2) }}
                                    </span>
                                </div>

                                <!-- Botones de accion -->
                                <div class="mt-2 pt-2 border-t border-gray-100 flex items-center gap-1 flex-wrap">
                                    <a :href="route('repair-orders.show', order.id)"
                                        class="flex items-center gap-1 bg-[#10213E] hover:bg-blue-900 text-white text-xs font-bold px-2.5 py-1.5 rounded-lg transition">
                                        📋 Cotización
                                    </a>
                                    <a v-if="order.recepcion_id"
                                        :href="route('recepcion.pdf', order.recepcion_id)"
                                        target="_blank"
                                        class="flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-2.5 py-1.5 rounded-lg transition">
                                        🖨️ PDF
                                    </a>
                                    <a v-if="order.recepcion_id"
                                        :href="route('recepcion.edit', order.recepcion_id)"
                                        class="flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-2.5 py-1.5 rounded-lg transition">
                                        ✏️ Editar
                                    </a>
                                    <a v-if="!order.recepcion_id"
                                        :href="route('recepcion.index')"
                                        class="flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-2.5 py-1.5 rounded-lg transition">
                                        📄 Recepción
                                    </a>
                                    <button v-if="isAdmin"
                                        @click.prevent="deleteOrder(order)"
                                        class="flex items-center gap-1 bg-red-50 hover:bg-red-100 text-[#EE2857] text-xs font-bold px-2.5 py-1.5 rounded-lg transition ml-auto">
                                        🗑️ Borrar
                                    </button>
                                </div>
                            </div>

                            <!-- Columna vacía -->
                            <div v-if="ordersForColumn(col.slugs).length === 0"
                                class="border-2 border-dashed border-gray-200 rounded-xl p-5 text-center text-gray-300 text-xs">
                                Vacío
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cerrar dropdowns al clicar fuera -->
        <div v-if="openDropdown" class="fixed inset-0 z-40" @click="openDropdown = null"></div>
    </AuthenticatedLayout>
</template>
