<script setup>
// Encabezado de página del Design System: título, descripción opcional y acciones a la derecha.
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-ds-page-title text-navy">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-ds-body text-ink-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </div>
</template>
