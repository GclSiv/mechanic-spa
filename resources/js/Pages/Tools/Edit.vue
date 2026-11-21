<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolForm from '@/Components/Tools/ToolForm.vue';

defineProps({
    tool:        Object,
    categories:  Array,
    locations:   Array,
    estados:     Array,
    condiciones: Array,
});
</script>

<template>
    <AuthenticatedLayout title="Editar herramienta">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">✏️ Editar herramienta</h2>
        </template>
        <div class="py-12 min-h-screen">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <ToolForm :tool="tool" :categories="categories" :locations="locations" :estados="estados" :condiciones="condiciones" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
