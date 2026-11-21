<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolForm from '@/Components/Tools/ToolForm.vue';

defineProps({
    categories:  Array,
    locations:   Array,
    estados:     Array,
    condiciones: Array,
});
</script>

<template>
    <AuthenticatedLayout title="Nueva herramienta">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">🔧 Nueva herramienta</h2>
        </template>
        <div class="py-12 min-h-screen">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <ToolForm :categories="categories" :locations="locations" :estados="estados" :condiciones="condiciones" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
