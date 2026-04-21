<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({ clients: Array });

const search = ref('');
const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    const list = usePage().props.clients ?? [];
    if (!q) return list;
    return list.filter(c =>
        (c.first_name + ' ' + c.last_name).toLowerCase().includes(q) ||
        c.phone?.toLowerCase().includes(q)
    );
});
</script>

<template>
    <AuthenticatedLayout title="Clientes">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">👥 Clientes</h2>
        </template>
        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="flex items-center gap-3 bg-white rounded-xl shadow-sm px-4 py-3 border border-gray-100">
                    <span class="text-gray-400">🔍</span>
                    <input v-model="search" type="text" placeholder="Buscar cliente..."
                        class="flex-1 text-sm focus:outline-none bg-transparent" />
                </div>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="bg-[#10213E] px-6 py-3">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">{{ filtered.length }} clientes</p>
                    </div>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b">
                            <tr>
                                <th class="px-5 py-3">#</th>
                                <th class="px-5 py-3">Nombre</th>
                                <th class="px-5 py-3">Teléfono</th>
                                <th class="px-5 py-3">RFC</th>
                                <th class="px-5 py-3">Dirección</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="c in filtered" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4 font-mono text-gray-400 text-xs">{{ c.id }}</td>
                                <td class="px-5 py-4 font-bold text-[#10213E]">{{ c.first_name }} {{ c.last_name }}</td>
                                <td class="px-5 py-4 text-gray-600">{{ c.phone ?? '—' }}</td>
                                <td class="px-5 py-4 text-gray-600 text-xs font-mono">{{ c.rfc ?? '—' }}</td>
                                <td class="px-5 py-4 text-gray-600 text-xs">{{ c.address ?? '—' }}</td>
                            </tr>
                            <tr v-if="filtered.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-300 italic">Sin clientes registrados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
