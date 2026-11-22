<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Identidad compartida por Inertia: logo_url y settings.company_name. Sin logo, muestra el nombre.
const page = usePage();
const logo = computed(() => page.props.logo_url ?? null);
const nombre = computed(() => page.props.settings?.company_name || 'Taller');
</script>

<template>
    <img v-if="logo" :src="logo" :alt="nombre" class="w-auto h-12 object-contain" />
    <span v-else class="text-xl font-black uppercase tracking-tight text-[#10213E]">{{ nombre }}</span>
</template>
