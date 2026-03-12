<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// 1. Nombres consistentes con la Base de Datos
const form = useForm({
    first_name: '',
    phone: '',
    address: '',
    rfc: '',
    brand_id: '',         // Cambiado de 'brand' a 'brand_id'
    vehicle_model_id: '', // Cambiado de 'model' a 'vehicle_model_id'
    year: '',
    plate: '',
    engine: '',
    vin: '',
    miles: '',
    description: '',
});

const props = defineProps({
    brands: Array
});

// 2. Lógica corregida para filtrar modelos
const availableModels = computed(() => {
    if (!props.brands || !form.brand_id) return [];

    // Buscamos la marca seleccionada
    const selectedBrand = props.brands.find(brand => brand.id == form.brand_id);
    
    // Retornamos el ARREGLO de modelos (la relación que viene de Laravel)
    return selectedBrand ? selectedBrand.vehicle_models : [];
});

const submit = () => {
    form.post(route('recepcion.store'), {
        onFinish: () => form.reset(),
    });
};

const page = usePage();
const successMessage = computed(() => page.props.flash?.success);
</script>

<template>
    <Head title="Nueva Recepción - JK Automotive" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar Nueva Recepción</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white p-8 shadow sm:rounded-lg border-t-4 border-jk-blue">
                    
                    <div v-if="successMessage" class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 font-medium rounded shadow-sm">
                        {{ successMessage }}
                    </div>

                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            
                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-jk-blue border-b-2 border-jk-blue pb-2 uppercase tracking-wider">
                                    Información del Cliente
                                </h3>
                                <div>
                                    <InputLabel for="first_name" value="Nombre Completo" />
                                    <TextInput id="first_name" type="text" class="mt-1 block w-full" v-model="form.first_name" required />
                                    <InputError class="mt-2" :message="form.errors.first_name" />
                                </div>
                                <div>
                                    <InputLabel for="phone" value="Teléfono" />
                                    <TextInput id="phone" type="text" class="mt-1 block w-full" v-model="form.phone" required />
                                    <InputError class="mt-2" :message="form.errors.phone" />
                                </div>
                                <div>
                                    <InputLabel for="address" value="Dirección" />
                                    <TextInput id="address" type="text" class="mt-1 block w-full" v-model="form.address" />
                                    <InputError class="mt-2" :message="form.errors.address" />
                                </div>
                                <div>
                                    <InputLabel for="rfc" value="RFC" />
                                    <TextInput id="rfc" type="text" class="mt-1 block w-full uppercase" v-model="form.rfc" />
                                    <InputError class="mt-2" :message="form.errors.rfc" />
                                </div>
                            </div>

                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-jk-red border-b-2 border-jk-red pb-2 uppercase tracking-wider">
                                    Información del Vehículo
                                </h3>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="brand" value="Marca" />
                                        <select id="brand" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.brand_id" required>
                                            <option value="" disabled>Seleccione...</option>
                                            <option v-for="brand in props.brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.brand_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="model" value="Modelo" />
                                        <select id="model" class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm" v-model="form.vehicle_model_id" required>
                                            <option value="" disabled>Seleccione...</option>
                                            <option v-for="vehicleModel in availableModels" :key="vehicleModel.id" :value="vehicleModel.id">{{ vehicleModel.name }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.vehicle_model_id" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="year" value="Año" />
                                        <TextInput id="year" type="text" class="mt-1 block w-full" v-model="form.year" required />
                                        <InputError class="mt-2" :message="form.errors.year" />
                                    </div>
                                    <div>
                                        <InputLabel for="plate" value="Placas" />
                                        <TextInput id="plate" type="text" class="mt-1 block w-full uppercase" v-model="form.plate" required />
                                        <InputError class="mt-2" :message="form.errors.plate" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="engine" value="Motor (Engine)" />
                                        <TextInput id="engine" type="text" class="mt-1 block w-full" v-model="form.engine" placeholder="Ej. 2.5L" />
                                        <InputError class="mt-2" :message="form.errors.engine" />
                                    </div>
                                    <div>
                                        <InputLabel for="miles" value="Millas / Km" />
                                        <TextInput id="miles" type="text" class="mt-1 block w-full" v-model="form.miles" />
                                        <InputError class="mt-2" :message="form.errors.miles" />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="vin" value="Número de Serie (VIN)" />
                                    <TextInput id="vin" type="text" class="mt-1 block w-full uppercase" v-model="form.vin" />
                                    <InputError class="mt-2" :message="form.errors.vin" />
                                </div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <h3 class="font-bold text-lg text-gray-700 border-b pb-2 uppercase mb-4">Petición del Cliente (Customer Request)</h3>
                            <textarea 
                                id="description" 
                                rows="4" 
                                class="mt-1 block w-full border-gray-300 focus:border-jk-blue focus:ring-jk-blue rounded-md shadow-sm"
                                v-model="form.description"
                                placeholder="Describa el servicio o falla reportada..."
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <div class="mt-8 flex justify-end">
                            <PrimaryButton 
                                class="bg-jk-blue hover:bg-blue-900 text-white px-8 py-3" 
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Guardando...</span>
                                <span v-else>Guardar Recepción</span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>