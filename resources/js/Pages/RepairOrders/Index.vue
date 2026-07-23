<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import draggable from 'vuedraggable';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

function getLang() { return localStorage.getItem('jk_locale') ?? 'es'; }
const { t } = useI18n();

const props = defineProps({
    statuses:       Array,
    ordersByStatus: Object,
    isAdmin:        Boolean,
});

// ── Helpers financieros ───────────────────────────────────────────────
function totalPagado(order) {
    return (order.payments ?? []).reduce((s, p) => s + Number(p.amount), 0);
}
function saldoPendiente(order) {
    return Number(order.estimated_cost ?? 0) - totalPagado(order);
}

// ── Datos por columna ─────────────────────────────────────────────────
function statusesForColumn(slugs) {
    return (props.statuses ?? []).filter(s => slugs.includes(s.slug));
}
function ordersForStatus(statusId) {
    return props.ordersByStatus?.[statusId] ?? [];
}
function ordersForColumn(slugs) {
    return statusesForColumn(slugs).flatMap(s => ordersForStatus(s.id));
}

// ── Columnas Kanban ───────────────────────────────────────────────────
const columnas = computed(() => [
    { slugs: ['recibido', 'diagnostico'], label: t('taller.pendientes'),   color: 'border-gray-400',   bg: 'bg-gray-50'   },
    { slugs: ['espera-piezas'],           label: t('taller.esperaPiezas'), color: 'border-yellow-400', bg: 'bg-yellow-50' },
    { slugs: ['reparado'],                label: t('taller.enTaller'),     color: 'border-blue-400',   bg: 'bg-blue-50'   },
    { slugs: ['entregado'],               label: t('taller.terminadas'),   color: 'border-green-400',  bg: 'bg-green-50'  },
    { slugs: ['rechazado'],               label: t('taller.rechazadas'),   color: 'border-red-400',    bg: 'bg-red-50'    },
]);

// ── Dropdown estado (admin) ───────────────────────────────────────────
const openStatusDropdown = ref(null);
function toggleStatusDropdown(orderId) {
    openStatusDropdown.value = openStatusDropdown.value === orderId ? null : orderId;
}
function changeStatus(order, statusId) {
    openStatusDropdown.value = null;
    if (statusId === order.status_id) return;
    router.patch(route('repair-orders.status.update', order.id), { status_id: statusId }, {
        preserveScroll: true,
    });
}

// ── Dropdown acciones (3 puntos) ──────────────────────────────────────
const openActionsDropdown = ref(null);
function toggleActionsDropdown(orderId) {
    openActionsDropdown.value = openActionsDropdown.value === orderId ? null : orderId;
}

// ── Drag & Drop ───────────────────────────────────────────────────────
// Construimos listas reactivas por columna para vuedraggable
const columnLists = ref({});

// Inicializar listas cuando cambian los datos
function getColumnList(col) {
    const key = col.slugs.join('-');
    if (!columnLists.value[key]) {
        columnLists.value[key] = [...ordersForColumn(col.slugs)];
    }
    return columnLists.value[key];
}

function onDragEnd(event, targetCol) {
    // Si no se movió entre columnas, ignorar
    if (!event.added) return;

    const order = event.added.element;
    // Encontrar el primer status de la columna destino
    const targetStatuses = statusesForColumn(targetCol.slugs);
    if (!targetStatuses.length) return;

    // Preferir el primer slug de la columna (más general)
    const targetStatus = targetStatuses[0];
    if (targetStatus.id === order.status_id) return;

    router.patch(route('repair-orders.status.update', order.id),
        { status_id: targetStatus.id },
        { preserveScroll: true }
    );
}

// ── Eliminar orden ────────────────────────────────────────────────────
const confirmDel = ref({ show: false, order: null });
function deleteOrder(order) {
    openActionsDropdown.value = null;
    confirmDel.value = { show: true, order };
}
function doDel() {
    router.delete(route('recepcion.destroy', confirmDel.value.order.recepcion_id), {
        preserveScroll: true,
    });
    confirmDel.value = { show: false, order: null };
}

function closeAll() {
    openStatusDropdown.value = null;
    openActionsDropdown.value = null;
}
</script>

<template>
    <AuthenticatedLayout :title="$t('taller.titulo')">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                    🏭 {{ $t('taller.titulo') }}
                </h2>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <span v-for="s in statuses" :key="s.id"
                        class="px-2 py-1 rounded-full font-bold text-[10px] uppercase"
                        :class="s.color_class">
                        {{ $t('status.' + s.slug) }}: {{ ordersForStatus(s.id).length }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 min-h-screen transition-colors">
            <div class="max-w-[1600px] mx-auto sm:px-4 lg:px-6">
                <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4">

                    <!-- ── COLUMNA ──────────────────────────────── -->
                    <div v-for="col in columnas" :key="col.label" class="flex flex-col min-w-0">

                        <!-- Header columna -->
                        <div class="flex items-center justify-between mb-2 px-1">
                            <h3 class="font-black text-xs text-gray-600 uppercase tracking-wider truncate">
                                {{ col.label }}
                            </h3>
                            <span class="bg-white border text-gray-500 text-xs font-black px-2 py-0.5 rounded-full ml-1 shrink-0">
                                {{ ordersForColumn(col.slugs).length }}
                            </span>
                        </div>

                        <!-- Zona de arrastre -->
                        <draggable
                            :list="getColumnList(col)"
                            group="kanban"
                            item-key="id"
                            class="space-y-3 flex-1 min-h-[80px]"
                            ghost-class="opacity-30"
                            chosen-class="shadow-xl rotate-1"
                            drag-class="rotate-2"
                            @change="onDragEnd($event, col)"
                        >
                            <template #item="{ element: order }">
                                <!-- ── TARJETA — un único div raíz ── -->
                                <div class="bg-white rounded-xl shadow-sm border-l-4 p-3 relative cursor-grab active:cursor-grabbing"
                                    :class="col.color">

                                    <!-- Fila superior: folio + menú acciones -->
                                    <div class="flex justify-between items-start mb-2">

                                        <!-- Folio -->
                                        <a :href="route('repair-orders.show', order.id)"
                                            class="font-black text-[#10213E] text-sm hover:underline">
                                            {{ order.folio ?? '#' + order.id }}
                                        </a>

                                        <!-- Controles derechos -->
                                        <div class="flex items-center gap-1">

                                            <!-- Badge / Dropdown estado -->
                                            <div v-if="isAdmin" class="relative">
                                                <button @click.stop="toggleStatusDropdown(order.id)"
                                                    class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full hover:opacity-75 transition"
                                                    :class="order.status?.color_class ?? 'bg-gray-100 text-gray-600'">
                                                    {{ order.status?.slug ? $t('status.' + order.status.slug) : '—' }} ▾
                                                </button>
                                                <div v-if="openStatusDropdown === order.id"
                                                    class="absolute right-0 top-7 z-50 bg-white rounded-xl shadow-xl border border-gray-100 w-44 py-1">
                                                    <p class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase border-b">
                                                        {{ $t('taller.cambiarEstado') }}
                                                    </p>
                                                    <button v-for="s in statuses" :key="s.id"
                                                        @click="changeStatus(order, s.id)"
                                                        class="w-full text-left flex items-center gap-2 px-3 py-2 text-xs hover:bg-gray-50 transition"
                                                        :class="s.id === order.status_id ? 'font-black' : 'font-medium text-gray-700'">
                                                        <span class="w-2 h-2 rounded-full shrink-0" :class="s.color_class"></span>
                                                        {{ $t('status.' + s.slug) }}
                                                        <span v-if="s.id === order.status_id" class="ml-auto text-green-600">✓</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <span v-else
                                                class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
                                                :class="order.status?.color_class ?? 'bg-gray-100 text-gray-600'">
                                                {{ order.status?.slug ? $t('status.' + order.status.slug) : '—' }}
                                            </span>

                                            <!-- ⋮ Dropdown de acciones -->
                                            <div class="relative">
                                                <button @click.stop="toggleActionsDropdown(order.id)"
                                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-black transition">
                                                    ⋮
                                                </button>
                                                <div v-if="openActionsDropdown === order.id"
                                                    class="absolute right-0 top-8 z-50 bg-white rounded-xl shadow-xl border border-gray-100 w-44 py-1 overflow-hidden">

                                                    <!-- Ver Cotización -->
                                                    <a :href="route('repair-orders.show', order.id)"
                                                        class="flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition">
                                                        📋 Ver Cotización
                                                    </a>

                                                    <!-- PDF recepción -->
                                                    <a v-if="order.recepcion_id"
                                                        :href="route('recepcion.pdf', order.recepcion_id) + '?lang=' + getLang()"
                                                        target="_blank"
                                                        class="flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition">
                                                        🖨️ Descargar PDF
                                                    </a>

                                                    <!-- Editar recepción -->
                                                    <a v-if="order.recepcion_id"
                                                        :href="route('recepcion.edit', order.recepcion_id)"
                                                        class="flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition">
                                                        ✏️ Editar Recepción
                                                    </a>

                                                    <!-- Borrar (solo admin) -->
                                                    <div v-if="isAdmin && order.recepcion_id"
                                                        class="border-t border-gray-100 mt-1 pt-1">
                                                        <button @click="deleteOrder(order)"
                                                            class="w-full flex items-center gap-2 px-3 py-2 text-xs text-[#EE2857] hover:bg-red-50 transition font-bold">
                                                            🗑️ Borrar Orden
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cliente -->
                                    <p class="text-sm font-semibold text-gray-800 truncate">
                                        👤 {{ order.recepcion?.client?.first_name ?? '—' }}
                                        {{ order.recepcion?.client?.last_name ?? '' }}
                                    </p>

                                    <!-- Vehículo -->
                                    <p class="text-xs text-gray-500 truncate mt-0.5">
                                        🚗
                                        {{ order.recepcion?.vehicle?.brand?.name ?? 'S/M' }}
                                        {{ order.recepcion?.vehicle?.vehicleModel?.name ?? '' }}
                                        {{ order.recepcion?.vehicle?.year ? '(' + order.recepcion.vehicle.year + ')' : '' }}
                                    </p>

                                    <!-- Mecánico -->
                                    <p class="text-xs mt-1"
                                        :class="order.mechanic ? 'text-blue-600' : 'text-amber-500'">
                                        🔧 {{ order.mechanic?.name ?? $t('taller.sinAsignar') }}
                                    </p>

                                    <!-- Saldo (solo admin) -->
                                    <div v-if="isAdmin"
                                        class="mt-2 pt-2 border-t border-gray-100 flex justify-between items-center">
                                        <span class="text-xs text-gray-400">{{ $t('taller.saldo') }}</span>
                                        <span class="font-black text-xs"
                                            :class="saldoPendiente(order) <= 0 ? 'text-green-600' : 'text-[#EE2857]'">
                                            {{ saldoPendiente(order) <= 0
                                                ? '✅ ' + $t('taller.liquidado')
                                                : '$' + saldoPendiente(order).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </draggable>

                        <!-- Estado vacío — fuera del draggable (footer slot no existe en vuedraggable v4) -->
                        <div v-if="ordersForColumn(col.slugs).length === 0"
                            class="border-2 border-dashed border-gray-200 rounded-xl p-5 text-center text-gray-300 text-xs mt-1">
                            {{ $t('taller.vacio') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overlay para cerrar dropdowns -->
        <div v-if="openStatusDropdown || openActionsDropdown"
            class="fixed inset-0 z-40"
            @click="closeAll()">
        </div>

        <!-- Modal confirmación eliminar -->
        <ConfirmModal
            :show="confirmDel.show"
            :title="'Eliminar Orden #' + (confirmDel.order?.folio ?? confirmDel.order?.id)"
            message="Se eliminará la orden y su recepción permanentemente."
            confirm-text="Sí, eliminar"
            @confirm="doDel"
            @cancel="confirmDel.show = false"
        />
    </AuthenticatedLayout>
</template>
