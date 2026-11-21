<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { ETIQUETAS_ESTADO, CLASES_ESTADO } from '@/Components/Tools/etiquetas.js';

const props = defineProps({
    tools:      Object,
    categories: Array,
    locations:  Array,
    estados:    Array,
    filters:    Object,
});

const search     = ref(props.filters?.search ?? '');
const status     = ref(props.filters?.status ?? '');
const categoryId = ref(props.filters?.category_id ?? '');
const locationId = ref(props.filters?.location_id ?? '');
const mostrarBajas = ref(!!props.filters?.mostrar_bajas);

let temporizador = null;
function aplicarFiltros() {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => {
        router.get(route('tools.index'), {
            search:       search.value || undefined,
            status:       status.value || undefined,
            category_id:  categoryId.value || undefined,
            location_id:  locationId.value || undefined,
            mostrar_bajas: mostrarBajas.value ? 1 : undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}
watch([search, status, categoryId, locationId, mostrarBajas], aplicarFiltros);

const confirmBaja = ref({ show: false, id: null });
function pedirBaja(id) { confirmBaja.value = { show: true, id }; }
function darDeBaja() {
    router.delete(route('tools.destroy', confirmBaja.value.id), { preserveScroll: true });
    confirmBaja.value = { show: false, id: null };
}
</script>

<template>
    <AuthenticatedLayout title="Herramientas">
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider shrink-0">🔧 Herramientas</h2>
                <div class="flex items-center gap-3">
                    <a :href="route('parts.index')" class="text-sm text-gray-500 hover:text-[#10213E] font-bold border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50">📦 Refacciones</a>
                    <a :href="route('tools.create')" class="bg-[#10213E] hover:bg-blue-900 text-white font-bold py-2 px-4 rounded-lg shadow-md text-sm uppercase">+ Nueva</a>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
                    <input v-model="search" type="text" placeholder="Buscar nombre, marca, serie o código…"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm md:col-span-2 focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                    <select v-model="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Todos los estados</option>
                        <option v-for="e in estados" :key="e" :value="e">{{ ETIQUETAS_ESTADO[e] ?? e }}</option>
                    </select>
                    <select v-model="categoryId" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Todas las categorías</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="locationId" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Todas las ubicaciones</option>
                        <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                    <label class="md:col-span-5 flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="mostrarBajas" type="checkbox" class="rounded border-gray-300 text-[#10213E] focus:ring-[#10213E]" />
                        Mostrar herramientas dadas de baja
                    </label>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div v-if="tools.data.length === 0" class="p-10 text-center text-gray-400 text-sm">
                        No hay herramientas que coincidan con los filtros.
                    </div>
                    <table v-else class="min-w-full text-sm">
                        <thead class="bg-[#10213E] text-white text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Código</th>
                                <th class="px-4 py-3 text-left">Herramienta</th>
                                <th class="px-4 py-3 text-left">Categoría</th>
                                <th class="px-4 py-3 text-left">Ubicación</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in tools.data" :key="t.id" :class="{ 'opacity-60': !t.active }">
                                <td class="px-4 py-3 font-mono text-xs">{{ t.internal_code }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ t.name }}</div>
                                    <div class="text-xs text-gray-500">{{ [t.brand, t.model].filter(Boolean).join(' ') || '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ t.category?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ t.location?.name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['text-xs font-bold px-2.5 py-1 rounded-full', CLASES_ESTADO[t.status]]">
                                        {{ ETIQUETAS_ESTADO[t.status] ?? t.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                                    <a :href="route('tools.show', t.id)" class="text-[#10213E] font-bold text-xs hover:underline">Ver</a>
                                    <a :href="route('tools.edit', t.id)" class="text-[#10213E] font-bold text-xs hover:underline">Editar</a>
                                    <button v-if="t.active" @click="pedirBaja(t.id)" class="text-red-600 font-bold text-xs hover:underline">Dar de baja</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav v-if="tools.links.length > 3" class="flex flex-wrap gap-2 justify-center">
                    <a v-for="link in tools.links" :key="link.label" :href="link.url ?? undefined" v-html="link.label"
                        :class="['px-3 py-1.5 rounded-lg text-sm border', link.active ? 'bg-[#10213E] text-white border-[#10213E]' : 'bg-white text-gray-600 border-gray-200', !link.url ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50']" />
                </nav>
            </div>
        </div>

        <ConfirmModal
            :show="confirmBaja.show"
            title="Dar de baja"
            message="La herramienta se conservará en el historial con estado Baja."
            confirmText="Sí, dar de baja"
            @confirm="darDeBaja"
            @cancel="confirmBaja = { show: false, id: null }"
        />
    </AuthenticatedLayout>
</template>
