<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    modules: Array,
});

const form = useForm({
    modules: Object.fromEntries(props.modules.map((m) => [m.key, m.enabled])),
});

const save = () => form.put(route('owner.modules.update'), { preserveScroll: true });
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-tight">Módulos del sistema</h2>
                <p class="text-xs text-gray-500 mt-1">Solo visible para el dueño. Un módulo apagado desaparece del menú para todos.</p>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
                    <div v-for="m in modules" :key="m.key" class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="font-bold text-[#10213E]">{{ m.label }}</p>
                            <p class="text-xs text-gray-500">{{ m.description }}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer min-h-[44px] min-w-[44px] justify-center">
                            <input type="checkbox" v-model="form.modules[m.key]" class="sr-only peer" />
                            <span class="w-12 h-7 bg-gray-300 rounded-full peer-checked:bg-green-600 transition-colors"></span>
                            <span class="absolute left-1 top-1 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" @click="save" :disabled="form.processing"
                        class="min-h-[44px] px-6 py-2 rounded-xl bg-[#10213E] text-white font-black uppercase text-sm disabled:opacity-40">
                        Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
