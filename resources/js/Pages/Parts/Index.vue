<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    parts:      Array,
    categories: Array,
    filters:    Object,
});

// ── Filtros reactivos (frontend) ──────────────────────────────────────
const search     = ref(props.filters?.search      ?? '');
const categoryId = ref(props.filters?.category_id ?? '');

// Debounce para no hacer request en cada tecla
let debounceTimer = null;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('parts.index'), {
            search:      search.value      || undefined,
            category_id: categoryId.value  || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}

watch(search,     applyFilters);
watch(categoryId, applyFilters);

// ── Alertas de stock bajo (sobre los ya filtrados) ────────────────────
const lowStockCount = computed(() => props.parts.filter(p => p.is_low_stock).length);

// ── Eliminar ──────────────────────────────────────────────────────────
const confirmDel = ref({ show: false, id: null });
function destroy(id) { confirmDel.value = { show: true, id }; }
function doDel() {
    router.delete(route('parts.destroy', confirmDel.value.id), { preserveScroll: true });
    confirmDel.value = { show: false, id: null };
}
</script>

<template>
    <AuthenticatedLayout :title="$t('inventario.titulo')">
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider shrink-0">
                    📦 {{ $t('inventario.titulo') }}
                </h2>
                <div class="flex items-center gap-3">
                    <a :href="route('tools.index')" class="text-sm text-gray-500 hover:text-[#10213E] font-bold transition border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50">🔧 Herramientas</a>
                    <a :href="route('categories.index')"
                        class="text-sm text-gray-500 hover:text-[#10213E] font-bold transition border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50">
                        🏷️ {{ $t('categories.gestionar') }}
                    </a>
                    <a :href="route('parts.create')"
                        class="bg-[#10213E] hover:bg-blue-900 text-white font-bold py-2 px-4 rounded-lg shadow-md transition text-sm uppercase shrink-0">
                        {{ $t('inventario.nueva') }}
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-screen transition-colors">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- ── Barra de búsqueda y filtros ── -->
                <div class="flex flex-col sm:flex-row gap-3">

                    <!-- Buscador -->
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('inventario.buscar')"
                            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E] bg-white"
                        />
                    </div>

                    <!-- Filtro por categoría -->
                    <select
                        v-model="categoryId"
                        class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E] bg-white min-w-[200px]">
                        <option value="">{{ $t('inventario.todasCategorias') }}</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <!-- ── Tabla ── -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">

                    <!-- Header tabla -->
                    <div class="bg-[#10213E] px-6 py-3 flex justify-between items-center">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">
                            {{ parts.length }} {{ $t('inventario.articulos') }}
                            <span v-if="search || categoryId" class="text-blue-300 ml-2">
                                — {{ $t('inventario.filtrado') }}
                            </span>
                        </p>
                        <p v-if="lowStockCount" class="text-red-300 text-xs font-bold">
                            ⚠️ {{ lowStockCount }} {{ $t('inventario.stockBajo') }}
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="overflow-x-auto"><table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider font-bold border-b">
                                <tr>
                                    <th class="px-5 py-3">{{ $t('inventario.nombre') }}</th>
                                    <th class="px-5 py-3">{{ $t('inventario.categoria') }}</th>
                                    <th class="px-5 py-3">{{ $t('inventario.sku') }}</th>
                                    <th class="px-5 py-3 text-right">{{ $t('inventario.costo') }}</th>
                                    <th class="px-5 py-3 text-right">{{ $t('inventario.venta') }}</th>
                                    <th class="px-5 py-3 text-center">{{ $t('inventario.stock') }}</th>
                                    <th class="px-5 py-3 text-center">{{ $t('inventario.acciones') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="p in parts" :key="p.id"
                                    class="hover:bg-gray-50 transition-colors"
                                    :class="p.is_low_stock ? 'bg-red-50' : ''">

                                    <!-- Nombre + badge stock bajo -->
                                    <td class="px-5 py-4 font-bold text-[#10213E]">
                                        {{ p.name }}
                                        <span v-if="p.is_low_stock"
                                            class="ml-2 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-black uppercase">
                                            {{ $t('inventario.badgeStockBajo') }}
                                        </span>
                                    </td>

                                    <!-- Categoría -->
                                    <td class="px-5 py-4">
                                        <span v-if="p.category"
                                            class="inline-block bg-blue-100 text-blue-700 text-[10px] font-black uppercase px-2 py-0.5 rounded-full">
                                            {{ p.category.name }}
                                        </span>
                                        <a v-else :href="route('parts.edit', p.id)"
                                            class="text-gray-400 text-xs italic hover:text-[#EE2857] hover:underline transition"
                                            :title="$t('inventario.clicParaAsignar')">
                                            {{ $t('inventario.sinCategoria') }} →
                                        </a>
                                    </td>

                                    <td class="px-5 py-4 font-mono text-gray-500 text-xs">{{ p.sku }}</td>
                                    <td class="px-5 py-4 text-right text-gray-600">${{ Number(p.cost_price).toFixed(2) }}</td>
                                    <td class="px-5 py-4 text-right font-bold text-green-700">${{ Number(p.sale_price).toFixed(2) }}</td>

                                    <!-- Stock -->
                                    <td class="px-5 py-4 text-center">
                                        <span class="font-black text-lg"
                                            :class="p.is_low_stock ? 'text-red-600' : 'text-gray-700'">
                                            {{ p.stock }}
                                        </span>
                                        <span class="text-gray-400 text-xs"> / min {{ p.low_stock_threshold }}</span>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-5 py-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <a :href="route('parts.edit', p.id)"
                                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                                ✏️ {{ $t('acciones.editar') }}
                                            </a>
                                            <button @click="destroy(p.id)"
                                                class="bg-red-50 hover:bg-red-100 text-[#EE2857] font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                                ✕
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Sin resultados -->
                                <tr v-if="parts.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-400 italic">
                                        <span v-if="search || categoryId">
                                            🔍 {{ $t('inventario.sinResultados') }} "{{ search || $t('inventario.filtroActivo') }}"
                                        </span>
                                        <span v-else>{{ $t('inventario.sinRegistros') }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table></div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="confirmDel.show"
            :title="$t('inventario.eliminarTitulo')"
            :message="$t('inventario.eliminarMsg')"
            :confirm-text="$t('acciones.eliminar')"
            @confirm="doDel"
            @cancel="confirmDel.show = false"
        />
    </AuthenticatedLayout>
</template>
