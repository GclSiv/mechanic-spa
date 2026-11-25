<script setup>
// Edición de Personal (solo admin/owner). Migrada al AppShell del Design System.
// La contraseña nunca se cambia desde aquí: solo se pide para crear la cuenta de un mecánico legacy sin ella.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import MechanicForm from '@/Components/Mechanics/MechanicForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    mechanic: { type: Object, required: true },
    genders: { type: Array, default: () => [] },
    mechanicTypes: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.mechanic.name ?? '',
    email: props.mechanic.email ?? '',
    role: props.mechanic.user?.role ?? 'mechanic',
    gender_id: props.mechanic.gender_id ?? '',
    mechanic_type_id: props.mechanic.mechanic_type_id ?? '',
    phone: props.mechanic.phone ?? '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put(route('mechanics.update', props.mechanic.id));
}
</script>

<template>
    <Head :title="t('mechanics_form.editarTitulo')" />

    <AppShellLayout>
        <PageHeader :title="t('mechanics_form.editarTitulo')" :description="mechanic.name">
            <Link :href="route('mechanics.index')" class="ds-focus inline-flex h-control items-center text-ds-control font-semibold text-navy hover:underline">{{ t('mechanics_form.volverPersonal') }}</Link>
        </PageHeader>

        <div class="mt-6 max-w-3xl">
            <MechanicForm
                :form="form"
                :genders="genders"
                :mechanic-types="mechanicTypes"
                modo="editar"
                :cuenta-vinculada="!!mechanic.user_id"
                :submit-label="t('mechanics_form.actualizar')"
                :submit-busy="t('mechanics_form.guardando')"
                @submit="submit"
            />
        </div>
    </AppShellLayout>
</template>
