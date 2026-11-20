<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import { ref, watch, computed, onMounted, nextTick } from "vue";
import { useI18n } from "vue-i18n";
import IconEngine from "@/Components/Icons/IconEngine.vue";
import DamageDiagram from '@/Components/DamageDiagram.vue';
import IconAbs from "@/Components/Icons/IconAbs.vue";
import IconOil from "@/Components/Icons/IconOil.vue";
import IconBattery from "@/Components/Icons/IconBattery.vue";
import IconTemp from "@/Components/Icons/IconTemp.vue";

const props = defineProps({
    brands: Array,
    clients: Array, // NUEVO: Necesario para seleccionar clientes que ya existen
    preselect: Object, // { client_id, vehicle_id } desde "Nueva visita"
});

const page = usePage();
const currentStep = ref(1);
const totalSteps = 2;
const isNewClient = ref(true);
function getLang() { return localStorage.getItem('jk_locale') ?? 'es'; }
const { t } = useI18n();

// ── Combobox de cliente existente ─────────────────────────────────────
const clientSearch   = ref('');
const showClientList = ref(false);
const selectedClientName = ref('');

const filteredClients = computed(() => {
    const q = clientSearch.value.toLowerCase().trim();
    if (!q || !props.clients) return props.clients?.slice(0, 30) ?? [];
    return props.clients.filter(c =>
        (c.first_name + ' ' + c.last_name).toLowerCase().includes(q) ||
        (c.phone ?? '').toLowerCase().includes(q)
    ).slice(0, 30);
});

function pickClient(client) {
    form.client_id  = client.id;
    // Pre-fill editable contact fields (usuario puede modificarlos)
    form.first_name = client.first_name ?? '';
    form.last_name  = client.last_name  ?? '';
    form.phone      = client.phone      ?? '';
    form.address    = client.address    ?? '';
    form.rfc        = client.rfc        ?? '';
    form.razon_social = '';
    clientSearch.value       = client.first_name + ' ' + client.last_name + ' — ' + (client.phone ?? '');
    selectedClientName.value = clientSearch.value;
    showClientList.value     = false;
}

// "Nueva visita": cliente y vehículo ya vienen cargados; solo falta kilometraje y síntomas
onMounted(() => {
    const cid = props.preselect?.client_id;
    const vid = props.preselect?.vehicle_id;
    const client = cid ? props.clients?.find((c) => c.id == cid) : null;
    if (!client) return;

    isNewClient.value = false;
    pickClient(client);
    if (vid) {
        // El watch de client_id limpia vehicle_id; lo asignamos después de que corra
        nextTick(() => {
            form.vehicle_id = vid;
            currentStep.value = 2;
        });
    }
});

function clearClient() {
    form.client_id = null;
    clientSearch.value = '';
    selectedClientName.value = '';
}
const goNext = () => {
    if (currentStep.value < totalSteps) currentStep.value++;
};
const goPrev = () => {
    if (currentStep.value > 1) currentStep.value--;
};

const showSuccessModal = ref(false);

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.last_id || flash?.success) {
            showSuccessModal.value = true;
        }
    },
    { deep: true }
);

const closeModal = () => {
    showSuccessModal.value = false;
    router.visit(route("dashboard"));
};

const printPdf = (id) => {
    window.open(route("recepcion.pdf", id), "_blank");
    closeModal();
};

const form = useForm({
    // ─── Control de Registros Existentes (NUEVO) ───
    country: 'MX',
    client_id: null,   // Si esto tiene valor, no validamos nombre/teléfono
    vehicle_id: null,  // Si esto tiene valor, no validamos placas/modelo

    // ─── Cliente (Solo se usará si client_id es null) ───
    first_name: "",
    last_name: "", // Faltaba en tu form original, es requerido en la tabla clients
    phone: "",
    address: "",
    rfc: "",
    razon_social: "",
    sin_factura: false,
    

    // ─── Vehículo (Solo se usará si vehicle_id es null) ───
    brand_id: "",
    vehicle_model_id: "",
    year: "",
    plate: "",
    vin: "",       // Corregido: en tu BD se llama 'vin', no 'vin_serial'
    engine: "",    // Nuevo: tu tabla vehicles ahora tiene motor
    color: "",

    // ─── Recepción (Datos transaccionales, SIEMPRE van) ───
    miles: "",
    fuel_level: "1/2",
    witnesses: [],
    damages: [],
    inventory: [],
    photos: [], 
    symptoms: "",
});

const photoPreviews = ref([]);

const handlePhotoUpload = (event) => {
    const files = Array.from(event.target.files);
    files.forEach((file) => {
        form.photos.push(file); 
        photoPreviews.value.push(URL.createObjectURL(file)); 
    });
};

const removePhoto = (index) => {
    form.photos.splice(index, 1);
    photoPreviews.value.splice(index, 1);
};

// ─── Modelos dinámicos según marca ───
const availableModels = computed(() => {
    if (!props.brands || !form.brand_id) return [];
    const selectedBrand = props.brands.find((b) => b.id == form.brand_id);
    return selectedBrand ? selectedBrand.vehicle_models : [];
});

watch(() => form.brand_id, () => {
    form.vehicle_model_id = '';
});

// ─── Vehículos dinámicos según el cliente seleccionado (NUEVO) ───
// Si eliges a "Juan Pérez", esta propiedad te devuelve solo sus carros
const availableVehicles = computed(() => {
    if (!props.clients || !form.client_id) return [];
    const selectedClient = props.clients.find((c) => c.id == form.client_id);
    return selectedClient ? selectedClient.vehicles : [];
});

// Limpiar el vehículo si cambian al cliente
watch(() => form.client_id, () => {
    form.vehicle_id = null;
});

const fuelLevels = ["E", "1/4", "1/2", "3/4", "F"];

const witnessOptions = computed(() => [
    { key: "engine", label: t('inspeccion.engine') },
    { key: "abs",    label: t('inspeccion.abs') },
    { key: "oil",    label: t('inspeccion.oil') },
    { key: "battery",label: t('inspeccion.battery') },
    { key: "temp",   label: t('inspeccion.temp') },
]);

const toggleWitness = (key) => {
    const idx = form.witnesses.indexOf(key);
    if (idx === -1) form.witnesses.push(key);
    else form.witnesses.splice(idx, 1);
};

const inventoryOptions = computed(() => [
    { key: "llanta",      label: t('recepcion_form.inv_llanta') },
    { key: "gato",        label: t('recepcion_form.inv_gato') },
    { key: "estereo",     label: t('recepcion_form.inv_estereo') },
    { key: "tapetes",     label: t('recepcion_form.inv_tapetes') },
    { key: "herramienta", label: t('recepcion_form.inv_herramienta') },
]);

const toggleList = (list, key) => {
    const idx = list.indexOf(key);
    if (idx === -1) list.push(key);
    else list.splice(idx, 1);
};

const odometerDisplay = computed(() => {
    const val = parseInt(form.miles) || 0;
    return String(val).padStart(6, "0").split("");
});

// ─── Detección de duplicados por placa / VIN ───
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
    if (form.vehicle_id) return; // ya seleccionó vehículo existente
    clearTimeout(plateTimer);
    plateTimer = setTimeout(() => checkField('plate', val?.toUpperCase()), 600);
});

watch(() => form.vin, (val) => {
    if (form.vehicle_id) return;
    clearTimeout(vinTimer);
    vinTimer = setTimeout(() => checkField('vin', val?.toUpperCase()), 600);
});

function useDuplicateVehicle() {
    // Usar el vehículo existente que se detectó
    form.vehicle_id = duplicateAlert.value.vehicle.id;
    showDuplicateModal.value = false;
    duplicateAlert.value = null;
}

// Placa o VIN ya registrados: no se permite capturarlos de nuevo; se limpia el campo para corregir
function ignoreDuplicate() {
    if (duplicateAlert.value?.type === 'plate') form.plate = '';
    if (duplicateAlert.value?.type === 'vin') form.vin = '';
    showDuplicateModal.value = false;
    duplicateAlert.value = null;
}

// ─── Detección de cliente ya registrado (mismo nombre y apellido) ───
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

// ─── Datos fiscales: RFC y razón social ───
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
            // Si es el perfil del mismo cliente que ya está seleccionado, no es duplicado
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

// Mismo cliente seleccionado o cliente nuevo: se puede usar el perfil existente
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

const submit = () => {
    form.post(route("recepcion.store"), {
        onSuccess: () => {
            form.reset();
            photoPreviews.value = [];
        },
    });
};
</script>

<template>
    <Head :title="$t('recepcion_form.titulo') + ' - JK Automotive'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight uppercase tracking-wider">
                    {{ $t('recepcion_form.titulo') }}
                </h2>
                <a :href="route('dashboard')" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                    {{ $t('recepcion_form.volverPanel') }}
                </a>
            </div>
        </template>
        
        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Indicador de pasos -->
                <div class="flex items-center justify-center gap-4 mb-8">
                    <div v-for="step in totalSteps" :key="step" class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all"
                            :class="step === currentStep ? 'bg-jk-blue text-white shadow-lg' : step < currentStep ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'">
                            <span v-if="step < currentStep">✓</span>
                            <span v-else>{{ step }}</span>
                        </div>
                        <span class="text-sm font-medium" :class="step === currentStep ? 'text-jk-blue' : 'text-gray-400'">
                            {{ step === 1 ? $t('recepcion_form.datosCliente') : $t('recepcion_form.datosVehiculo') }}
                        </span>
                        <span v-if="step < totalSteps" class="text-gray-300 ml-2">──</span>
                    </div>
                </div>

                <form @submit.prevent="submit" novalidate>
                    <div class="bg-white shadow sm:rounded-2xl border-t-4 border-jk-blue overflow-hidden">
                        
                        <!-- ═══════════════════════════════════════════════
                             PASO 1: DATOS DEL CLIENTE
                        ════════════════════════════════════════════════ -->
                        <div v-show="currentStep === 1" class="p-8">
                            <h3 class="font-bold text-lg text-jk-blue border-b-2 border-jk-blue pb-2 uppercase tracking-wider mb-6">
                                {{ $t('recepcion_form.datosCliente') }}
                            </h3>

                            <!-- SWITCHER: ¿Cliente Nuevo o Registrado? -->
                            <div class="flex bg-gray-100 p-1 rounded-lg w-fit mb-6">
                                <button type="button" @click="isNewClient = false" 
                                    :class="!isNewClient ? 'bg-white shadow-sm text-jk-blue font-bold' : 'text-gray-500 hover:text-gray-700'" 
                                    class="px-6 py-2 rounded-md text-sm transition-all flex items-center gap-2">
                                    {{ $t('recepcion_form.clienteRegistrado') }}
                                </button>
                                <button type="button" @click="isNewClient = true; form.client_id = null;" 
                                    :class="isNewClient ? 'bg-white shadow-sm text-jk-blue font-bold' : 'text-gray-500 hover:text-gray-700'" 
                                    class="px-6 py-2 rounded-md text-sm transition-all flex items-center gap-2">
                                    {{ $t('recepcion_form.clienteNuevo') }}
                                </button>
                            </div>

                            <!-- VISTA: CLIENTE EXISTENTE -->
                            <!-- COMBOBOX BUSCADOR DE CLIENTE EXISTENTE -->
                            <div v-if="!isNewClient" class="mb-6">
                                <InputLabel for="client_search" :value="$t('recepcion_form.buscarCliente')" />
                                <div class="relative mt-1">
                                    <input
                                        id="client_search"
                                        v-model="clientSearch"
                                        @focus="showClientList = true"
                                        @input="showClientList = true; form.client_id = null;"
                                        type="text"
                                        :placeholder="$t('recepcion_form.buscarPlaceholder')"
                                        autocomplete="off"
                                        class="block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm text-sm"
                                    />
                                    <!-- Botón limpiar -->
                                    <button v-if="form.client_id"
                                        @click="clearClient"
                                        type="button"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition">
                                        ✕
                                    </button>
                                    <!-- Dropdown lista -->
                                    <div v-if="showClientList && filteredClients.length > 0"
                                        class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl max-h-56 overflow-y-auto">
                                        <button v-for="client in filteredClients" :key="client.id"
                                            type="button"
                                            @mousedown.prevent="pickClient(client)"
                                            class="w-full flex items-start gap-3 px-4 py-2.5 hover:bg-blue-50 transition text-left"
                                            :class="form.client_id === client.id ? 'bg-blue-50 font-black' : ''">
                                            <div>
                                                <p class="text-sm font-bold text-gray-800">{{ client.first_name }} {{ client.last_name }}</p>
                                                <p class="text-xs text-gray-400">📞 {{ client.phone ?? '—' }}</p>
                                            </div>
                                        </button>
                                    </div>
                                    <!-- Sin resultados -->
                                    <div v-if="showClientList && clientSearch.length > 1 && filteredClients.length === 0"
                                        class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl px-4 py-3 text-sm text-gray-400 italic">
                                        {{ $t('recepcion_form.sinResultados') }} "{{ clientSearch }}"
                                    </div>
                                </div>
                                <!-- Badge + campos editables cliente existente -->
                                <div v-if="form.client_id" class="mt-3 space-y-3">
                                    <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                                        <span class="text-green-600 text-sm">✅</span>
                                        <p class="text-xs font-bold text-green-800">{{ $t('recepcion.clienteSeleccionado') }} — {{ $t('recepcion_form.clienteEditarDatos') }}</p>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('recepcion_form.nombre') }}</label>
                                            <input v-model="form.first_name" type="text"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('recepcion_form.apellidos') }}</label>
                                            <input v-model="form.last_name" type="text"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('recepcion_form.telefono') }}</label>
                                            <input v-model="form.phone" type="text"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                        </div>
                                        <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">RFC</label>
                                            <input v-model="form.rfc" type="text"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('recepcion_form.direccion') }}</label>
                                            <input v-model="form.address" type="text"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                        </div>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.client_id" />
                            </div>

                            <!-- VISTA: CLIENTE NUEVO -->
                            <div v-if="isNewClient" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="first_name" :value="$t('recepcion_form.nombre')" />
                                    <TextInput id="first_name" type="text" class="mt-1 block w-full" v-model="form.first_name" required :placeholder="$t('recepcion_form.nombrePh')" />
                                    <InputError class="mt-2" :message="form.errors.first_name" />
                                </div>
                                <div>
                                    <InputLabel for="last_name" :value="$t('recepcion_form.apellidos')" />
                                    <TextInput id="last_name" type="text" class="mt-1 block w-full" v-model="form.last_name" required :placeholder="$t('recepcion_form.apellidosPh')" />
                                    <InputError class="mt-2" :message="form.errors.last_name" />
                                </div>
                                <div>
                                    <InputLabel for="phone" :value="$t('recepcion_form.telefono')" />
                                    <TextInput id="phone" type="tel" class="mt-1 block w-full" v-model="form.phone" :placeholder="$t('recepcion_form.telefonoPh')" />
                                    <InputError class="mt-2" :message="form.errors.phone" />
                                </div>
                                <div>
                                    <InputLabel for="rfc" value="RFC" />
                                    <TextInput id="rfc" type="text" class="mt-1 block w-full uppercase" v-model="form.rfc" :placeholder="$t('recepcion_form.rfcPh')" maxlength="13" />
                                    <InputError class="mt-2" :message="form.errors.rfc" />
                                    <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                                        <input type="checkbox" v-model="form.sin_factura" @change="toggleSinFactura" class="rounded" />
                                        Sin factura (RFC genérico de público en general)
                                    </label>
                                    <InputLabel for="razon_social" value="Razón social" class="mt-3" />
                                    <TextInput id="razon_social" type="text" class="mt-1 block w-full uppercase" v-model="form.razon_social" :disabled="form.sin_factura" placeholder="Nombre de la empresa, tal como aparece en la constancia" maxlength="255" />
                                    <InputError class="mt-2" :message="form.errors.razon_social" />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel for="address" :value="$t('recepcion_form.direccion')" />
                                    <TextInput id="address" type="text" class="mt-1 block w-full" v-model="form.address" :placeholder="$t('recepcion_form.direccionPh')" />
                                    <InputError class="mt-2" :message="form.errors.address" />
                                </div>
                            </div>

                            <!-- Validación del botón continuar -->
                            <div class="mt-8 flex justify-end">
                                <button type="button" @click="goNext" 
                                    :disabled="(isNewClient && (!form.first_name || !form.last_name)) || (!isNewClient && !form.client_id)"
                                    class="bg-jk-blue text-white px-8 py-3 rounded-lg font-bold uppercase tracking-wider hover:bg-blue-900 disabled:opacity-40 transition-all">
                                    {{ $t('recepcion_form.continuar') }}
                                </button>
                            </div>
                        </div>

                        <!-- ═══════════════════════════════════════════════
                             PASO 2: DATOS DEL VEHÍCULO Y RECEPCIÓN
                        ════════════════════════════════════════════════ -->
                        <div v-show="currentStep === 2" class="p-8">
                            <h3 class="font-bold text-lg text-jk-red border-b-2 border-jk-red pb-2 uppercase tracking-wider mb-6">
                                {{ $t('recepcion_form.vehiculoTitulo') }}
                            </h3>

                            <!-- Vehículos Existentes del Cliente Seleccionado -->
                            <div v-if="!isNewClient && availableVehicles.length > 0" class="mb-6 bg-blue-50 p-5 rounded-xl border border-blue-100">
                                <InputLabel for="vehicle_id" :value="$t('recepcion_form.vehiculosRegistrados')" class="text-blue-800" />
                                <select id="vehicle_id" v-model="form.vehicle_id" class="mt-2 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm">
                                    <option :value="null">{{ $t('recepcion_form.vehiculoNuevo') }}</option>
                                    <option v-for="vehicle in availableVehicles" :key="vehicle.id" :value="vehicle.id">
                                        {{ vehicle.brand }} {{ vehicle.model }} ({{ vehicle.year }}) - {{ $t('recepcion_form.placas') }}: {{ vehicle.plate }}
                                    </option>
                                </select>
                            </div>

                            <!-- Formulario de Vehículo Nuevo (Se oculta si elige un carro existente) -->
                            <div v-if="!form.vehicle_id" class="bg-gray-50 border border-gray-200 p-6 rounded-xl mb-8">
                                <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase">{{ $t('recepcion_form.datosVehiculoSub') }}</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                                    <div>
                                        <InputLabel for="brand" :value="$t('recepcion_form.marca')" />
                                        <select id="brand" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.brand_id" required>
                                            <option value="" disabled>{{ $t('recepcion_form.seleccione') }}</option>
                                            <option v-for="brand in props.brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.brand_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="model" :value="$t('recepcion_form.modelo')" />
                                        <select id="model" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.vehicle_model_id" :disabled="!form.brand_id" required>
                                            <option value="" disabled>{{ form.brand_id ? $t('recepcion_form.seleccione') + '' : $t('recepcion_form.primerMarca') }}</option>
                                            <option v-for="m in availableModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.vehicle_model_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="year" :value="$t('recepcion_form.anio')" />
                                        <select id="year" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.year" required>
                                            <option value="" disabled>{{ $t('recepcion_form.anio') }}</option>
                                            <option v-for="y in Array.from({ length: 35 }, (_, i) => 2026 - i)" :key="y" :value="String(y)">{{ y }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.year" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                    <div>
                                        <InputLabel for="plate" :value="$t('recepcion_form.placas')" />
                                        <TextInput id="plate" type="text" class="mt-1 block w-full uppercase" v-model="form.plate" :placeholder="$t('recepcion_form.placasPh')" />
                                        <InputError class="mt-2" :message="form.errors.plate" />
                                    </div>
                                    <div>
                                        <InputLabel for="vin" :value="$t('recepcion_form.serie')" />
                                        <TextInput id="vin" type="text" class="mt-1 block w-full uppercase" v-model="form.vin" placeholder="17 caracteres" maxlength="17" />
                                        <InputError class="mt-2" :message="form.errors.vin" />
                                    </div>
                                    <div>
                                        <InputLabel for="engine" :value="$t('recepcion_form.motor')" />
                                        <TextInput id="engine" type="text" class="mt-1 block w-full uppercase" v-model="form.engine" :placeholder="$t('recepcion_form.motorPh')" />
                                        <InputError class="mt-2" :message="form.errors.engine" />
                                    </div>
                                    <div>
                                        <InputLabel for="color" :value="$t('recepcion_form.colorLabel')" />
                                        <TextInput id="color" type="text" class="mt-1 block w-full" v-model="form.color" :placeholder="$t('recepcion_form.colorPh')" />
                                        <InputError class="mt-2" :message="form.errors.color" />
                                    </div>
                                </div>
                            </div>

                            <!-- ════ DATOS TRANSACCIONALES (SIEMPRE VISIBLES) ════ -->
                            <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase border-b pb-2">{{ $t('recepcion_form.condiciones') }}</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                                <!-- Odómetro visual -->
                                <div>
                                    <InputLabel :value="$t('recepcion_form.kilometraje')" />
                                    <div class="mt-2 flex items-center gap-4">
                                        <div class="flex gap-1">
                                            <div v-for="(digit, i) in odometerDisplay" :key="i" class="w-9 h-12 bg-gray-900 text-green-400 flex items-center justify-center text-xl font-mono font-bold rounded-sm border border-gray-700 shadow-inner">
                                                {{ digit }}
                                            </div>
                                        </div>
                                        <TextInput type="number" min="0" max="999999" class="block w-32 text-lg font-mono" v-model="form.miles" placeholder="0" />
                                    </div>
                                </div>

                                <!-- Nivel de Gasolina -->
                                <div>
                                    <InputLabel :value="$t('recepcion_form.gasolina')" />
                                    <div class="mt-3 flex gap-3">
                                        <button v-for="level in fuelLevels" :key="level" type="button" @click="form.fuel_level = level" 
                                            class="w-14 h-14 rounded-full border-2 font-bold text-sm transition-all"
                                            :class="form.fuel_level === level ? 'border-jk-blue bg-jk-blue text-white shadow-lg scale-110' : 'border-gray-300 text-gray-500 hover:border-jk-blue'">
                                            {{ level }}
                                        </button>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.fuel_level" />
                                </div>
                            </div>

                            <!-- Testigos del Tablero -->
                            <div class="mb-6">
                                <InputLabel :value="$t('recepcion_form.testigos')" />
                                <div class="mt-3 flex flex-wrap gap-4">
                                    <button v-for="w in witnessOptions" :key="w.key" type="button" @click="toggleWitness(w.key)" 
                                        class="flex flex-col items-center gap-2 w-20 py-3 rounded-xl border-2 transition-all duration-200"
                                        :class="form.witnesses.includes(w.key) ? 'border-red-500 bg-red-50 text-red-600 shadow-md scale-105' : 'border-gray-200 text-gray-300 hover:border-gray-400 hover:text-gray-400'">
                                        <div class="w-8 h-8">
                                            <IconEngine v-if="w.key === 'engine'" />
                                            <IconAbs v-if="w.key === 'abs'" />
                                            <IconOil v-if="w.key === 'oil'" />
                                            <IconBattery v-if="w.key === 'battery'" />
                                            <IconTemp v-if="w.key === 'temp'" />
                                        </div>
                                        <span class="text-xs font-bold tracking-wider">{{ w.label }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Inventario de ingreso -->
                            <div class="mb-6">
                                <InputLabel :value="$t('recepcion_form.inventarioIngreso')" />
                                <p class="text-xs text-gray-500 mt-1">{{ $t('recepcion_form.inventarioAyuda') }}</p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button v-for="item in inventoryOptions" :key="item.key" type="button" @click="toggleList(form.inventory, item.key)"
                                        class="px-4 py-2 rounded-lg border-2 text-xs font-bold transition-all"
                                        :class="form.inventory.includes(item.key) ? 'border-jk-blue bg-jk-blue text-white' : 'border-gray-200 text-gray-500 hover:border-gray-400'">
                                        {{ item.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Diagrama de daños previos -->
                            <div class="mb-6">
                                <InputLabel :value="$t('recepcion_form.danos')" />
                                <p class="text-xs text-gray-500 mt-1">{{ $t('recepcion_form.danosAyuda') }}</p>
                                <div class="mt-3">
                                    <DamageDiagram v-model="form.damages" />
                                </div>
                            </div>

                            <!-- Fallas reportadas -->
                            <div class="mb-6">
                                <InputLabel for="symptoms" :value="$t('recepcion_form.fallas')" />
                                <textarea id="symptoms" rows="3" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.symptoms" :placeholder="$t('recepcion_form.fallasPh')"></textarea>
                                <InputError class="mt-2" :message="form.errors.symptoms" />
                            </div>

                            <!-- Evidencia Fotográfica -->
                            <div class="pt-6 border-t border-gray-100 mt-6">
                                <label class="block text-sm font-bold text-gray-700 mb-3">{{ $t('recepcion_form.evidencia') }}</label>
                                <div class="flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 hover:border-jk-blue transition-all">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <span class="text-3xl mb-2">📸</span>
                                            <p class="mb-2 text-sm text-gray-500"><span class="font-bold">{{ $t('recepcion_form.subirFotos') }}</span> {{ $t('recepcion_form.arrastraFotos') }}</p>
                                            <p class="text-xs text-gray-400">{{ $t('recepcion_form.formatoFotos') }}</p>
                                        </div>
                                        <input type="file" class="hidden" multiple accept="image/*" @change="handlePhotoUpload" />
                                    </label>
                                </div>

                                <div v-if="photoPreviews.length > 0" class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div v-for="(preview, index) in photoPreviews" :key="index" class="relative group rounded-lg overflow-hidden shadow-sm border border-gray-200">
                                        <img :src="preview" class="w-full h-24 object-cover" />
                                        <button type="button" @click="removePhoto(index)" class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow-md">❌</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Botones de navegación finales -->
                            <div class="flex justify-between mt-8">
                                <button type="button" @click="goPrev" class="bg-gray-100 text-gray-600 px-6 py-3 rounded-lg font-bold uppercase tracking-wider hover:bg-gray-200 transition-all">
                                    {{ $t('recepcion_form.anterior') }}
                                </button>
                                <PrimaryButton class="bg-jk-blue hover:bg-blue-900 text-white px-10 py-3" :disabled="form.processing">
                                    <span v-if="form.processing">{{ $t('recepcion_form.guardando') }}</span>
                                    <span v-else>{{ $t('recepcion_form.finalizar') }}</span>
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════ -->
        <!-- MODAL: VEHÍCULO DUPLICADO DETECTADO           -->
        <!-- ══════════════════════════════════════════════ -->
        <!-- MODAL: RFC YA REGISTRADO -->
        <div v-if="showFiscalModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center border border-orange-100">
                <div class="w-16 h-16 bg-orange-100 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">⚠️</div>
                <h2 class="text-xl font-black text-[#10213E] uppercase mb-2">RFC ya registrado</h2>
                <p class="text-gray-500 text-sm mb-4">Este RFC ya existe en el sistema.</p>

                <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 text-left mb-6">
                    <p class="font-black text-[#10213E]">{{ fiscalDuplicado?.razon_social }}</p>
                    <p class="text-sm text-gray-600 mt-1">RFC: <strong>{{ fiscalDuplicado?.rfc }}</strong></p>
                    <p class="text-sm text-gray-600">Cliente: <strong>{{ fiscalDuplicado?.client_name }}</strong></p>
                </div>

                <p v-if="perfilEsDeOtro()" class="text-sm text-red-600 mb-4">
                    Este RFC pertenece a otro cliente. Corrige el RFC o selecciona al cliente correcto.
                </p>

                <div class="space-y-3">
                    <button v-if="!perfilEsDeOtro()" @click="usarPerfilFiscal"
                        class="w-full bg-[#10213E] text-white py-3 rounded-2xl font-black uppercase tracking-widest text-xs shadow hover:opacity-90 transition">
                        Usar este perfil fiscal
                    </button>
                    <button @click="corregirRfc"
                        class="w-full bg-gray-100 text-gray-500 py-3 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-gray-200 transition">
                        Corregir RFC
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: CLIENTE YA REGISTRADO -->
        <div v-if="showClienteDuplicadoModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center border border-orange-100">
                <div class="w-16 h-16 bg-orange-100 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">⚠️</div>
                <h2 class="text-xl font-black text-[#10213E] uppercase mb-2">{{ $t('recepcion_form.clienteDuplicado') }}</h2>
                <p class="text-gray-500 text-sm mb-4">{{ $t('recepcion_form.clienteDuplicadoDesc') }}</p>

                <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 text-left mb-6">
                    <p class="text-xs text-orange-600 font-bold uppercase tracking-widest mb-2">{{ $t('recepcion_form.clienteEncontrado') }}</p>
                    <p class="font-black text-[#10213E]">{{ clienteDuplicado?.first_name }} {{ clienteDuplicado?.last_name }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $t('recepcion_form.telefonoLabel') }} <strong>{{ clienteDuplicado?.phone ?? '—' }}</strong></p>
                    <p class="text-sm text-gray-600">{{ $t('recepcion_form.noClienteLabel') }} <strong>{{ clienteDuplicado?.client_number ?? '—' }}</strong></p>
                </div>

                <div class="space-y-3">
                    <button @click="usarClienteDuplicado"
                        class="w-full bg-[#10213E] text-white py-3 rounded-2xl font-black uppercase tracking-widest text-xs shadow hover:opacity-90 transition">
                        {{ $t('recepcion_form.usarClienteExistente') }}
                    </button>
                    <button @click="corregirNombre"
                        class="w-full bg-gray-100 text-gray-500 py-3 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-gray-200 transition">
                        {{ $t('recepcion_form.corregirNombre') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDuplicateModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center border border-orange-100">

                <div class="w-16 h-16 bg-orange-100 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                    ⚠️
                </div>
                <h2 class="text-xl font-black text-[#10213E] uppercase mb-2">
                    {{ $t('recepcion_form.vehiculoDuplicado') }}
                </h2>
                <p class="text-gray-500 text-sm mb-4">
                    {{ $t('recepcion_form.duplicadoDesc') }}
                    <strong>{{ duplicateAlert?.type === 'plate' ? $t('recepcion_form.laPlaca') : $t('recepcion_form.elVin') }}</strong>
                    ingresada:
                </p>

                <!-- Tarjeta del vehículo encontrado -->
                <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 text-left mb-6">
                    <p class="text-xs text-orange-600 font-bold uppercase tracking-widest mb-2">{{ $t('recepcion_form.vehiculoEncontrado') }}</p>
                    <p class="font-black text-[#10213E]">
                        {{ duplicateAlert?.vehicle.brand }} {{ duplicateAlert?.vehicle.model }} ({{ duplicateAlert?.vehicle.year }})
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $t('recepcion_form.placaLabel') }} <strong>{{ duplicateAlert?.vehicle.plate ?? '—' }}</strong>
                    </p>
                    <p class="text-sm text-gray-600">
                        {{ $t('recepcion_form.vinLabel') }} <strong>{{ duplicateAlert?.vehicle.vin ?? '—' }}</strong>
                    </p>
                </div>

                <div class="space-y-3">
                    <button @click="useDuplicateVehicle"
                        class="w-full bg-[#10213E] text-white py-3 rounded-2xl font-black uppercase tracking-widest text-xs shadow hover:opacity-90 transition flex items-center justify-center gap-2">
                        {{ $t('recepcion_form.usarExistente') }}
                    </button>
                    <button @click="ignoreDuplicate"
                        class="w-full bg-gray-100 text-gray-500 py-3 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-gray-200 transition">
                        {{ $t('recepcion_form.corregirDatos') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════ -->
        <!-- MODAL: REGISTRO EXITOSO                       -->
        <!-- ══════════════════════════════════════════════ -->
        <div v-if="showSuccessModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-jk-blue/40 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center border border-gray-100">
                <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-jk-blue uppercase mb-2">{{ $t('recepcion_form.ingresoExitoso') }}</h2>
                <p class="text-gray-500 text-sm mb-1 font-medium">{{ $t('recepcion_form.folioRegistrado') }}</p>
                <p class="text-jk-red font-black text-4xl mb-6">#{{ page.props.flash?.last_id }}</p>
                <div class="space-y-3">
                    <button @click="printPdf(page.props.flash?.last_id)"
                        class="w-full bg-jk-blue text-white py-4 rounded-2xl font-black uppercase tracking-widest text-xs shadow-lg hover:opacity-90 transition flex items-center justify-center gap-2">
                        {{ $t('recepcion_form.imprimirNota') }}
                    </button>
                    <button @click="closeModal"
                        class="w-full bg-gray-100 text-gray-500 py-4 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-gray-200 transition">
                        {{ $t('recepcion_form.irDashboard') }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>