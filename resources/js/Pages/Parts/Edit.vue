<script setup>
// Edición de refacción (solo admin/owner; el backend aplica role:admin). La vista solo presenta y envía.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import PartForm from '@/Components/Parts/PartForm.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({ part: Object, categories: Array });

const form = useForm({
    category_id:         props.part.category_id ?? '',
    name:                props.part.name,
    sku:                 props.part.sku,
    barcode:             props.part.barcode ?? '',
    cost_price:          props.part.cost_price,
    sale_price:          props.part.sale_price,
    low_stock_threshold: props.part.low_stock_threshold,
});

function submit() { form.put(route('parts.update', props.part.id)); }
</script>

<template>
    <Head :title="t('refaccionesUi.formEditarTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('refaccionesUi.formEditarTitulo')" :description="part.name" />

        <div class="mt-6 min-w-0 max-w-3xl">
            <PartForm
                :form="form"
                :categories="categories"
                :submit-label="t('refaccionesUi.formActualizar')"
                :submitting-label="t('refaccionesUi.formGuardando')"
                :cancel-href="route('parts.index')"
                :stock-actual="part.stock"
                :kardex-href="route('parts.kardex', part.id)"
                @submit="submit"
            />
        </div>
    </AppShellLayout>
</template>
