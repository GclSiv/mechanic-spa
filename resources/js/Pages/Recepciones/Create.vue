<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import IconEngine from "@/Components/Icons/IconEngine.vue";
import IconAbs from "@/Components/Icons/IconAbs.vue";
import IconOil from "@/Components/Icons/IconOil.vue";
import IconBattery from "@/Components/Icons/IconBattery.vue";
import IconTemp from "@/Components/Icons/IconTemp.vue";

const props = defineProps({
    brands: Array,
    clients: Array, // NUEVO: Necesario para seleccionar clientes que ya existen
});

const page = usePage();
const currentStep = ref(1);
const totalSteps = 2;
const isNewClient = ref(true); 
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
    client_id: null,   // Si esto tiene valor, no validamos nombre/teléfono
    vehicle_id: null,  // Si esto tiene valor, no validamos placas/modelo

    // ─── Cliente (Solo se usará si client_id es null) ───
    first_name: "",
    last_name: "", // Faltaba en tu form original, es requerido en la tabla clients
    phone: "",
    address: "",
    rfc: "",
    

    // ─── Vehículo (Solo se usará si vehicle_id es null) ───
    brand_id: "",
    vehicle_model_id: "",
    year: "",
    plate: "",
    vin: "",       // Corregido: en tu BD se llama 'vin', no 'vin_serial'
    engine: "",    // Nuevo: tu tabla vehicles ahora tiene motor

    // ─── Recepción (Datos transaccionales, SIEMPRE van) ───
    miles: "",
    fuel_level: "1/2",
    witnesses: [],
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

const witnessOptions = [
    { key: "engine", label: "ENGINE" },
    { key: "abs", label: "ABS" },
    { key: "oil", label: "ACEITE" },
    { key: "battery", label: "BATERÍA" },
    { key: "temp", label: "TEMP" },
];

const toggleWitness = (key) => {
    const idx = form.witnesses.indexOf(key);
    if (idx === -1) form.witnesses.push(key);
    else form.witnesses.splice(idx, 1);
};

const odometerDisplay = computed(() => {
    const val = parseInt(form.miles) || 0;
    return String(val).padStart(6, "0").split("");
});

const submit = () => {
    form.post(route("recepcion.store"), {
        // Inertia.js procesa automáticamente los archivos (photos) y setea forceFormData si es necesario
        onSuccess: () => {
            form.reset();
            photoPreviews.value = []; 
        },
    });
};
</script>

<template>
    <Head title="Nueva Recepción - JK Automotive" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight uppercase tracking-wider">
                    Registrar Entrada de Vehículo
                </h2>
                <a :href="route('dashboard')" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                    ← Volver al Panel
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
                            {{ step === 1 ? "Datos del Cliente" : "Datos del Vehículo" }}
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
                                Datos del Cliente
                            </h3>

                            <!-- SWITCHER: ¿Cliente Nuevo o Registrado? -->
                            <div class="flex bg-gray-100 p-1 rounded-lg w-fit mb-6">
                                <button type="button" @click="isNewClient = false" 
                                    :class="!isNewClient ? 'bg-white shadow-sm text-jk-blue font-bold' : 'text-gray-500 hover:text-gray-700'" 
                                    class="px-6 py-2 rounded-md text-sm transition-all flex items-center gap-2">
                                    👤 Cliente Registrado
                                </button>
                                <button type="button" @click="isNewClient = true; form.client_id = null;" 
                                    :class="isNewClient ? 'bg-white shadow-sm text-jk-blue font-bold' : 'text-gray-500 hover:text-gray-700'" 
                                    class="px-6 py-2 rounded-md text-sm transition-all flex items-center gap-2">
                                    ➕ Cliente Nuevo
                                </button>
                            </div>

                            <!-- VISTA: CLIENTE EXISTENTE -->
                            <div v-if="!isNewClient" class="mb-6">
                                <InputLabel for="client_id" value="Buscar Cliente en la Base de Datos *" />
                                <select id="client_id" v-model="form.client_id" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" required>
                                    <option :value="null" disabled>Seleccione un cliente...</option>
                                    <option v-for="client in props.clients" :key="client.id" :value="client.id">
                                        {{ client.first_name }} {{ client.last_name }} - Tel: {{ client.phone }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.client_id" />
                            </div>

                            <!-- VISTA: CLIENTE NUEVO -->
                            <div v-if="isNewClient" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="first_name" value="Nombre(s) *" />
                                    <TextInput id="first_name" type="text" class="mt-1 block w-full" v-model="form.first_name" required placeholder="Ej. Juan" />
                                    <InputError class="mt-2" :message="form.errors.first_name" />
                                </div>
                                <div>
                                    <InputLabel for="last_name" value="Apellidos *" />
                                    <TextInput id="last_name" type="text" class="mt-1 block w-full" v-model="form.last_name" required placeholder="Ej. García López" />
                                    <InputError class="mt-2" :message="form.errors.last_name" />
                                </div>
                                <div>
                                    <InputLabel for="phone" value="Teléfono / WhatsApp" />
                                    <TextInput id="phone" type="tel" class="mt-1 block w-full" v-model="form.phone" placeholder="Ej. 9515087464" />
                                    <InputError class="mt-2" :message="form.errors.phone" />
                                </div>
                                <div>
                                    <InputLabel for="rfc" value="RFC" />
                                    <TextInput id="rfc" type="text" class="mt-1 block w-full uppercase" v-model="form.rfc" placeholder="Ej. GARL850101ABC" maxlength="13" />
                                    <InputError class="mt-2" :message="form.errors.rfc" />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel for="address" value="Dirección" />
                                    <TextInput id="address" type="text" class="mt-1 block w-full" v-model="form.address" placeholder="Calle, número, colonia, ciudad..." />
                                    <InputError class="mt-2" :message="form.errors.address" />
                                </div>
                            </div>

                            <!-- Validación del botón continuar -->
                            <div class="mt-8 flex justify-end">
                                <button type="button" @click="goNext" 
                                    :disabled="(isNewClient && (!form.first_name || !form.last_name)) || (!isNewClient && !form.client_id)"
                                    class="bg-jk-blue text-white px-8 py-3 rounded-lg font-bold uppercase tracking-wider hover:bg-blue-900 disabled:opacity-40 transition-all">
                                    Continuar →
                                </button>
                            </div>
                        </div>

                        <!-- ═══════════════════════════════════════════════
                             PASO 2: DATOS DEL VEHÍCULO Y RECEPCIÓN
                        ════════════════════════════════════════════════ -->
                        <div v-show="currentStep === 2" class="p-8">
                            <h3 class="font-bold text-lg text-jk-red border-b-2 border-jk-red pb-2 uppercase tracking-wider mb-6">
                                Vehículo y Estado al Ingreso
                            </h3>

                            <!-- Vehículos Existentes del Cliente Seleccionado -->
                            <div v-if="!isNewClient && availableVehicles.length > 0" class="mb-6 bg-blue-50 p-5 rounded-xl border border-blue-100">
                                <InputLabel for="vehicle_id" value="Vehículos Registrados de este Cliente" class="text-blue-800" />
                                <select id="vehicle_id" v-model="form.vehicle_id" class="mt-2 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm">
                                    <option :value="null">➕ Registrar un vehículo NUEVO para este cliente...</option>
                                    <option v-for="vehicle in availableVehicles" :key="vehicle.id" :value="vehicle.id">
                                        {{ vehicle.brand }} {{ vehicle.model }} ({{ vehicle.year }}) - Placas: {{ vehicle.plate }}
                                    </option>
                                </select>
                            </div>

                            <!-- Formulario de Vehículo Nuevo (Se oculta si elige un carro existente) -->
                            <div v-if="!form.vehicle_id" class="bg-gray-50 border border-gray-200 p-6 rounded-xl mb-8">
                                <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase">Datos del Vehículo a Registrar</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                                    <div>
                                        <InputLabel for="brand" value="Marca *" />
                                        <select id="brand" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.brand_id" required>
                                            <option value="" disabled>Seleccione...</option>
                                            <option v-for="brand in props.brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.brand_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="model" value="Modelo *" />
                                        <select id="model" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.vehicle_model_id" :disabled="!form.brand_id" required>
                                            <option value="" disabled>{{ form.brand_id ? "Seleccione..." : "Primero marca" }}</option>
                                            <option v-for="m in availableModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.vehicle_model_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="year" value="Año *" />
                                        <select id="year" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.year" required>
                                            <option value="" disabled>Año...</option>
                                            <option v-for="y in Array.from({ length: 35 }, (_, i) => 2026 - i)" :key="y" :value="String(y)">{{ y }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.year" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <InputLabel for="plate" value="Placas" />
                                        <TextInput id="plate" type="text" class="mt-1 block w-full uppercase" v-model="form.plate" placeholder="Ej. ABC-123-D" />
                                        <InputError class="mt-2" :message="form.errors.plate" />
                                    </div>
                                    <div>
                                        <InputLabel for="vin" value="Número de Serie (VIN)" />
                                        <TextInput id="vin" type="text" class="mt-1 block w-full uppercase" v-model="form.vin" placeholder="17 caracteres" maxlength="17" />
                                        <InputError class="mt-2" :message="form.errors.vin" />
                                    </div>
                                    <div>
                                        <InputLabel for="engine" value="Motor" />
                                        <TextInput id="engine" type="text" class="mt-1 block w-full uppercase" v-model="form.engine" placeholder="Ej. 2.0L o V6" />
                                        <InputError class="mt-2" :message="form.errors.engine" />
                                    </div>
                                </div>
                            </div>

                            <!-- ════ DATOS TRANSACCIONALES (SIEMPRE VISIBLES) ════ -->
                            <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase border-b pb-2">Condiciones de Ingreso al Taller</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                                <!-- Odómetro visual -->
                                <div>
                                    <InputLabel value="Kilometraje / Millaje" />
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
                                    <InputLabel value="Nivel de Gasolina *" />
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
                                <InputLabel value="Testigos Encendidos en el Tablero" />
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

                            <!-- Fallas reportadas -->
                            <div class="mb-6">
                                <InputLabel for="symptoms" value="Fallas Reportadas / Observaciones del Cliente" />
                                <textarea id="symptoms" rows="3" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.symptoms" placeholder="Describa el problema reportado, ruidos, servicios solicitados..."></textarea>
                                <InputError class="mt-2" :message="form.errors.symptoms" />
                            </div>

                            <!-- Evidencia Fotográfica -->
                            <div class="pt-6 border-t border-gray-100 mt-6">
                                <label class="block text-sm font-bold text-gray-700 mb-3">Evidencia Fotográfica (Opcional)</label>
                                <div class="flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 hover:border-jk-blue transition-all">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <span class="text-3xl mb-2">📸</span>
                                            <p class="mb-2 text-sm text-gray-500"><span class="font-bold">Haz clic para subir fotos</span> o arrástralas aquí</p>
                                            <p class="text-xs text-gray-400">PNG, JPG o WEBP (Max. 5MB)</p>
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
                                    ← Anterior
                                </button>
                                <PrimaryButton class="bg-jk-blue hover:bg-blue-900 text-white px-10 py-3" :disabled="form.processing">
                                    <span v-if="form.processing">⏳ Guardando...</span>
                                    <span v-else>✓ Finalizar Recepción</span>
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL DE ÉXITO (Sin cambios) -->
        <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-jk-blue/40 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center border border-gray-100">
                <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-black text-jk-blue uppercase mb-2">¡Ingreso Exitoso!</h2>
                <p class="text-gray-500 text-sm mb-6 font-medium">Se ha registrado la recepción con el folio:<br />
                    <span class="text-jk-red font-black text-3xl">#{{ page.props.flash?.last_id }}</span>
                </p>
                <div class="space-y-3">
                    <button @click="printPdf(page.props.flash?.last_id)" class="w-full bg-jk-blue text-white py-4 rounded-2xl font-black uppercase tracking-widest text-xs shadow-lg hover:opacity-90 transition-all flex items-center justify-center gap-2">
                        🖨️ Imprimir Nota de Recepción
                    </button>
                    <button @click="closeModal" class="w-full bg-gray-100 text-gray-500 py-4 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-gray-200 transition-all">
                        Ir al Dashboard
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>