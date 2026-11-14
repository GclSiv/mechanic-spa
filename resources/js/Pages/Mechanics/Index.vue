<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    mechanics: Array,
});

const flash = computed(() => usePage().props.flash);

const confirmDel = ref({ show: false, id: null });
function destroy(id) { confirmDel.value = { show: true, id }; }
function doDel() {
    router.delete(route('mechanics.destroy', confirmDel.value.id), { preserveScroll: true });
    confirmDel.value = { show: false, id: null };
}
</script>

<template>
    <AuthenticatedLayout :title="$t('personal.titulo')">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                    🔧 {{ $t('personal.titulo') }}
                </h2>
                <a :href="route('mechanics.create')"
                    class="bg-[#10213E] hover:bg-blue-900 text-white font-bold py-2.5 px-5 rounded-lg shadow-md transition-all text-sm uppercase tracking-wider">
                    {{ $t('personal.nuevo') }}
                </a>
            </div>
        
        <ConfirmModal :show="confirmDel.show" :title="$t('personal.eliminarTitulo')"
            :message="$t('personal.eliminarMsg')"
            :confirm-text="$t('acciones.eliminar')" @confirm="doDel" @cancel="confirmDel.show = false" />
</template>

        <div class="py-12 min-h-screen transition-colors">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

                <!-- Flash -->
                <div v-if="flash?.success"
                    class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm font-medium">
                    ✅ {{ flash.success }}
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] px-6 py-4">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">
                            {{ $t('personal.total', { n: mechanics.length }) }}
                        </p>
                    </div>

                    <div class="overflow-x-auto"><table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider font-bold border-b">
                            <tr>
                                <th class="px-6 py-3">#</th>
                                <th class="px-6 py-3">{{ $t('personal.nombre') }}</th>
                                <th class="px-6 py-3">{{ $t('personal.email') }}</th>
                                <th class="px-6 py-3">{{ $t('personal.especialidad') }}</th>
                                <th class="px-6 py-3">{{ $t('personal.genero') }}</th>
                                <th class="px-6 py-3">{{ $t('personal.telefono') }}</th>
                                <th class="px-6 py-3 text-center">{{ $t('personal.acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in mechanics" :key="m.id"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-gray-400 font-mono">{{ m.id }}</td>
                                <td class="px-6 py-4 font-bold text-[#10213E]">{{ m.name }}</td>
                                <td class="px-6 py-4 text-gray-600 text-xs">
                                    <span v-if="m.email">{{ m.email }}</span>
                                    <span v-else class="text-amber-500 font-medium">{{ $t('personal.sinCuenta') }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold">
                                        {{ m.mechanic_type?.name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ m.gender?.name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ m.phone ?? '—' }}</td>
                                <td class="px-6 py-4 text-center flex justify-center gap-2">
                                    <a :href="route('mechanics.edit', m.id)"
                                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                        ✏️ Editar
                                    </a>
                                    <button @click="destroy(m.id)"
                                        class="bg-red-50 hover:bg-red-100 text-[#EE2857] font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                        ✕ Eliminar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="mechanics.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">
                                    {{ $t('personal.sinRegistros') }}
                                </td>
                            </tr>
                        </tbody>
                    </table></div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
