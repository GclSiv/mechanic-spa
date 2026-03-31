<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue'; // Añadimos computed

const props = defineProps({
    orden: Object,
    recepcion: Object
});

const showModal = ref(false);

const form = useForm({
    repair_order_id: props.orden.id,
    description: '',
    quantity: 1,
    unit_price: 0,
    type: 'part'
});

// 🧮 LÓGICA SENIOR: Cálculos en tiempo real para JK Automotive
// Añadimos una variable reactiva para el tipo de impuesto (puedes guardarla en la DB después)
const taxType = ref('CA'); // 'CA' para California, 'MX' para México

const totals = computed(() => {
    const partsSubtotal = props.orden.items
        .filter(i => i.type === 'part')
        .reduce((acc, i) => acc + Number(i.subtotal), 0);

    const laborSubtotal = props.orden.items
        .filter(i => i.type === 'labor')
        .reduce((acc, i) => acc + Number(i.subtotal), 0);

    // 🌍 Lógica Multiregión
    const taxRate = taxType.value === 'MX' ? 0.16 : 0.0875;
    
     const taxAmount = taxRate === 0.0875 ? 0 : laborSubtotal * taxRate;
    return {
        parts: partsSubtotal,
        labor: laborSubtotal,
        tax: taxAmount,

        taxLabel: taxType.value === 'MX' ? 'IVA (16%)' : 'Tax (8.75% on Parts)',
        grandTotal: partsSubtotal + laborSubtotal + taxAmount
    };
});

const submitItem = () => {
    form.post(route('repair-order-items.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        }
    });
};

const imprimirCotizacion = () => {
    window.open(route('cotizacion.pdf', props.orden.id), '_blank');
};
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
                        <div><span class="font-bold text-gray-900">Cliente:</span> {{ recepcion.first_name }}</div>
                        <div><span class="font-bold text-gray-900">Vehículo:</span> {{ recepcion.brand?.name }} / {{
                            recepcion.year }}</div>
                        <div><span class="font-bold text-gray-900">Placas/VIN:</span> {{ recepcion.plate }} / {{
                            recepcion.vin_serial }}</div>
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
                                <td class="p-3 border-b text-right font-bold">${{ Number(item.subtotal).toFixed(2) }}
                                </td>
                            </tr>
                            <tr v-if="orden.items.length === 0">
                                <td colspan="5" class="p-10 text-center text-gray-400 italic font-medium">No hay items
                                    agregados. Comienza por "+ Agregar Concepto".</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end">
                        <div class="w-full max-w-xs space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Subtotal Refacciones:</span>
                                <span>${{ totals.parts.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-green-600 font-bold border-b pb-2">
                                <span>Sin impuestos (Exento):</span>
                                <span>${{ totals.tax.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Subtotal Mano de Obra:</span>
                                <span>${{ totals.labor.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-xl font-black text-gray-900 pt-2 border-t">
                                <span>TOTAL:</span>
                                <span>${{ totals.grandTotal.toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-100">
                <div class="bg-blue-600 p-4 text-white flex justify-between items-center">
                    <h3 class="text-xl font-bold italic">NUEVO CONCEPTO - JK AUTOMOTIVE</h3>
                    <span class="text-xs opacity-80 uppercase tracking-widest">Módulo de Cotización</span>
                </div>

                <form @submit.prevent="submitItem" class="p-8 space-y-6">

                    <div class="bg-gray-50 p-4 rounded-lg border flex items-center justify-between">
                        <span class="text-sm font-bold text-gray-700">Configuración Fiscal:</span>
                        <div class="flex gap-2">
                            <button type="button" @click="taxType = 'CA'"
                                :class="taxType === 'CA' ? 'bg-blue-600 text-white' : 'bg-gray-200'"
                                class="px-3 py-1 rounded text-xs font-bold transition">USA (8.75%)</button>
                            <button type="button" @click="taxType = 'MX'"
                                :class="taxType === 'MX' ? 'bg-green-600 text-white' : 'bg-gray-200'"
                                class="px-3 py-1 rounded text-xs font-bold transition">MÉXICO (16%)</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripción del Servicio o
                            Refacción</label>
                        <textarea v-model="form.description" rows="3"
                            placeholder="Ej: Cambio de balatas cerámicas delanteras y rectificado de discos..."
                            class="w-full border-gray-300 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-600 transition shadow-sm"
                            required></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Cantidad</label>
                            <input v-model="form.quantity" type="number"
                                class="w-full border-gray-300 rounded-xl shadow-sm" min="1">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Precio Unitario</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-400">$</span>
                                <input v-model="form.unit_price" type="number" step="0.01"
                                    class="w-full pl-7 border-gray-300 rounded-xl shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Tipo</label>
                            <select v-model="form.type"
                                class="w-full border-gray-300 rounded-xl shadow-sm bg-gray-50 font-bold text-blue-600">
                                <option value="part">Refacción (Gravable)</option>
                                <option value="labor">Mano de Obra</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-6 border-t">
                        <button type="button" @click="showModal = false"
                            class="flex-1 px-6 py-3 rounded-xl font-bold text-gray-500 hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-black shadow-lg shadow-blue-200 transition transform active:scale-95"
                            :disabled="form.processing">
                            {{ form.processing ? 'PROCESANDO...' : 'AGREGAR A COTIZACIÓN' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>

</template>