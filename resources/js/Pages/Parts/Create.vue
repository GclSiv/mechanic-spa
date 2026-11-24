<script setup>
// Alta de refacción (solo admin/owner; el backend aplica role:admin). La vista solo presenta y envía.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import PartForm from '@/Components/Parts/PartForm.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({ categories: Array });

const form = useForm({
    category_id: '', name: '', sku: '', cost_price: '', sale_price: '', stock: 0, low_stock_threshold: 2,
});

function submit() { form.post(route('parts.store')); }
</script>

<template>
    <Head :title="t('refaccionesUi.formNuevaTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('refaccionesUi.formNuevaTitulo')" :description="t('refaccionesUi.descripcion')" />

        <div class="mt-6 min-w-0 max-w-3xl">
            <PartForm
                :form="form"
                :categories="categories"
                :submit-label="t('refaccionesUi.formGuardar')"
                :submitting-label="t('refaccionesUi.formGuardando')"
                :cancel-href="route('parts.index')"
                @submit="submit"
            />
        </div>
    </AppShellLayout>
</template>
