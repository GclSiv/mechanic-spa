<script setup>
// Tus otras importaciones (ref, useForm, etc)...
import OrderTotals from '@/Components/OrderTotals.vue';

// Unifiqué el prop a 'orden' porque así lo llamas en todo el HTML
defineProps({
    orden: Object,
    recepcion: Object,
    financial_breakdown: Object
    
});

const page = usePage();
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
                        <div><span class="font-bold text-gray-900">Vehículo:</span> {{ recepcion.vehicle?.brand_id }} / {{ recepcion.vehicle?.year }}</div>
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
                            </tr>
                            <tr v-if="orden.items.length === 0">
                                <td colspan="5" class="p-10 text-center text-gray-400 italic font-medium">No hay items agregados. Comienza por "+ Agregar Concepto".</td>
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

        <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
           </div>
    </AuthenticatedLayout>
</template>