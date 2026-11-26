<script setup>
// Editar recepción: corrige los datos de ingreso. No cambia la orden de reparación, su estado,
// ni los daños o fotos (esta vista no los envía). Los datos llegan pre-llenados desde el servidor.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import ReceptionClientFields from '@/Components/Recepciones/ReceptionClientFields.vue';
import ReceptionVehicleFields from '@/Components/Recepciones/ReceptionVehicleFields.vue';
import ReceptionIntakeFields from '@/Components/Recepciones/ReceptionIntakeFields.vue';
import ReceptionConditionFields from '@/Components/Recepciones/ReceptionConditionFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    brands: Array,
    vehicleTypes: { type: Array, default: () => [] },
    vehicleSubtypes: { type: Array, default: () => [] },
    fuelTypes: { type: Array, default: () => [] },
    transmissions: { type: Array, default: () => [] },
    recepcion: Object,
    legacyVehicle: Object, // { brand, model } en texto, si el vehículo no tiene FK
});
const { t } = useI18n();

const client = props.recepcion.client || {};
const vehicle = props.recepcion.vehicle || {};

const form = useForm({
    client_id: client.id,
    vehicle_id: vehicle.id,

    // Cliente
    first_name: client.first_name || '',
    last_name: client.last_name || '',
    country: client.country || 'MX',
    phone: client.phone || '',
    address: client.address || '',
    rfc: client.rfc || '',

    // Vehículo
    brand_id: vehicle.brand_id || '',
    vehicle_model_id: vehicle.model_id || '',
    vehicle_type_id: vehicle.vehicle_type_id || '',
    vehicle_subtype_id: vehicle.vehicle_subtype_id || '',
    fuel_type_id: vehicle.fuel_type_id || '',
    transmission_id: vehicle.transmission_id || '',
    year: vehicle.year ? String(vehicle.year) : '',
    plate: vehicle.plate || '',
    vin: vehicle.vin || '',
    engine: vehicle.engine || '',

    // Recepción
    miles: props.recepcion.miles || '',
    fuel_level: props.recepcion.fuel_level || '1/4',
    symptoms: props.recepcion.symptoms || '',
    witnesses: props.recepcion.witnesses || [],
    inventory: props.recepcion.inventory || [],
});

const submit = () => {
    form.put(route('recepcion.update', props.recepcion.id), {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('recepcionUi.editarTitulo')" />

    <AppShellLayout>
        <PageHeader :title="`${t('recepcionUi.editarTitulo')} #${recepcion.id}`" :description="t('recepcionUi.editarAyuda')">
            <Link
                :href="route('recepcion.show', recepcion.id)"
                class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2"
            >{{ t('recepcionUi.volverDetalle') }}</Link>
        </PageHeader>

        <form class="mt-6 min-w-0 space-y-6" novalidate @submit.prevent="submit">
            <div
                v-if="form.hasErrors"
                role="alert"
                class="rounded-xl border border-danger bg-danger-soft px-4 py-3 text-ds-control font-semibold text-danger-text"
            >{{ t('recepcionUi.revisaCampos') }}</div>

            <section aria-labelledby="sec-cliente" class="min-w-0 space-y-5 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <h2 id="sec-cliente" class="text-ds-section-title text-navy">{{ t('recepcionUi.secCliente') }}</h2>
                <ReceptionClientFields :form="form" />
            </section>

            <section aria-labelledby="sec-vehiculo" class="min-w-0 space-y-5 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <h2 id="sec-vehiculo" class="text-ds-section-title text-navy">{{ t('recepcionUi.secVehiculo') }}</h2>
                <ReceptionVehicleFields
                    :form="form"
                    :brands="brands"
                    :legacy="legacyVehicle"
                    :vehicle-types="vehicleTypes"
                    :vehicle-subtypes="vehicleSubtypes"
                    :fuel-types="fuelTypes"
                    :transmissions="transmissions"
                />
            </section>

            <section aria-label="Ingreso" class="min-w-0 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <ReceptionIntakeFields :form="form" />
            </section>

            <section aria-labelledby="sec-condicion" class="min-w-0 space-y-6 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <h2 id="sec-condicion" class="text-ds-section-title text-navy">{{ t('recepcion_form.condiciones') }}</h2>
                <ReceptionConditionFields :form="form" />
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    :href="route('recepcion.show', recepcion.id)"
                    class="ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-5 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
                >{{ t('recepcionUi.cancelar') }}</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="ds-focus h-control rounded-lg bg-navy px-6 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50"
                >{{ form.processing ? t('recepcion_form.guardando') : t('recepcionUi.guardarCambios') }}</button>
            </div>
        </form>
    </AppShellLayout>
</template>
