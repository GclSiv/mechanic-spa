<script setup>
// Alta de Personal (solo admin/owner). Migrada al AppShell del Design System.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import MechanicForm from '@/Components/Mechanics/MechanicForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    genders: { type: Array, default: () => [] },
    mechanicTypes: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    email: '',
    role: 'mechanic',
    gender_id: '',
    mechanic_type_id: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('mechanics.store'), {
        // La contraseña no se conserva en el formulario tras un alta correcta.
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="t('mechanics_form.nuevoTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('mechanics_form.nuevoTitulo')">
            <Link :href="route('mechanics.index')" class="ds-focus inline-flex h-control items-center text-ds-control font-semibold text-navy hover:underline">{{ t('mechanics_form.volverPersonal') }}</Link>
        </PageHeader>

        <div class="mt-6 max-w-3xl">
            <MechanicForm
                :form="form"
                :genders="genders"
                :mechanic-types="mechanicTypes"
                modo="crear"
                :submit-label="t('mechanics_form.guardar')"
                :submit-busy="t('mechanics_form.guardando')"
                @submit="submit"
            />
        </div>
    </AppShellLayout>
</template>
