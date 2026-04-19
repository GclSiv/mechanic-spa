<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ part: Object });

const form = useForm({
    name: props.part.name,
    sku: props.part.sku,
    cost_price: props.part.cost_price,
    sale_price: props.part.sale_price,
    stock: props.part.stock,
    low_stock_threshold: props.part.low_stock_threshold,
});

function submit() { form.put(route('parts.update', props.part.id)); }
</script>

<template>
    <AuthenticatedLayout title="Editar Refacción">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">✏️ Editar Refacción</h2>
        </template>
        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] px-6 py-4">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">Editando: {{ part.name }}</p>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre *</label>
                            <input v-model="form.name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">SKU / Código *</label>
                            <input v-model="form.sku" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.sku" class="text-red-500 text-xs mt-1">{{ form.errors.sku }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Precio costo *</label>
                                <input v-model="form.cost_price" type="number" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Precio venta *</label>
                                <input v-model="form.sale_price" type="number" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stock actual *</label>
                                <input v-model="form.stock" type="number" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Alerta mínimo *</label>
                                <input v-model="form.low_stock_threshold" type="number" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            </div>
                        </div>
                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <a :href="route('parts.index')" class="flex-1 text-center text-gray-500 font-bold py-2.5 rounded-lg hover:bg-gray-100 transition text-sm uppercase">Cancelar</a>
                            <button @click="submit" :disabled="form.processing"
                                class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2.5 rounded-lg transition text-sm uppercase shadow-md">
                                {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
