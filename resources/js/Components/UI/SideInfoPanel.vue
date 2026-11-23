<script setup>
// Panel lateral de información complementaria. Solo lectura: no contiene acciones.
defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <aside class="rounded-xl border border-line bg-surface p-4" :aria-label="title">
        <h2 class="mb-3 text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ title }}</h2>
        <div class="space-y-3 text-ds-body text-ink">
            <slot />
        </div>
    </aside>
</template>
