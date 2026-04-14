<script setup>
import OrderTotals from '@/Components/OrderTotals.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import FollowUpLogger from '@/Components/FollowUpLogger.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    orden: Object,
    recepcion: Object,
    financial_breakdown: Object,
    settings: Object,
    statuses: Array,  // Fase 3
    mechanics: Array, // Fase 4
});

const page = usePage();
const showModal = ref(false);

// Función ÚNICA para actualizar el impuesto sin recargar/cerrar el modal
function updateTax(newTax) {
    router.post(route('settings.updateTax'), { iva: newTax }, {
        preserveScroll: true,
        preserveState: true, 
    });
}

const form = useForm({
    description: '',
    type: 'part',
    quantity: 1,
    unit_price: 0,
});

function imprimirCotizacion() {
    window.open(route('repair-orders.pdf', page.props.orden?.id), '_blank');
}

function submitItem() {
    form.post(route('repair-orders.items.store', page.props.orden?.id), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
}

function removeItem(itemId) {
    if (confirm('¿Eliminar este concepto?')) {
        router.delete(route('repair-orders.items.destroy', {
            order: page.props.orden?.id,
            item: itemId,
        }));
    }
}
</script>

<template>
    <AuthenticatedLayout title="Gestión de Cotización">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider leading-tight">
                        Cotización JK - Orden #{{ orden.id }}
                    </h2>
                    <StatusBadge :orden="orden" :statuses="statuses ?? []" />
                </div>
                <button @click="imprimirCotizacion"
                    class="bg-[#10213E] hover:bg-blue-900 text-white font-bold py-2.5 px-5 rounded-lg shadow-md transition-all flex items-center gap-2">
                    🖨️ Descargar PDF
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- DATOS DEL INGRESO -->
                <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-[#10213E]">
                    <h3 class="text-sm font-black mb-4 text-[#10213E] uppercase tracking-widest border-b pb-2">Datos del Ingreso</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-700">
                        <div><span class="font-bold text-gray-400 uppercase text-xs block">Cliente</span> {{ recepcion.client?.first_name }} {{ recepcion.client?.last_name }}</div>
                        <div><span class="font-bold text-gray-400 uppercase text-xs block">Vehículo</span> {{ recepcion.vehicle?.brand?.name ?? recepcion.vehicle?.brand_id }} {{ recepcion.vehicle?.vehicleModel?.name }} / {{ recepcion.vehicle?.year }}</div>
                        <div><span class="font-bold text-gray-400 uppercase text-xs block">Placas/VIN</span> <span class="font-bold uppercase">{{ recepcion.vehicle?.plate }}</span> / {{ recepcion.vehicle?.vin }}</div>
                    </div>
                </div>

                <!-- CONCEPTOS DE LA COTIZACIÓN -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-xl">
                    <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                        <h3 class="text-white font-black text-sm uppercase tracking-wider">Conceptos de la Cotización</h3>
                        <button @click="showModal = true"
                            class="bg-[#EE2857] hover:bg-red-700 text-white px-5 py-2 rounded-lg font-bold shadow-md transition-all text-xs uppercase">
                            + Agregar Concepto
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <table class="w-full text-left border-collapse mb-6">
                            <thead>
                                <tr class="bg-gray-100 text-gray-500 uppercase text-xs tracking-wider font-bold border-b border-gray-200">
                                    <th class="p-3">Cant</th>
                                    <th class="p-3">Tipo</th>
                                    <th class="p-3">Descripción</th>
                                    <th class="p-3 text-right">Unitario</th>
                                    <th class="p-3 text-right">Total Parcial</th>
                                    <th class="p-3 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 text-sm">
                                <tr v-for="item in orden.items" :key="item.id" class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                                    <td class="p-3 font-medium">{{ item.quantity }}</td>
                                    <td class="p-3">
                                        <span :class="item.type === 'part' ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-700'"
                                              class="px-2 py-1 rounded-md text-[10px] font-black uppercase">
                                            {{ item.type === 'part' ? 'Refacción' : 'Mano de Obra' }}
                                        </span>
                                    </td>
                                    <td class="p-3">{{ item.description }}</td>
                                    <td class="p-3 text-right">${{ Number(item.unit_price).toFixed(2) }}</td>
                                    <td class="p-3 text-right font-black">${{ Number(item.subtotal).toFixed(2) }}</td>
                                    <td class="p-3 text-center">
                                        <button @click="removeItem(item.id)" class="text-gray-400 hover:text-[#EE2857] text-sm font-bold transition">
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!orden.items || orden.items.length === 0">
                                    <td colspan="6" class="p-10 text-center text-gray-400 italic font-medium">No hay artículos agregados. Comienza por "+ Agregar Concepto".</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Calculadora Financiera -->
                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200">
                            <OrderTotals :orden="orden" :breakdown="financial_breakdown" />
                        </div>
                    </div>
                </div>

                <!-- FASE 4: MECÁNICO + BITÁCORA (solo cuando la orden está activa) -->
                <FollowUpLogger
                    v-if="['espera-piezas', 'reparado', 'entregado'].includes(orden.status?.slug)"
                    :orden="orden"
                    :mechanics="mechanics ?? []"
                />
            </div>
        </div>

        <!-- Modal: Agregar Concepto -->
        <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                <!-- Header Dinámico con los Colores y Nombre de tu empresa -->
                <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                    <h3 class="text-white font-black text-sm tracking-wider uppercase">
                        Agregar Concepto - {{ settings?.company_name || 'Taller' }}
                    </h3>
                    <button @click="showModal = false; form.reset()" class="text-gray-400 hover:text-white transition-colors text-xl font-bold"> ✕ </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- BOTONES DE CONFIGURACIÓN FISCAL FUNCIONALES -->
                    <div class="flex justify-between items-center bg-gray-100 p-1 rounded-lg mb-2">
                        <span class="text-xs font-bold text-gray-500 pl-3">Configuración Fiscal:</span>
                        <div class="flex gap-1">
                            <button type="button" @click="updateTax(8.75)"
                                    :class="settings?.iva == 8.75 ? 'bg-[#10213E] text-white shadow-sm' : 'bg-transparent text-gray-500 hover:bg-gray-200'"
                                    class="px-4 py-1.5 rounded text-xs font-bold transition-all">
                                USA (8.75%)
                            </button>
                            <button type="button" @click="updateTax(16)"
                                    :class="settings?.iva == 16 ? 'bg-[#10213E] text-white shadow-sm' : 'bg-transparent text-gray-500 hover:bg-gray-200'"
                                    class="px-4 py-1.5 rounded text-xs font-bold transition-all">
                                MÉXICO (16%)
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tipo *</label>
                        <select v-model="form.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                            <option value="part">Refacción (Pieza)</option>
                            <option value="labor">Mano de Obra</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción</label>
                        <input v-model="form.description" type="text" placeholder="Ej. Cambio de balatas"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cantidad</label>
                            <input v-model="form.quantity" type="number" min="0.01" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Precio Unitario</label>
                            <input v-model="form.unit_price" type="number" min="0" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                        </div>
                    </div>
                    
                    <div class="bg-gray-100 rounded-lg px-4 py-3 flex justify-between text-sm font-black text-[#10213E] border border-gray-200 mt-2">
                        <span>Subtotal estimado:</span>
                        <span>${{ (Number(form.quantity) * Number(form.unit_price)).toFixed(2) }}</span>
                    </div>
                    
                    <div class="flex gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button @click="showModal = false; form.reset()"
                            class="flex-1 text-gray-500 font-bold py-2 rounded-lg hover:bg-gray-100 transition text-sm uppercase tracking-wider">
                            Cancelar
                        </button>
                        <button @click="submitItem" :disabled="form.processing"
                            class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2 rounded-lg transition text-sm uppercase tracking-wider shadow-md">
                            {{ form.processing ? 'Guardando...' : 'Agregar Concepto' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>