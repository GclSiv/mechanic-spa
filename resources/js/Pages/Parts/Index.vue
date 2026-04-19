<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ parts: Array });

const flash = computed(() => usePage().props.flash);

function destroy(id) {
    if (confirm('¿Eliminar esta refacción del inventario?')) {
        router.delete(route('parts.destroy', id), { preserveScroll: true });
    }
}
</script>

<template>
    <AuthenticatedLayout title="Inventario">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">📦 Inventario</h2>
                <a :href="route('parts.create')"
                    class="bg-[#10213E] hover:bg-blue-900 text-white font-bold py-2.5 px-5 rounded-lg shadow-md transition-all text-sm uppercase">
                    + Nueva Refacción
                </a>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div v-if="flash?.success"
                    class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm font-medium">
                    ✅ {{ flash.success }}
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">
                            {{ parts.length }} artículos en inventario
                        </p>
                        <p class="text-xs text-red-300 font-bold" v-if="parts.filter(p => p.is_low_stock).length">
                            ⚠️ {{ parts.filter(p => p.is_low_stock).length }} con stock bajo
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider font-bold border-b">
                                <tr>
                                    <th class="px-5 py-3">Nombre</th>
                                    <th class="px-5 py-3">SKU</th>
                                    <th class="px-5 py-3 text-right">Costo</th>
                                    <th class="px-5 py-3 text-right">Venta</th>
                                    <th class="px-5 py-3 text-center">Stock</th>
                                    <th class="px-5 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="p in parts" :key="p.id"
                                    class="hover:bg-gray-50 transition-colors"
                                    :class="p.is_low_stock ? 'bg-red-50' : ''">
                                    <td class="px-5 py-4 font-bold text-[#10213E]">
                                        {{ p.name }}
                                        <span v-if="p.is_low_stock" class="ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-black uppercase">Stock Bajo</span>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-gray-500 text-xs">{{ p.sku }}</td>
                                    <td class="px-5 py-4 text-right text-gray-600">${{ Number(p.cost_price).toFixed(2) }}</td>
                                    <td class="px-5 py-4 text-right font-bold text-green-700">${{ Number(p.sale_price).toFixed(2) }}</td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="font-black text-lg" :class="p.is_low_stock ? 'text-red-600' : 'text-gray-700'">
                                            {{ p.stock }}
                                        </span>
                                        <span class="text-gray-400 text-xs"> / min {{ p.low_stock_threshold }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <a :href="route('parts.edit', p.id)"
                                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                                ✏️ Editar
                                            </a>
                                            <button @click="destroy(p.id)"
                                                class="bg-red-50 hover:bg-red-100 text-[#EE2857] font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                                ✕
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="parts.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">
                                        No hay refacciones registradas. Agrega la primera.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
