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

// ─── Props ────────────────────────────────────────────────────
const props = defineProps({
    brands: Array,
});

const page = usePage();

// ─── Wizard (pasos) ──────────────────────────────────────────
const currentStep = ref(1);
const totalSteps = 2;
const isNewClient = ref(true);
const goNext = () => {
    if (currentStep.value < totalSteps) currentStep.value++;
};
const goPrev = () => {
    if (currentStep.value > 1) currentStep.value--;
};

// ─── Modal de éxito ──────────────────────────────────────────
// ─── Modal de éxito ──────────────────────────────────────────
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

// ─── Formulario ──────────────────────────────────────────────
const form = useForm({
    // Cliente
    first_name: "",
    phone: "",
    address: "",
    rfc: "",

    // Vehículo
    brand_id: "",
    vehicle_model_id: "",
    year: "",
    plate: "",
    vin_serial: "",
    miles: "",

    // Estado al ingreso
    fuel_level: "1/2",
    witnesses: [],
    inventory: [],
    photos: [], // NUEVO: Arreglo vacío para guardar los archivos antes de  enviarlos
    symptoms: "",
});

//  NUEVO: Variable para mostrar las vistas previas de las fotos en pantalla
const photoPreviews = ref([]);

// NUEVO: Función para procesar las fotos cuando el usuario las selecciona
const handlePhotoUpload = (event) => {
    const files = Array.from(event.target.files);
    files.forEach((file) => {
        form.photos.push(file); // Guardamos el archivo real para enviarlo
        photoPreviews.value.push(URL.createObjectURL(file)); // Creamos una URL temporal para la vista previa
    });
};
//  NUEVO: Función para quitar una foto si te equivocaste
const removePhoto = (index) => {
    form.photos.splice(index, 1);
    photoPreviews.value.splice(index, 1);
};
// ─── Modelos dinámicos según marca ───────────────────────────
const availableModels = computed(() => {
    if (!props.brands || !form.brand_id) return [];
    const selectedBrand = props.brands.find((b) => b.id == form.brand_id);
    return selectedBrand ? selectedBrand.vehicle_models : [];
});

// Resetear modelo al cambiar marca
watch(
    () => form.brand_id,
    () => {
        form.vehicle_model_id = "";
    },
);

// ─── Nivel de gasolina ───────────────────────────────────────
const fuelLevels = ["E", "1/4", "1/2", "3/4", "F"];

// ─── Testigos del tablero ────────────────────────────────────
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

// ─── Odómetro visual ─────────────────────────────────────────
const odometerDisplay = computed(() => {
    const val = parseInt(form.miles) || 0;
    return String(val).padStart(6, "0").split("");
});

// ─── Submit ──────────────────────────────────────────────────
const submit = () => {
    form.post(route("recepcion.store"), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Nueva Recepción - JK Automotive" />
    <h1 class="text-4xl text-red-600 font-black text-center my-4">
        ¡HOLA GILBERTO!
    </h1>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="font-semibold text-xl text-gray-800 leading-tight uppercase tracking-wider"
                >
                    Registrar Entrada de Vehículo
                </h2>
                <a
                    :href="route('dashboard')"
                    class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1"
                >
                    ← Volver al Panel
                </a>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <!-- Indicador de pasos -->
                <div class="flex items-center justify-center gap-4 mb-8">
                    <div
                        v-for="step in totalSteps"
                        :key="step"
                        class="flex items-center gap-2"
                    >
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all"
                            :class="
                                step === currentStep
                                    ? 'bg-jk-blue text-white shadow-lg'
                                    : step < currentStep
                                      ? 'bg-green-500 text-white'
                                      : 'bg-gray-200 text-gray-400'
                            "
                        >
                            <span v-if="step < currentStep">✓</span>
                            <span v-else>{{ step }}</span>
                        </div>
                        <span
                            class="text-sm font-medium"
                            :class="
                                step === currentStep
                                    ? 'text-jk-blue'
                                    : 'text-gray-400'
                            "
                        >
                            {{
                                step === 1
                                    ? "Datos del Cliente"
                                    : "Datos del Vehículo"
                            }}
                        </span>
                        <span
                            v-if="step < totalSteps"
                            class="text-gray-300 ml-2"
                            >──</span
                        >
                    </div>
                </div>

                <form @submit.prevent="submit">
                    <div
                        class="bg-white shadow sm:rounded-2xl border-t-4 border-jk-blue overflow-hidden"
                    >
                        <!-- ═══════════════════════════════════════════════
                             PASO 1: DATOS DEL CLIENTE
                        ════════════════════════════════════════════════ -->
                        <div v-show="currentStep === 1" class="p-8">
                            <h3
                                class="font-bold text-lg text-jk-blue border-b-2 border-jk-blue pb-2 uppercase tracking-wider mb-6"
                            >
                                Datos del Cliente
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel
                                        for="first_name"
                                        value="Nombre Completo *"
                                    />
                                    <TextInput id="first_name" type="text" class="mt-1 block w-full"
                                        v-model="form.first_name" required placeholder="Ej. Juan García López" />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.first_name"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        for="phone"
                                        value="Teléfono / WhatsApp"
                                    />
                                    <TextInput
                                        id="phone"
                                        type="tel"
                                        class="mt-1 block w-full"
                                        v-model="form.phone"
                                        placeholder="Ej. 9515087464"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.phone"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel
                                        for="address"
                                        value="Dirección"
                                    />
                                    <TextInput
                                        id="address"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.address"
                                        placeholder="Calle, número, colonia, ciudad..."
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.address"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="rfc" value="RFC" />
                                    <TextInput
                                        id="rfc"
                                        type="text"
                                        class="mt-1 block w-full uppercase"
                                        v-model="form.rfc"
                                        placeholder="Ej. GARL850101ABC"
                                        maxlength="13"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.rfc"
                                    />
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end">
                                <button
                                    type="button"
                                    @click="goNext"
                                    :disabled="!form.first_name"
                                    class="bg-jk-blue text-white px-8 py-3 rounded-lg font-bold uppercase tracking-wider hover:bg-blue-900 disabled:opacity-40 transition-all"
                                >
                                    Continuar →
                                </button>
                            </div>
                        </div>

                        <!-- ═══════════════════════════════════════════════
                             PASO 2: DATOS DEL VEHÍCULO
                        ════════════════════════════════════════════════ -->
                        <div v-show="currentStep === 2" class="p-8">
                            <h3
                                class="font-bold text-lg text-jk-red border-b-2 border-jk-red pb-2 uppercase tracking-wider mb-6"
                            >
                                Datos del Vehículo
                            </h3>

                            <!-- Marca y Modelo dinámico -->
                            <div
                                class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6"
                            >
                                <div>
                                    <InputLabel
                                        for="brand"
                                        value="Marca del Vehículo *"
                                    />
                                    <select
                                        id="brand"
                                        class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm"
                                        v-model="form.brand_id"
                                        required
                                    >
                                        <option value="" disabled>
                                            Seleccione marca...
                                        </option>
                                        <option
                                            v-for="brand in props.brands"
                                            :key="brand.id"
                                            :value="brand.id"
                                        >
                                            {{ brand.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.brand_id"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="model" value="Modelo *" />
                                    <select
                                        id="model"
                                        class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm"
                                        v-model="form.vehicle_model_id"
                                        :disabled="!form.brand_id"
                                        required
                                    >
                                        <option value="" disabled>
                                            {{
                                                form.brand_id
                                                    ? "Seleccione modelo..."
                                                    : "Primero seleccione marca"
                                            }}
                                        </option>
                                        <option
                                            v-for="m in availableModels"
                                            :key="m.id"
                                            :value="m.id"
                                        >
                                            {{ m.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.vehicle_model_id"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="year" value="Año *" />
                                    <select
                                        id="year"
                                        class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm"
                                        v-model="form.year"
                                        required
                                    >
                                        <option value="" disabled>
                                            Año...
                                        </option>
                                        <option
                                            v-for="y in Array.from(
                                                { length: 35 },
                                                (_, i) => 2025 - i,
                                            )"
                                            :key="y"
                                            :value="String(y)"
                                        >
                                            {{ y }}
                                        </option>
                                    </select>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.year"
                                    />
                                </div>
                            </div>

                            <!-- Placa y VIN -->
                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6"
                            >
                                <div>
                                    <InputLabel for="plate" value="Placas" />
                                    <TextInput
                                        id="plate"
                                        type="text"
                                        class="mt-1 block w-full uppercase"
                                        v-model="form.plate"
                                        placeholder="Ej. ABC-123-D"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.plate"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        for="vin_serial"
                                        value="Número de Serie (VIN)"
                                    />
                                    <TextInput
                                        id="vin_serial"
                                        type="text"
                                        class="mt-1 block w-full uppercase"
                                        v-model="form.vin_serial"
                                        placeholder="17 caracteres"
                                        maxlength="17"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.vin_serial"
                                    />
                                </div>
                            </div>

                            <!-- Odómetro visual + Kilometraje -->
                            <div class="mb-6">
                                <InputLabel value="Kilometraje al Ingreso" />
                                <div class="mt-2 flex items-center gap-4">
                                    <!-- Display odómetro -->
                                    <div class="flex gap-1">
                                        <div
                                            v-for="(
                                                digit, i
                                            ) in odometerDisplay"
                                            :key="i"
                                            class="w-9 h-12 bg-gray-900 text-green-400 flex items-center justify-center text-xl font-mono font-bold rounded-sm border border-gray-700 shadow-inner"
                                        >
                                            {{ digit }}
                                        </div>
                                        <div
                                            class="w-6 h-12 bg-gray-900 text-gray-500 flex items-center justify-center text-sm font-mono rounded-sm border border-gray-700 ml-1"
                                        >
                                            km
                                        </div>
                                    </div>
                                    <!-- Input numérico -->
                                    <TextInput
                                        type="number"
                                        min="0"
                                        max="999999"
                                        class="block w-full text-lg font-mono"
                                        v-model="form.miles"
                                        placeholder="0"
                                    />
                                </div>
                            </div>

                            <!-- Nivel de Gasolina -->
                            <div class="mb-6">
                                <InputLabel
                                    value="Nivel de Gasolina al Ingreso *"
                                />
                                <div class="mt-3 flex items-center gap-6">
                                    <div class="flex gap-3">
                                        <button
                                            v-for="level in fuelLevels"
                                            :key="level"
                                            type="button"
                                            @click="form.fuel_level = level"
                                            class="w-14 h-14 rounded-full border-2 font-bold text-sm transition-all"
                                            :class="
                                                form.fuel_level === level
                                                    ? 'border-jk-blue bg-jk-blue text-white shadow-lg scale-110'
                                                    : 'border-gray-300 text-gray-500 hover:border-jk-blue'
                                            "
                                        >
                                            {{ level }}
                                        </button>
                                    </div>
                                </div>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.fuel_level"
                                />
                            </div>

                            <!-- Testigos del Tablero -->
                            <!-- Testigos del Tablero -->
                            <div class="mb-6">
                                <InputLabel value="Testigos del Tablero" />
                                <div class="mt-3 flex flex-wrap gap-4">
                                    <button
                                        v-for="w in witnessOptions"
                                        :key="w.key"
                                        type="button"
                                        @click="toggleWitness(w.key)"
                                        class="flex flex-col items-center gap-2 w-20 py-3 rounded-xl border-2 transition-all duration-200"
                                        :class="
                                            form.witnesses.includes(w.key)
                                                ? 'border-red-500 bg-red-50 text-red-600 shadow-md scale-105'
                                                : 'border-gray-200 text-gray-300 hover:border-gray-400 hover:text-gray-400'
                                        "
                                    >
                                        <div class="w-8 h-8">
                                            <IconEngine
                                                v-if="w.key === 'engine'"
                                            />
                                            <IconAbs v-if="w.key === 'abs'" />
                                            <IconOil v-if="w.key === 'oil'" />
                                            <IconBattery
                                                v-if="w.key === 'battery'"
                                            />
                                            <IconTemp v-if="w.key === 'temp'" />
                                        </div>
                                        <span
                                            class="text-xs font-bold tracking-wider"
                                            >{{ w.label }}</span
                                        >
                                    </button>
                                </div>
                            </div>

                            <!-- Fallas reportadas -->

                            
                            <div class="mb-6">
                                <InputLabel
                                    for="symptoms"
                                    value="Falla Reportadass / Observaciones"
                                />
                                <textarea
                                    id="symptoms"
                                    rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm"
                                    v-model="form.symptoms"
                                    placeholder="Describa el problema o servicio solicitado..."
                                ></textarea>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.symptoms"
                                />
      
                                <div class="pt-6 border-t border-gray-100 mt-6">
                                    <label
                                        class="block text-sm font-bold text-gray-700 mb-3"
                                        >Evidencia Fotográfica (Opcional)</label
                                    >

                                    <div
                                        class="flex items-center justify-center w-full"
                                    >
                                        <label
                                            class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 hover:border-jk-blue transition-all"
                                        >
                                            <div
                                                class="flex flex-col items-center justify-center pt-5 pb-6"
                                            >
                                                <span class="text-3xl mb-2"
                                                    >📸</span
                                                >
                                                <p
                                                    class="mb-2 text-sm text-gray-500"
                                                >
                                                    <span class="font-bold"
                                                        >Haz clic para subir
                                                        fotos</span
                                                    >
                                                    o arrástralas aquí
                                                </p>
                                                <p
                                                    class="text-xs text-gray-400"
                                                >
                                                    PNG, JPG o WEBP (Max. 5MB)
                                                </p>
                                            </div>
                                            <input
                                                type="file"
                                                class="hidden"
                                                multiple
                                                accept="image/*"
                                                @change="handlePhotoUpload"
                                            />
                                        </label>
                                    </div>

                                    <div
                                        v-if="photoPreviews.length > 0"
                                        class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4"
                                    >
                                        <div
                                            v-for="(
                                                preview, index
                                            ) in photoPreviews"
                                            :key="index"
                                            class="relative group rounded-lg overflow-hidden shadow-sm border border-gray-200"
                                        >
                                            <img
                                                :src="preview"
                                                class="w-full h-24 object-cover"
                                            />
                                            <button
                                                type="button"
                                                @click="removePhoto(index)"
                                                class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow-md"
                                            >
                                                ❌
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                      

                            <!-- Botones de navegación -->
                            <div class="flex justify-between mt-8">
                                <button
                                    type="button"
                                    @click="goPrev"
                                    class="bg-gray-100 text-gray-600 px-6 py-3 rounded-lg font-bold uppercase tracking-wider hover:bg-gray-200 transition-all"
                                >
                                    ← Anterior
                                </button>

                                <PrimaryButton
                                    class="bg-jk-blue hover:bg-blue-900 text-white px-10 py-3"
                                    :disabled="form.processing"
                                >
                                    <span v-if="form.processing"
                                        >⏳ Guardando...</span
                                    >
                                    <span v-else>✓ Finalizar Recepción</span>
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════
             MODAL DE ÉXITO
        ════════════════════════════════════════════════ -->
        <div
            v-if="showSuccessModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-jk-blue/40 backdrop-blur-sm"
        >
            <div
                class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center border border-gray-100"
            >
                <div
                    class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6"
                >
                    <svg
                        class="w-10 h-10"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="3"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>

                <h2 class="text-2xl font-black text-jk-blue uppercase mb-2">
                    ¡Ingreso Exitoso!
                </h2>
                <p class="text-gray-500 text-sm mb-6 font-medium">
                    Se ha registrado la recepción con el folio:<br />
                    <span class="text-jk-red font-black text-3xl"
                        >#{{ page.props.flash?.last_id }}</span
                    >
                </p>

                <div class="space-y-3">
                    <button
                        @click="printPdf(page.props.flash?.last_id)"
                        class="w-full bg-jk-blue text-white py-4 rounded-2xl font-black uppercase tracking-widest text-xs shadow-lg hover:opacity-90 transition-all flex items-center justify-center gap-2"
                    >
                        🖨️ Imprimir Nota de Recepción
                    </button>
                    <button
                        @click="closeModal"
                        class="w-full bg-gray-100 text-gray-500 py-4 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-gray-200 transition-all"
                    >
                        Ir al Dashboard
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
