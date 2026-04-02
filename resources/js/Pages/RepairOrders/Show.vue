<script setup>
import { ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrderTotals from '@/Components/OrderTotals.vue';

defineProps({
    orden: Object,
    recepcion: Object,
    financial_breakdown: Object,
});

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
        <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-gray-800">Agregar Concepto</h3>
                    <button @click="showModal = false; form.reset()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">✕</button>
                </div>

                <div class="space-y-4">
                    <!-- Tipo -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tipo</label>
                        <select v-model="form.type"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="part">Refacción</option>
                            <option value="labor">Mano de Obra</option>
                        </select>
                    </div>
                    <!-- Descripción -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción</label>
                        <input v-model="form.description" type="text" placeholder="Ej. Filtro de aceite"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</p>
                    </div>
                    <!-- Cantidad y Precio -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cantidad</label>
                            <input v-model="form.quantity" type="number" min="0.01" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            <p v-if="form.errors.quantity" class="text-red-500 text-xs mt-1">{{ form.errors.quantity }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Precio Unitario</label>
                            <input v-model="form.unit_price" type="number" min="0" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            <p v-if="form.errors.unit_price" class="text-red-500 text-xs mt-1">{{ form.errors.unit_price }}</p>
                        </div>
                    </div>
                    <!-- Subtotal preview -->
                    <div class="bg-blue-50 rounded-lg px-4 py-2 flex justify-between text-sm font-bold text-blue-800">
                        <span>Subtotal estimado:</span>
                        <span>${{ (Number(form.quantity) * Number(form.unit_price)).toFixed(2) }}</span>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button @click="showModal = false; form.reset()"
                        class="flex-1 border border-gray-300 text-gray-600 font-bold py-2 rounded-lg hover:bg-gray-50 transition text-sm">
                        Cancelar
                    </button>
                    <button @click="submitItem" :disabled="form.processing"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold py-2 rounded-lg transition text-sm">
                        {{ form.processing ? 'Guardando...' : 'Agregar Concepto' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>