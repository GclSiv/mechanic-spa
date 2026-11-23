<script setup>
// EmptyState del DS: icono opcional, título, descripción y acción solo cuando hay un siguiente paso lógico.
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-line-strong bg-surface-2 px-6 py-12 text-center">
        <slot name="icon" />
        <p class="text-ds-section-title text-navy">{{ title }}</p>
        <p v-if="description" class="max-w-md text-ds-body text-ink-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
