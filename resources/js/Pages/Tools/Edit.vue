<script setup>
// Edición de herramienta (solo admin/owner). Editar no borra evidencia; solo puede agregar.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import ToolForm from '@/Components/Tools/ToolForm.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    tool:        Object,
    categories:  Array,
    locations:   Array,
    estados:     Array,
    condiciones: Array,
});
</script>

<template>
    <Head :title="t('herramientasUi.formEditarTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('herramientasUi.formEditarTitulo')" :description="tool.name" />

        <div class="mt-6 min-w-0 max-w-3xl">
            <ToolForm :tool="tool" :categories="categories" :locations="locations" :estados="estados" :condiciones="condiciones" />
        </div>
    </AppShellLayout>
</template>
