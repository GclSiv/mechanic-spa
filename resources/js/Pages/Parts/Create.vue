<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({ categories: Array });

const form = useForm({
    category_id: '', name: '', sku: '', cost_price: '', sale_price: '', stock: 0, low_stock_threshold: 2,
});

function submit() { form.post(route('parts.store')); }
</script>

<template>
    <AuthenticatedLayout :title="$t('parts_form.nuevaTitulo')">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">➕ {{ $t('parts_form.nuevaTitulo') }}</h2>
        </template>
        <div class="py-12 min-h-screen transition-colors">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] px-6 py-4">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">t('parts_form.datosRefaccion')</p>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- Categoría -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('inventario.categoria') }}</label>
                            <select v-model="form.category_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                <option value="">{{ $t('inventario.sinCategoria') }}</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('parts_form.nombre') }}</label>
                            <input v-model="form.name" type="text" :placeholder="t('parts_form.nombre')"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('parts_form.sku') }}</label>
                            <input v-model="form.sku" type="text" :placeholder="t('parts_form.skuPh')"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.sku" class="text-red-500 text-xs mt-1">{{ form.errors.sku }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('parts_form.costo') }}</label>
                                <input v-model="form.cost_price" type="number" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                <p v-if="form.errors.cost_price" class="text-red-500 text-xs mt-1">{{ form.errors.cost_price }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('parts_form.venta') }}</label>
                                <input v-model="form.sale_price" type="number" step="0.01" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                <p v-if="form.errors.sale_price" class="text-red-500 text-xs mt-1">{{ form.errors.sale_price }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('parts_form.stock') }}</label>
                                <input v-model="form.stock" type="number" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                <p v-if="form.errors.stock" class="text-red-500 text-xs mt-1">{{ form.errors.stock }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('parts_form.minStock') }}</label>
                                <input v-model="form.low_stock_threshold" type="number" min="0"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                <p class="text-gray-400 text-xs mt-1">{{ t('parts_form.minStockHint') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <a :href="route('parts.index')" class="flex-1 text-center text-gray-500 font-bold py-2.5 rounded-lg hover:bg-gray-100 transition text-sm uppercase">{{ t('acciones.cancelar') }}</a>
                            <button @click="submit" :disabled="form.processing"
                                class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2.5 rounded-lg transition text-sm uppercase shadow-md">
                                {{ form.processing ? $t('parts_form.guardando') : $t('parts_form.guardar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
