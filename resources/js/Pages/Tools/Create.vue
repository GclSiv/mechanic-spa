<script setup>
// Alta de herramienta (solo admin/owner). La evidencia es opcional y la valida el backend.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import ToolForm from '@/Components/Tools/ToolForm.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    categories:  Array,
    locations:   Array,
    estados:     Array,
    condiciones: Array,
});
</script>

<template>
    <Head :title="t('herramientasUi.formNuevaTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('herramientasUi.formNuevaTitulo')" :description="t('herramientasUi.descripcion')" />

        <div class="mt-6 min-w-0 max-w-3xl">
            <ToolForm :categories="categories" :locations="locations" :estados="estados" :condiciones="condiciones" />
        </div>
    </AppShellLayout>
</template>
