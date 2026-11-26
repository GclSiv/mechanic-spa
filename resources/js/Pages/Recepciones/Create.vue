<script setup>
// Recepción nueva (solo admin/owner). Una sola página por bloques: cliente, vehículo, ingreso, condición y fotos.
// La validación de negocio vive en StoreRecepcionRequest; aquí solo se avisan duplicados y se muestran errores de Laravel.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import ReceptionClientFields from '@/Components/Recepciones/ReceptionClientFields.vue';
import ReceptionVehicleFields from '@/Components/Recepciones/ReceptionVehicleFields.vue';
import ReceptionIntakeFields from '@/Components/Recepciones/ReceptionIntakeFields.vue';
import ReceptionConditionFields from '@/Components/Recepciones/ReceptionConditionFields.vue';
import DamageDiagram from '@/Components/DamageDiagram.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { diagramaParaTipo, zonasDe } from '@/Components/DamageDiagrams/diagramas.js';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    brands: Array,
    vehicleTypes: { type: Array, default: () => [] },
    vehicleSubtypes: { type: Array, default: () => [] },
    fuelTypes: { type: Array, default: () => [] },
    transmissions: { type: Array, default: () => [] },
    clients: Array,   // Clientes para elegir uno ya registrado
    preselect: Object, // { client_id, vehicle_id } desde "Nueva visita"
});

const page = usePage();
const { t } = useI18n();
const isNewClient = ref(true);

// ── Combobox de cliente existente ─────────────────────────────────────
const clientSearch = ref('');
const showClientList = ref(false);

const filteredClients = computed(() => {
    const q = clientSearch.value.toLowerCase().trim();
    if (!q || !props.clients) return props.clients?.slice(0, 30) ?? [];
    return props.clients.filter(c =>
        (c.first_name + ' ' + c.last_name).toLowerCase().includes(q) ||
        (c.phone ?? '').toLowerCase().includes(q)
    ).slice(0, 30);
});

function pickClient(client) {
    form.client_id = client.id;
    // Datos de contacto precargados; el usuario puede corregirlos
    form.first_name = client.first_name ?? '';
    form.last_name = client.last_name ?? '';
    form.phone = client.phone ?? '';
    form.address = client.address ?? '';
    form.rfc = client.rfc ?? '';
    form.razon_social = '';
    clientSearch.value = client.first_name + ' ' + client.last_name + ' — ' + (client.phone ?? '');
    showClientList.value = false;
}

function clearClient() {
    form.client_id = null;
    clientSearch.value = '';
}

// "Nueva visita": cliente y vehículo ya vienen cargados; solo faltan kilometraje y síntomas
onMounted(() => {
    const cid = props.preselect?.client_id;
    const vid = props.preselect?.vehicle_id;
    const client = cid ? props.clients?.find((c) => c.id == cid) : null;
    if (!client) return;

    isNewClient.value = false;
    pickClient(client);
    if (vid) {
        // El watch de client_id limpia vehicle_id; lo asignamos después de que corra
        nextTick(() => { form.vehicle_id = vid; });
    }
});

// ── Registro exitoso ──────────────────────────────────────────────────
const showSuccessModal = ref(false);

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.last_id || flash?.success) showSuccessModal.value = true;
    },
    { deep: true }
);

const closeModal = () => {
    showSuccessModal.value = false;
    router.visit(route('recepcion.index'));
};

const printPdf = (id) => {
    window.open(route('recepcion.pdf', id), '_blank');
    closeModal();
};

// ── Formulario ────────────────────────────────────────────────────────
const form = useForm({
    country: 'MX',
    client_id: null,   // Si tiene valor, el cliente ya existe
    vehicle_id: null,  // Si tiene valor, el vehículo ya existe

    // Cliente (solo si client_id es null)
    first_name: '',
    last_name: '',
    phone: '',
    address: '',
    rfc: '',
    razon_social: '',
    sin_factura: false,

    // Vehículo (solo si vehicle_id es null)
    brand_id: '',
    vehicle_model_id: '',
    vehicle_type_id: '',
    vehicle_subtype_id: '',
    fuel_type_id: '',
    transmission_id: '',
    year: '',
    plate: '',
    vin: '',
    engine: '',
    color: '',

    // Recepción
    miles: '',
    fuel_level: '1/2',
    witnesses: [],
    damages: [],
    inventory: [],
    photos: [],
    symptoms: '',
});

const photoPreviews = ref([]);

const handlePhotoUpload = (event) => {
    Array.from(event.target.files).forEach((file) => {
        form.photos.push(file);
        photoPreviews.value.push(URL.createObjectURL(file));
    });
    event.target.value = '';
};

const removePhoto = (index) => {
    form.photos.splice(index, 1);
    photoPreviews.value.splice(index, 1);
};

// Vehículos del cliente seleccionado
const availableVehicles = computed(() => {
    if (!props.clients || !form.client_id) return [];
    const selectedClient = props.clients.find((c) => c.id == form.client_id);
    return selectedClient ? selectedClient.vehicles : [];
});

// Cambiar de cliente limpia el vehículo elegido
watch(() => form.client_id, () => {
    form.vehicle_id = null;
});

// ── Duplicados de vehículo por placa / VIN ────────────────────────────
const duplicateAlert = ref(null);   // { type: 'plate'|'vin', vehicle: {...} }
const showDuplicateModal = ref(false);
let plateTimer = null;
let vinTimer = null;

async function checkField(field, value) {
    if (!value || value.length < 3) return;
    try {
        const params = new URLSearchParams({ [field]: value });
        const res = await fetch(route('vehicles.check') + '?' + params.toString());
        const data = await res.json();
        if (data[field]) {
            duplicateAlert.value = { type: field, vehicle: data[field] };
            showDuplicateModal.value = true;
        }
    } catch (_) {}
}

watch(() => form.plate, (val) => {
    if (form.vehicle_id) return;
    clearTimeout(plateTimer);
    plateTimer = setTimeout(() => checkField('plate', val?.toUpperCase()), 600);
});

watch(() => form.vin, (val) => {
    if (form.vehicle_id) return;
    clearTimeout(vinTimer);
    vinTimer = setTimeout(() => checkField('vin', val?.toUpperCase()), 600);
});

function useDuplicateVehicle() {
    form.vehicle_id = duplicateAlert.value.vehicle.id;
    showDuplicateModal.value = false;
    duplicateAlert.value = null;
}

// Placa o VIN ya registrados: no se captura de nuevo; se limpia el campo para corregir
function ignoreDuplicate() {
    if (duplicateAlert.value?.type === 'plate') form.plate = '';
    if (duplicateAlert.value?.type === 'vin') form.vin = '';
    showDuplicateModal.value = false;
    duplicateAlert.value = null;
}

// ── Cliente ya registrado (mismo nombre y apellido) ───────────────────
const clienteDuplicado = ref(null);
const showClienteDuplicadoModal = ref(false);
let nombreTimer = null;

async function checkCliente() {
    if (form.client_id) return;
    const first = (form.first_name || '').trim();
    const last = (form.last_name || '').trim();
    if (first.length < 2 || last.length < 2) return;
    try {
        const params = new URLSearchParams({ first_name: first, last_name: last });
        const res = await fetch(route('clients.check') + '?' + params.toString());
        const data = await res.json();
        if (data.client && !form.client_id) {
            clienteDuplicado.value = data.client;
            showClienteDuplicadoModal.value = true;
        }
    } catch (_) {}
}

watch([() => form.first_name, () => form.last_name], () => {
    if (form.client_id) return;
    clearTimeout(nombreTimer);
    nombreTimer = setTimeout(checkCliente, 600);
});

function usarClienteDuplicado() {
    pickClient(clienteDuplicado.value);
    showClienteDuplicadoModal.value = false;
    clienteDuplicado.value = null;
}

function corregirNombre() {
    form.first_name = '';
    form.last_name = '';
    showClienteDuplicadoModal.value = false;
    clienteDuplicado.value = null;
}

// ── Datos fiscales: RFC y razón social ────────────────────────────────
const RFC_GENERICOS = ['XAXX010101000', 'XEXX010101000'];
const fiscalDuplicado = ref(null);
const showFiscalModal = ref(false);
let rfcTimer = null;

function toggleSinFactura() {
    if (form.sin_factura) {
        form.rfc = 'XAXX010101000';
        form.razon_social = '';
    } else if (RFC_GENERICOS.includes((form.rfc || '').toUpperCase())) {
        form.rfc = '';
    }
}

async function checkRfc(val) {
    const rfc = (val || '').trim().toUpperCase();
    if (rfc.length < 12 || RFC_GENERICOS.includes(rfc)) return;
    try {
        const res = await fetch(route('clients.check') + '?' + new URLSearchParams({ rfc }).toString());
        const data = await res.json();
        if (data.fiscal) {
            // Perfil del mismo cliente ya seleccionado: no es duplicado
            if (form.client_id && Number(data.fiscal.client_id) === Number(form.client_id)) {
                if (!form.razon_social) form.razon_social = data.fiscal.razon_social ?? '';
                return;
            }
            fiscalDuplicado.value = data.fiscal;
            showFiscalModal.value = true;
        }
    } catch (_) {}
}

watch(() => form.rfc, (val) => {
    if (form.sin_factura) return;
    clearTimeout(rfcTimer);
    rfcTimer = setTimeout(() => checkRfc(val), 600);
});

// El perfil fiscal solo se puede reutilizar si pertenece al cliente seleccionado (o a un cliente nuevo)
const perfilEsDeOtro = () => form.client_id && Number(form.client_id) !== Number(fiscalDuplicado.value?.client_id);

function usarPerfilFiscal() {
    form.client_id = fiscalDuplicado.value.client_id;
    form.rfc = fiscalDuplicado.value.rfc;
    form.razon_social = fiscalDuplicado.value.razon_social ?? '';
    showFiscalModal.value = false;
    fiscalDuplicado.value = null;
}

function corregirRfc() {
    form.rfc = '';
    showFiscalModal.value = false;
    fiscalDuplicado.value = null;
}

// ── Diagrama de daños por tipo de unidad ──────────────────────────────
// Tipo efectivo: el del vehículo existente si se eligió uno; si no, el tipo capturado en el formulario.
const vehiculoPorId = (id) => (props.clients ?? []).flatMap((c) => c.vehicles ?? []).find((v) => v.id == id) ?? null;
const tipoIdEfectivo = computed(() => (form.vehicle_id ? vehiculoPorId(form.vehicle_id)?.vehicle_type_id ?? null : form.vehicle_type_id || null));
const diagramaActual = computed(() => diagramaParaTipo(props.vehicleTypes, tipoIdEfectivo.value));

// Cambiar el tipo: si hay daños que el nuevo diagrama no tiene, se pide confirmación antes de quitarlos.
const estadoTipo = ref({ vehicle_id: form.vehicle_id, vehicle_type_id: form.vehicle_type_id });
const cambioPendiente = ref(null); // { count, revertir }

watch(tipoIdEfectivo, (nuevo, anterior) => {
    const previo = estadoTipo.value;
    estadoTipo.value = { vehicle_id: form.vehicle_id, vehicle_type_id: form.vehicle_type_id };
    if (anterior === undefined || nuevo == anterior) return;

    const zonas = zonasDe(diagramaParaTipo(props.vehicleTypes, nuevo));
    const incompatibles = form.damages.filter((d) => !zonas.includes(d.zone));
    if (!incompatibles.length) return;

    const campo = previo.vehicle_id !== form.vehicle_id ? 'vehicle_id' : 'vehicle_type_id';
    const valorAnterior = previo[campo];
    cambioPendiente.value = {
        count: incompatibles.length,
        revertir: () => { form[campo] = valorAnterior; },
    };
});

function confirmarCambioDiagrama() {
    const zonas = zonasDe(diagramaActual.value);
    form.damages = form.damages.filter((d) => zonas.includes(d.zone));
    cambioPendiente.value = null;
}

function cancelarCambioDiagrama() {
    cambioPendiente.value?.revertir();
    cambioPendiente.value = null;
}

const submit = () => {
    // Con vehículo existente los campos de vehículo nuevo están ocultos: no se envían para no validar datos vacíos.
    form.transform((data) => (data.vehicle_id ? {
        ...data,
        brand_id: null,
        vehicle_model_id: null,
        vehicle_type_id: null,
        vehicle_subtype_id: null,
        fuel_type_id: null,
        transmission_id: null,
        year: null,
        plate: null,
        vin: null,
        engine: null,
        color: null,
    } : data)).post(route('recepcion.store'), {
        onSuccess: () => {
            form.reset();
            photoPreviews.value = [];
        },
    });
};
</script>

<template>
    <Head :title="t('recepcion_form.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('recepcion_form.titulo')" :description="t('recepcionUi.descripcion')">
            <Link
                :href="route('recepcion.index')"
                class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2"
            >{{ t('recepcionUi.volverRecepciones') }}</Link>
        </PageHeader>

        <form class="mt-6 min-w-0 space-y-6" novalidate @submit.prevent="submit">
            <div
                v-if="form.hasErrors"
                role="alert"
                class="rounded-xl border border-danger bg-danger-soft px-4 py-3 text-ds-control font-semibold text-danger-text"
            >{{ t('recepcionUi.revisaCampos') }}</div>

            <!-- ═══ CLIENTE ═══ -->
            <section aria-labelledby="sec-cliente" class="min-w-0 space-y-5 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <h2 id="sec-cliente" class="text-ds-section-title text-navy">{{ t('recepcionUi.secCliente') }}</h2>

                <div class="inline-flex rounded-lg bg-surface-2 p-1" role="group" :aria-label="t('recepcionUi.secCliente')">
                    <button
                        type="button"
                        :aria-pressed="!isNewClient"
                        class="ds-focus h-control-compact rounded-md px-4 text-ds-control font-semibold transition-colors"
                        :class="!isNewClient ? 'bg-surface text-navy shadow-sm' : 'text-ink-muted hover:text-ink'"
                        @click="isNewClient = false"
                    >{{ t('recepcion_form.clienteRegistrado') }}</button>
                    <button
                        type="button"
                        :aria-pressed="isNewClient"
                        class="ds-focus h-control-compact rounded-md px-4 text-ds-control font-semibold transition-colors"
                        :class="isNewClient ? 'bg-surface text-navy shadow-sm' : 'text-ink-muted hover:text-ink'"
                        @click="isNewClient = true; form.client_id = null;"
                    >{{ t('recepcion_form.clienteNuevo') }}</button>
                </div>

                <template v-if="!isNewClient">
                    <div class="relative">
                        <label for="client_search" class="mb-1 block text-ds-label font-semibold text-ink-secondary">{{ t('recepcion_form.buscarCliente') }}</label>
                        <input
                            id="client_search"
                            v-model="clientSearch"
                            type="text"
                            autocomplete="off"
                            :placeholder="t('recepcion_form.buscarPlaceholder')"
                            class="ds-focus h-control w-full rounded-lg border border-line-strong bg-surface px-3 text-ds-control text-ink placeholder:text-ink-subtle"
                            @focus="showClientList = true"
                            @input="showClientList = true; form.client_id = null;"
                        />
                        <button
                            v-if="form.client_id"
                            type="button"
                            class="ds-focus absolute right-2 top-[34px] rounded px-2 text-ink-muted hover:text-danger-text"
                            :aria-label="t('recepcionUi.cancelar')"
                            @click="clearClient"
                        >✕</button>

                        <ul
                            v-if="showClientList && filteredClients.length > 0"
                            class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-xl border border-line bg-surface shadow-lg"
                        >
                            <li v-for="client in filteredClients" :key="client.id">
                                <button
                                    type="button"
                                    class="ds-focus flex w-full flex-col items-start px-4 py-2.5 text-left hover:bg-surface-2"
                                    :class="form.client_id === client.id ? 'bg-surface-2 font-semibold' : ''"
                                    @mousedown.prevent="pickClient(client)"
                                >
                                    <span class="text-ds-control font-semibold text-navy">{{ client.first_name }} {{ client.last_name }}</span>
                                    <span class="text-ds-caption text-ink-muted">{{ client.phone ?? '—' }}</span>
                                </button>
                            </li>
                        </ul>
                        <p
                            v-if="showClientList && clientSearch.length > 1 && filteredClients.length === 0"
                            class="absolute left-0 right-0 z-30 mt-1 rounded-xl border border-line bg-surface px-4 py-3 text-ds-control italic text-ink-muted shadow-lg"
                        >{{ t('recepcion_form.sinResultados') }} "{{ clientSearch }}"</p>
                    </div>

                    <div v-if="form.client_id" class="space-y-4">
                        <p class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-ds-caption font-semibold text-navy">
                            {{ t('recepcion.clienteSeleccionado') }} — {{ t('recepcion_form.clienteEditarDatos') }}
                        </p>
                        <ReceptionClientFields :form="form" />
                    </div>
                    <p v-if="form.errors.client_id" class="text-ds-caption text-danger-text" role="alert">{{ form.errors.client_id }}</p>
                </template>

                <ReceptionClientFields v-else :form="form" fiscal @sin-factura="toggleSinFactura" />
            </section>

            <!-- ═══ VEHÍCULO ═══ -->
            <section aria-labelledby="sec-vehiculo" class="min-w-0 space-y-5 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <h2 id="sec-vehiculo" class="text-ds-section-title text-navy">{{ t('recepcionUi.secVehiculo') }}</h2>

                <div v-if="!isNewClient && availableVehicles.length > 0" class="space-y-2">
                    <label for="vehicle_id" class="block text-ds-label font-semibold text-ink-secondary">{{ t('recepcion_form.vehiculosRegistrados') }}</label>
                    <select id="vehicle_id" v-model="form.vehicle_id" class="ds-focus h-control w-full rounded-lg border border-line-strong bg-surface px-3 text-ds-control text-ink">
                        <option :value="null">{{ t('recepcion_form.vehiculoNuevo') }}</option>
                        <option v-for="vehicle in availableVehicles" :key="vehicle.id" :value="vehicle.id">
                            {{ vehicle.brand }} {{ vehicle.model }} ({{ vehicle.year }}) - {{ t('recepcion_form.placas') }}: {{ vehicle.plate }}
                        </option>
                    </select>
                </div>

                <ReceptionVehicleFields
                    v-if="!form.vehicle_id"
                    :form="form"
                    :brands="brands"
                    :vehicle-types="vehicleTypes"
                    :vehicle-subtypes="vehicleSubtypes"
                    :fuel-types="fuelTypes"
                    :transmissions="transmissions"
                    show-color
                />
                <p v-else class="text-ds-caption text-ink-muted">{{ t('recepcion_form.vehiculoNuevo') }}</p>
            </section>

            <!-- ═══ INGRESO Y SÍNTOMAS ═══ -->
            <section aria-label="Ingreso" class="min-w-0 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <ReceptionIntakeFields :form="form" />
            </section>

            <!-- ═══ CONDICIÓN Y ACCESORIOS ═══ -->
            <section aria-labelledby="sec-condicion" class="min-w-0 space-y-6 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <h2 id="sec-condicion" class="text-ds-section-title text-navy">{{ t('recepcion_form.condiciones') }}</h2>
                <ReceptionConditionFields :form="form" />

                <div>
                    <h3 class="text-ds-label font-semibold text-ink-secondary">{{ t('recepcionUi.secDanos') }}</h3>
                    <p class="text-ds-caption text-ink-muted">{{ t('recepcion_form.danosAyuda') }}</p>
                    <div class="mt-3 min-w-0 overflow-x-auto">
                        <DamageDiagram v-model="form.damages" :diagram="diagramaActual" />
                        <ConfirmModal
                            :show="!!cambioPendiente"
                            :title="t('diagramasUi.cambioTitulo')"
                            :message="t('diagramasUi.cambioMensaje', { n: cambioPendiente?.count ?? 0 })"
                            :confirm-text="t('diagramasUi.cambioConfirmar')"
                            :cancel-text="t('diagramasUi.cambioCancelar')"
                            :danger="true"
                            @confirm="confirmarCambioDiagrama"
                            @cancel="cancelarCambioDiagrama"
                        />
                    </div>
                </div>
            </section>

            <!-- ═══ FOTOS ═══ -->
            <section aria-labelledby="sec-fotos" class="min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5 sm:p-6">
                <h2 id="sec-fotos" class="text-ds-section-title text-navy">{{ t('recepcion_form.evidencia') }}</h2>
                <label class="ds-focus flex min-h-[7rem] cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-line-strong bg-surface-2 p-4 text-center hover:border-navy">
                    <span class="text-ds-control font-semibold text-navy">{{ t('recepcion_form.subirFotos') }}</span>
                    <span class="text-ds-caption text-ink-muted">{{ t('recepcion_form.arrastraFotos') }} · {{ t('recepcion_form.formatoFotos') }}</span>
                    <input type="file" class="sr-only" multiple accept="image/*" @change="handlePhotoUpload" />
                </label>

                <ul v-if="photoPreviews.length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <li v-for="(preview, index) in photoPreviews" :key="index" class="group relative overflow-hidden rounded-lg border border-line">
                        <img :src="preview" :alt="t('recepcionUi.fotoAlt')" class="h-24 w-full object-cover" />
                        <button
                            type="button"
                            class="ds-focus absolute right-1 top-1 rounded-full bg-danger px-2 py-0.5 text-ds-caption font-semibold text-white opacity-90 hover:opacity-100"
                            @click="removePhoto(index)"
                        >{{ t('recepcionUi.eliminar') }}</button>
                    </li>
                </ul>
            </section>

            <!-- ═══ ACCIONES ═══ -->
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    :href="route('recepcion.index')"
                    class="ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-5 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
                >{{ t('recepcionUi.cancelar') }}</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="ds-focus h-control rounded-lg bg-navy px-6 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50"
                >{{ form.processing ? t('recepcion_form.guardando') : t('recepcionUi.registrarRecepcion') }}</button>
            </div>
        </form>

        <!-- MODAL: RFC YA REGISTRADO -->
        <div v-if="showFiscalModal" class="fixed inset-0 z-50 flex items-center justify-center bg-navy/40 p-4">
            <div role="dialog" aria-modal="true" :aria-labelledby="'fiscal-title'" class="w-full max-w-sm rounded-xl border border-line bg-surface p-6 shadow-xl">
                <h2 id="fiscal-title" class="text-ds-section-title text-navy">{{ t('recepcionUi.rfcYaRegistrado') }}</h2>
                <p class="mt-1 text-ds-caption text-ink-muted">{{ t('recepcionUi.rfcYaRegistradoDesc') }}</p>

                <div class="mt-4 rounded-lg border border-line bg-surface-2 p-4">
                    <p class="text-ds-control font-semibold text-navy">{{ fiscalDuplicado?.razon_social }}</p>
                    <p class="text-ds-caption text-ink-secondary">RFC: <strong>{{ fiscalDuplicado?.rfc }}</strong></p>
                    <p class="text-ds-caption text-ink-secondary">{{ t('recepcionUi.secCliente') }}: <strong>{{ fiscalDuplicado?.client_name }}</strong></p>
                </div>

                <p v-if="perfilEsDeOtro()" class="mt-4 text-ds-caption text-danger-text">{{ t('recepcionUi.rfcDeOtro') }}</p>

                <div class="mt-6 flex flex-col gap-2">
                    <button
                        v-if="!perfilEsDeOtro()"
                        type="button"
                        class="ds-focus h-control rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
                        @click="usarPerfilFiscal"
                    >{{ t('recepcionUi.usarPerfilFiscal') }}</button>
                    <button
                        type="button"
                        class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
                        @click="corregirRfc"
                    >{{ t('recepcionUi.corregirRfc') }}</button>
                </div>
            </div>
        </div>

        <!-- MODAL: CLIENTE YA REGISTRADO -->
        <div v-if="showClienteDuplicadoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-navy/40 p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="cliente-title" class="w-full max-w-sm rounded-xl border border-line bg-surface p-6 shadow-xl">
                <h2 id="cliente-title" class="text-ds-section-title text-navy">{{ t('recepcion_form.clienteDuplicado') }}</h2>
                <p class="mt-1 text-ds-caption text-ink-muted">{{ t('recepcion_form.clienteDuplicadoDesc') }}</p>

                <div class="mt-4 rounded-lg border border-line bg-surface-2 p-4">
                    <p class="text-ds-caption font-semibold uppercase tracking-wide text-ink-muted">{{ t('recepcion_form.clienteEncontrado') }}</p>
                    <p class="text-ds-control font-semibold text-navy">{{ clienteDuplicado?.first_name }} {{ clienteDuplicado?.last_name }}</p>
                    <p class="text-ds-caption text-ink-secondary">{{ t('recepcion_form.telefonoLabel') }} <strong>{{ clienteDuplicado?.phone ?? '—' }}</strong></p>
                    <p class="text-ds-caption text-ink-secondary">{{ t('recepcion_form.noClienteLabel') }} <strong>{{ clienteDuplicado?.client_number ?? '—' }}</strong></p>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <button type="button" class="ds-focus h-control rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90" @click="usarClienteDuplicado">{{ t('recepcion_form.usarClienteExistente') }}</button>
                    <button type="button" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2" @click="corregirNombre">{{ t('recepcion_form.corregirNombre') }}</button>
                </div>
            </div>
        </div>

        <!-- MODAL: VEHÍCULO DUPLICADO -->
        <div v-if="showDuplicateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-navy/40 p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="vehiculo-title" class="w-full max-w-sm rounded-xl border border-line bg-surface p-6 shadow-xl">
                <h2 id="vehiculo-title" class="text-ds-section-title text-navy">{{ t('recepcion_form.vehiculoDuplicado') }}</h2>
                <p class="mt-1 text-ds-caption text-ink-muted">
                    {{ t('recepcion_form.duplicadoDesc') }}
                    <strong>{{ duplicateAlert?.type === 'plate' ? t('recepcion_form.laPlaca') : t('recepcion_form.elVin') }}</strong>
                </p>

                <div class="mt-4 rounded-lg border border-line bg-surface-2 p-4">
                    <p class="text-ds-caption font-semibold uppercase tracking-wide text-ink-muted">{{ t('recepcion_form.vehiculoEncontrado') }}</p>
                    <p class="text-ds-control font-semibold text-navy">{{ duplicateAlert?.vehicle.brand }} {{ duplicateAlert?.vehicle.model }} ({{ duplicateAlert?.vehicle.year }})</p>
                    <p class="text-ds-caption text-ink-secondary">{{ t('recepcion_form.placaLabel') }} <strong>{{ duplicateAlert?.vehicle.plate ?? '—' }}</strong></p>
                    <p class="text-ds-caption text-ink-secondary">{{ t('recepcion_form.vinLabel') }} <strong>{{ duplicateAlert?.vehicle.vin ?? '—' }}</strong></p>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <button type="button" class="ds-focus h-control rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90" @click="useDuplicateVehicle">{{ t('recepcion_form.usarExistente') }}</button>
                    <button type="button" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2" @click="ignoreDuplicate">{{ t('recepcion_form.corregirDatos') }}</button>
                </div>
            </div>
        </div>

        <!-- MODAL: REGISTRO EXITOSO -->
        <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-navy/40 p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="exito-title" class="w-full max-w-sm rounded-xl border border-line bg-surface p-6 text-center shadow-xl">
                <h2 id="exito-title" class="text-ds-section-title text-navy">{{ t('recepcion_form.ingresoExitoso') }}</h2>
                <p class="mt-2 text-ds-caption text-ink-muted">{{ t('recepcion_form.folioRegistrado') }}</p>
                <p class="mb-6 font-mono text-ds-page-title text-navy">#{{ page.props.flash?.last_id }}</p>
                <div class="flex flex-col gap-2">
                    <button type="button" class="ds-focus h-control rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90" @click="printPdf(page.props.flash?.last_id)">{{ t('recepcion_form.imprimirNota') }}</button>
                    <button type="button" class="ds-focus h-control rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2" @click="closeModal">{{ t('recepcionUi.volverRecepciones') }}</button>
                </div>
            </div>
        </div>
    </AppShellLayout>
</template>
