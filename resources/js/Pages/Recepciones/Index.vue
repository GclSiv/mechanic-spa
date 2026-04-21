<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({ recepciones: Array });

const page = usePage();
const isAdmin = computed(() => page.props.auth.role === 'admin');

const search = ref('');
const currentPage = ref(1);
const perPage = 15;

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    const list = page.props.recepciones ?? [];
    if (!q) return list;
    return list.filter(r =>
        (r.client?.first_name + ' ' + r.client?.last_name).toLowerCase().includes(q) ||
        r.vehicle?.plate?.toLowerCase().includes(q) ||
        String(r.id).includes(q)
    );
});

const totalPages = computed(() => Math.ceil(filtered.value.length / perPage));

const paginated = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filtered.value.slice(start, start + perPage);
});

// Reset page when searching
function onSearch() { currentPage.value = 1; }

const confirmDel = ref({ show: false, id: null });
function askDelete(id) { confirmDel.value = { show: true, id }; }
function doDel() {
    router.delete(route('recepcion.destroy', confirmDel.value.id), { preserveScroll: true });
    confirmDel.value = { show: false, id: null };
}
</script>

<template>
    <AuthenticatedLayout title="Recepciones">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">🚗 Recepciones</h2>
                <Link :href="route('recepcion.create')"
                    class="bg-[#EE2857] hover:bg-red-700 text-white font-black text-xs uppercase px-5 py-2.5 rounded-lg shadow transition">
                    + Nueva Recepción
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Buscador -->
                <div class="flex items-center gap-3 bg-white rounded-xl shadow-sm px-4 py-3 border border-gray-100">
                    <span class="text-gray-400">🔍</span>
                    <input v-model="search" @input="onSearch" type="text"
                        placeholder="Buscar por cliente, placa o folio..."
                        class="flex-1 text-sm focus:outline-none bg-transparent" />
                    <span class="text-xs text-gray-400 font-medium">
                        {{ filtered.length }} resultado{{ filtered.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="bg-[#10213E] px-6 py-3 flex justify-between items-center">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">
                            Historial de recepciones
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b">
                                <tr>
                                    <th class="px-5 py-3">#</th>
                                    <th class="px-5 py-3">Cliente</th>
                                    <th class="px-5 py-3">Vehículo</th>
                                    <th class="px-5 py-3">Placa</th>
                                    <th class="px-5 py-3">Síntomas</th>
                                    <th class="px-5 py-3">Fecha</th>
                                    <th class="px-5 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="rec in paginated" :key="rec.id"
                                    class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 font-mono text-gray-400 text-xs">{{ rec.id }}</td>
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-[#10213E]">
                                            {{ rec.client?.first_name }} {{ rec.client?.last_name }}
                                        </p>
                                        <p class="text-xs text-gray-400">{{ rec.client?.phone ?? '—' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700">
                                        {{ rec.vehicle?.brand?.name ?? 'S/M' }}
                                        {{ rec.vehicle?.vehicleModel?.name ?? '' }}
                                        <span class="text-gray-400 text-xs">({{ rec.vehicle?.year ?? '—' }})</span>
                                    </td>
                                    <td class="px-5 py-4 font-mono font-bold uppercase text-xs text-gray-600">
                                        {{ rec.vehicle?.plate ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 text-xs max-w-xs truncate">
                                        {{ rec.symptoms ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-gray-500 text-xs">
                                        {{ new Date(rec.created_at).toLocaleDateString('es-MX') }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-center gap-2">
                                            <Link :href="route('recepcion.show', rec.id)"
                                                class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                Ver
                                            </Link>
                                            <Link :href="route('recepcion.edit', rec.id)"
                                                class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                ✏️
                                            </Link>
                                            <a :href="route('recepcion.pdf', rec.id)" target="_blank"
                                                class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                🖨️
                                            </a>
                                            <button v-if="isAdmin" @click="askDelete(rec.id)"
                                                class="bg-red-50 hover:bg-red-100 text-[#EE2857] px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                ✕
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="paginated.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-300 italic text-sm">
                                        {{ search ? 'Sin resultados para "' + search + '"' : 'No hay recepciones registradas.' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="totalPages > 1"
                        class="px-6 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <span>Página {{ currentPage }} de {{ totalPages }}</span>
                        <div class="flex gap-1">
                            <button v-for="p in totalPages" :key="p" @click="currentPage = p"
                                class="w-8 h-8 rounded-lg border transition font-bold"
                                :class="p === currentPage
                                    ? 'bg-[#10213E] text-white border-[#10213E]'
                                    : 'bg-white border-gray-200 hover:bg-gray-50 text-gray-600'">
                                {{ p }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <ConfirmModal :show="confirmDel.show" title="Eliminar recepción"
            message="Se eliminará esta recepción permanentemente. Esta acción no se puede deshacer."
            confirm-text="Sí, eliminar" @confirm="doDel" @cancel="confirmDel.show = false" />
    </AuthenticatedLayout>
</template>
