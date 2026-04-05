<script setup>
import { ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrderTotals from '@/Components/OrderTotals.vue';

defineProps({
    orden: Object,
    recepcion: Object,
    financial_breakdown: Object,
      settings: Object, // <--- Recibimos los settings dinámicos
});
// Función para cambiar el impuesto con un solo clic
function updateTax(newTax) {
    router.post(route('settings.updateTax'), { iva: newTax }, {
        preserveScroll: true,
        preserveState: true, // <--- ¡CAMBIA ESTO A TRUE!
    });
}

const page = usePage();
const showModal = ref(false);

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
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Cotización JK - Orden #{{ orden.id }}
                </h2>
                <button @click="imprimirCotizacion"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow transition">
                    🖨️ Descargar PDF
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-600">
                    <h3 class="text-lg font-bold mb-4 text-blue-900">DATOS DEL INGRESO</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                        <div><span class="font-bold text-gray-900">Cliente:</span> {{ recepcion.client?.first_name }}</div>
                        <div><span class="font-bold text-gray-900">Vehículo:</span> {{ recepcion.vehicle?.brand?.name ?? recepcion.vehicle?.brand_id }} {{ recepcion.vehicle?.vehicleModel?.name }} / {{ recepcion.vehicle?.year }}</div>
                        <div><span class="font-bold text-gray-900">Placas/VIN:</span> {{ recepcion.vehicle?.plate }} / {{ recepcion.vehicle?.vin }}</div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between mb-6">
                        <h3 class="text-lg font-bold">Conceptos de la Cotización</h3>
                        <button @click="showModal = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-bold shadow transition">
                            + Agregar Concepto
                        </button>
                    </div>

                    <table class="w-full text-left border-collapse mb-6">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                                <th class="p-3 border-b">Cant</th>
                                <th class="p-3 border-b">Tipo</th>
                                <th class="p-3 border-b">Descripción</th>
                                <th class="p-3 border-b text-right">Unitario</th>
                                <th class="p-3 border-b text-right">Subtotal</th>
                                <th class="p-3 border-b text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <tr v-for="item in orden.items" :key="item.id" class="hover:bg-gray-50">
                                <td class="p-3 border-b">{{ item.quantity }}</td>
                                <td class="p-3 border-b">
                                    <span
                                        :class="item.type === 'part' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'"
                                        class="px-2 py-1 rounded-full text-[10px] font-bold uppercase">
                                        {{ item.type === 'part' ? 'Refacción' : 'Mano de Obra' }}
                                    </span>
                                </td>
                                <td class="p-3 border-b">{{ item.description }}</td>
                                <td class="p-3 border-b text-right">${{ Number(item.unit_price).toFixed(2) }}</td>
                                <td class="p-3 border-b text-right font-bold">${{ Number(item.subtotal).toFixed(2) }}</td>
                                <td class="p-3 border-b text-center">
                                    <button @click="removeItem(item.id)"
                                        class="text-red-500 hover:text-red-700 text-xs font-bold transition">
                                        ✕
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!orden.items || orden.items.length === 0">
                                <td colspan="6" class="p-10 text-center text-gray-400 italic font-medium">No hay items agregados. Comienza por "+ Agregar Concepto".</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end mt-4 pt-4 border-t border-gray-200">
    <OrderTotals 
        :orden="orden" 
        :breakdown="financial_breakdown" 
    />
</div>

                </div>
            </div>
        </div>

        <!-- Modal: Agregar Concepto -->
    <!-- Modal: Agregar Concepto -->
        <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-200">
                
                <!-- HEADER DEL MODAL DINÁMICO -->
                <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                    <h3 class="text-white font-black text-sm tracking-wider uppercase">
                        Nuevo Concepto - {{ settings?.company_name || 'Taller' }}
                    </h3>
                    <button @click="showModal = false; form.reset()" class="text-white hover:text-red-400 font-bold">✕</button>
                </div>

                <div class="p-6">
                    <!-- BOTONES FISCALES FUNCIONALES -->
                    <div class="flex justify-between items-center bg-gray-100 p-1 rounded-lg mb-6">
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

                    <div class="space-y-4">
                        <!-- Descripción -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción del Servicio o Refacción</label>
                            <textarea v-model="form.description" rows="2" placeholder="Ej: Cambio de balatas cerámicas..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]"></textarea>
                            <p v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</p>
                        </div>

                        <!-- Cantidad, Precio y EL SELECTOR DE TIPO (VITAL PARA EL SISTEMA) -->
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cantidad</label>
                                <input v-model="form.quantity" type="number" min="0.01" step="0.01"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Precio Unit.</label>
                                <input v-model="form.unit_price" type="number" min="0" step="0.01"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tipo *</label>
                                <select v-model="form.type"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                    <option value="part">Refacción (Pieza)</option>
                                    <option value="labor">Mano de Obra</option>
                                </select>
                            </div>
                        </div>

                        <!-- Subtotal preview -->
                        <div class="bg-blue-50 rounded-lg px-4 py-2 flex justify-between text-sm font-bold text-blue-800">
                            <span>Subtotal estimado:</span>
                            <span>${{ (Number(form.quantity) * Number(form.unit_price)).toFixed(2) }}</span>
                        </div>
                    </div>

                    <!-- Botones Inferiores -->
                    <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-100">
                        <button @click="showModal = false; form.reset()" class="text-gray-500 font-bold text-sm hover:text-[#EE2857] transition-colors">
                            Cancelar
                        </button>
                        <button @click="submitItem" :disabled="form.processing"
                                class="bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black px-6 py-3 rounded-lg shadow-md transition-all text-sm uppercase">
                            {{ form.processing ? 'Guardando...' : 'Agregar a Cotización' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>