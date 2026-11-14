<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    result: { type: Object, default: null },
});

const form = useForm({ file: null });

const submit = () => {
    form.post(route('clients.import.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('file'),
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-tight">Importar clientes</h2>
                <Link :href="route('clients.index')" class="text-sm font-bold text-gray-500 hover:text-[#10213E] min-h-[44px] inline-flex items-center">
                    ← Volver al directorio
                </Link>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 space-y-6">

                <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-black uppercase text-gray-500">1. Prepara tu archivo</h3>
                    <p class="text-sm text-gray-600">
                        Descarga la plantilla, pega tus clientes en las mismas columnas y guárdala como CSV.
                        En Excel: Archivo › Guardar como › CSV (delimitado por comas o punto y coma).
                    </p>
                    <p class="text-xs text-gray-500">
                        Columnas obligatorias: <strong>nombre, apellidos, telefono</strong>.
                        Opcionales: <strong>direccion, rfc, correo, pais</strong> (MX o US; por defecto MX).
                    </p>
                    <a :href="route('clients.import.template')"
                        class="inline-flex items-center min-h-[44px] px-5 py-2 rounded-xl bg-[#10213E] text-white text-sm font-bold">
                        Descargar plantilla CSV
                    </a>
                </section>

                <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-black uppercase text-gray-500">2. Sube el archivo</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <input type="file" accept=".csv,text/csv"
                            @input="form.file = $event.target.files[0]"
                            class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-gray-100 file:font-bold" />
                        <p v-if="form.errors.file" class="text-sm text-red-600">{{ form.errors.file }}</p>
                        <button type="submit" :disabled="!form.file || form.processing"
                            class="min-h-[44px] px-6 py-2 rounded-xl bg-[#EE2857] text-white font-black uppercase text-sm disabled:opacity-40">
                            {{ form.processing ? 'Importando…' : 'Importar clientes' }}
                        </button>
                    </form>
                </section>

                <section v-if="result" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3">
                    <h3 class="text-sm font-black uppercase text-gray-500">Resultado</h3>
                    <p class="text-sm"><strong class="text-green-700">{{ result.creados }}</strong> clientes creados.</p>
                    <div v-if="result.omitidos.length">
                        <p class="text-sm font-bold text-amber-700">Omitidos ({{ result.omitidos.length }}):</p>
                        <ul class="text-xs text-gray-600 list-disc ml-5">
                            <li v-for="(m, i) in result.omitidos" :key="'o' + i">{{ m }}</li>
                        </ul>
                    </div>
                    <div v-if="result.errores.length">
                        <p class="text-sm font-bold text-red-700">Con errores ({{ result.errores.length }}):</p>
                        <ul class="text-xs text-gray-600 list-disc ml-5">
                            <li v-for="(m, i) in result.errores" :key="'e' + i">{{ m }}</li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
