<script setup>
// Edición de servicio (solo admin/owner). Cambiar la comisión NO altera conceptos anteriores.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import ServiceForm from '@/Components/Services/ServiceForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    service: { type: Object, required: true },
});

const form = useForm({
    name: props.service.name,
    commission_amount: props.service.commission_amount,
    is_active: props.service.is_active,
});

function submit() {
    form.put(route('services.update', props.service.id));
}
</script>

<template>
    <Head :title="t('serviciosUi.editarTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('serviciosUi.editarTitulo')" :description="service.name">
            <Link :href="route('services.index')" class="ds-focus inline-flex h-control items-center text-ds-control font-semibold text-navy hover:underline">{{ t('serviciosUi.volver') }}</Link>
        </PageHeader>

        <div class="mt-6 max-w-3xl">
            <ServiceForm
                :form="form"
                :en-uso="service.en_uso"
                :submit-label="t('serviciosUi.actualizar')"
                :submit-busy="t('serviciosUi.guardando')"
                @submit="submit"
            />
        </div>
    </AppShellLayout>
</template>
