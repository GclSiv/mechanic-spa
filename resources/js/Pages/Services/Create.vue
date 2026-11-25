<script setup>
// Alta de servicio con comisión fija (solo admin/owner). AppShell del Design System.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import ServiceForm from '@/Components/Services/ServiceForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    commission_amount: '',
    is_active: true,
});

function submit() {
    form.post(route('services.store'));
}
</script>

<template>
    <Head :title="t('serviciosUi.nuevoTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('serviciosUi.nuevoTitulo')">
            <Link :href="route('services.index')" class="ds-focus inline-flex h-control items-center text-ds-control font-semibold text-navy hover:underline">{{ t('serviciosUi.volver') }}</Link>
        </PageHeader>

        <div class="mt-6 max-w-3xl">
            <ServiceForm
                :form="form"
                :submit-label="t('serviciosUi.guardar')"
                :submit-busy="t('serviciosUi.guardando')"
                @submit="submit"
            />
        </div>
    </AppShellLayout>
</template>
