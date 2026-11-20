<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    ordenes: Object,
    filtros: Object,
    estados: Array,
    mecanicos: Array,
});

const f = ref({ ...props.filtros });
let timer = null;

const aplicar = () => {
    const params = {};
    Object.entries(f.value).forEach(([k, v]) => { if (v !== '' && v !== null) params[k] = v; });
    router.get(route('repair-orders.lista'), params, { preserveState: true, preserveScroll: true, replace: true });
};

watch(() => [f.value.estado, f.value.mecanico, f.value.saldo, f.value.desde, f.value.hasta], aplicar);
watch(() => f.value.buscar, () => {
    clearTimeout(timer);
    timer = setTimeout(aplicar, 400);
});

const limpiar = () => {
    f.value = { buscar: '', estado: '', mecanico: '', saldo: '', desde: '', hasta: '' };
    aplicar();
};

const dinero = (n) => '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Lista de órdenes" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-[#10213E]">Lista de órdenes</h2>
        </template>

        <div class="py-6 px-4 max-w-7xl mx-auto space-y-5">
            <!-- Filtros -->
            <div class="bg-white rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <input v-model="f.buscar" type="text" placeholder="Folio, placa, VIN, cliente o teléfono"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm md:col-span-2 lg:col-span-2 focus:ring-2 focus:ring-[#EE2857] focus:outline-none" />
                <select v-model="f.estado" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Todos los estados</option>
                    <option v-for="e in estados" :key="e.slug" :value="e.slug">{{ e.name }}</option>
                </select>
                <select v-model="f.mecanico" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Todos los mecánicos</option>
                    <option v-for="m in mecanicos" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <select v-model="f.saldo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Cualquier saldo</option>
                    <option value="pendiente">Con saldo pendiente</option>
                    <option value="liquidado">Liquidadas</option>
                </select>
                <div class="flex gap-2">
                    <input v-model="f.desde" type="date" class="border border-gray-300 rounded-lg px-2 py-2 text-sm w-full" title="Desde" />
                    <input v-model="f.hasta" type="date" class="border border-gray-300 rounded-lg px-2 py-2 text-sm w-full" title="Hasta" />
                </div>
                <div class="md:col-span-3 lg:col-span-6 flex justify-end">
                    <button @click="limpiar" class="text-xs font-bold text-gray-500 hover:text-[#10213E] uppercase tracking-wide">Limpiar filtros</button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Folio</th>
                            <th class="px-4 py-3 text-left">Placa</th>
                            <th class="px-4 py-3 text-left">Vehículo</th>
                            <th class="px-4 py-3 text-left">Cliente</th>
                            <th class="px-4 py-3 text-left">Mecánico</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Entrada</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="o in ordenes.data" :key="o.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-bold text-[#10213E] whitespace-nowrap">{{ o.folio }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ o.placa ?? '—' }}</td>
                            <td class="px-4 py-3">{{ o.vehiculo }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[#10213E]">{{ o.cliente }}</p>
                                <p class="text-xs text-gray-400">{{ o.telefono ?? 'Sin teléfono' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ o.mecanico }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 rounded-full text-xs font-bold uppercase" :class="o.color">{{ o.estado }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ o.entrada }}
                                <span class="block text-xs text-gray-400">hace {{ o.dias }} días</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                {{ dinero(o.total) }}
                                <span v-if="o.pendiente > 0" class="block text-xs text-amber-700">
                                    + {{ dinero(o.pendiente) }} sin autorizar
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-black"
                                :class="o.saldo > 0 ? 'text-[#EE2857]' : 'text-emerald-700'">
                                {{ o.saldo > 0 ? dinero(o.saldo) : 'Liquidada' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('repair-orders.show', o.id)" class="text-xs font-bold text-[#EE2857] hover:underline">Abrir</Link>
                            </td>
                        </tr>
                        <tr v-if="ordenes.data.length === 0">
                            <td colspan="10" class="px-4 py-10 text-center text-gray-400 italic">No hay órdenes con estos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="flex items-center justify-between text-sm text-gray-500">
                <button :disabled="!ordenes.prev_page_url" @click="router.get(ordenes.prev_page_url, {}, { preserveState: true })"
                        class="px-3 py-2 rounded-lg bg-white shadow-sm disabled:opacity-40">← Anterior</button>
                <span>Página {{ ordenes.current_page }} de {{ ordenes.last_page }} · {{ ordenes.total }} órdenes</span>
                <button :disabled="!ordenes.next_page_url" @click="router.get(ordenes.next_page_url, {}, { preserveState: true })"
                        class="px-3 py-2 rounded-lg bg-white shadow-sm disabled:opacity-40">Siguiente →</button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
