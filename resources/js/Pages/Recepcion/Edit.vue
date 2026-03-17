<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// 1. Recibimos los datos exactos que nos manda el Controlador
const props = defineProps({
    brands: Array,
    recepcion: Object,
});

// 2. Control de la vista
const currentStep = ref(1);

// 3. PRE-LLENADO: El formulario arranca con la memoria de la base de datos
const form = useForm({
    first_name: props.recepcion.first_name || '',
    phone: props.recepcion.phone || '',
    address: props.recepcion.address || '',
    rfc: props.recepcion.rfc || '',
    brand_id: props.recepcion.brand_id || '',
    vehicle_model_id: props.recepcion.vehicle_model_id || '',
    year: props.recepcion.year || '',
    plate: props.recepcion.plate || '',
    vin_serial: props.recepcion.vin_serial || '',
    miles: props.recepcion.miles || '',
    fuel_level: props.recepcion.fuel_level || '1/4',
    symptoms: props.recepcion.symptoms || '',
    witnesses: props.recepcion.witnesses || [],
    inventory: props.recepcion.inventory || [],
});

// 4. Lógica de Marcas y Modelos Dinámicos
const availableModels = computed(() => {
    const brand = props.brands.find(b => b.id === form.brand_id);
    return brand ? brand.vehicle_models : [];
});

// Niveles de gasolina para el diseño visual
const fuelLevels = ['E', '1/4', '1/2', '3/4', 'F'];

// 5. Enviar los datos actualizados (usando PUT)
const submit = () => {
    // Usamos form.put y apuntamos a la ruta update con el ID del registro
    form.put(route("recepcion.update", props.recepcion.id), {
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => {
            console.error("🚨 Laravel rechazó los datos:", errors);
            alert("Por favor, revisa los campos requeridos.");
        }
    });
};
</script>

<template>
    <Head title="Editar Recepción" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold leading-tight text-gray-800 uppercase tracking-tighter">
                    EDITAR RECEPCIÓN <span class="text-jk-red">#{{ recepcion.id }}</span>
                </h2>
                <Link :href="route('dashboard')" class="text-sm text-gray-500 hover:text-jk-blue transition-colors">
                    &larr; Volver al Panel
                </Link>
            </div>
        </template>

        <div class="py-12 bg-gray-50 min-h-screen">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                
                <div class="flex justify-center mb-8">
                    <div class="flex items-center space-x-4">
                        <button @click="currentStep = 1" :class="currentStep === 1 ? 'bg-jk-blue text-white' : 'bg-green-500 text-white'" class="w-8 h-8 rounded-full font-bold flex items-center justify-center transition-all">
                            <span v-if="currentStep === 1">1</span>
                            <span v-else>✓</span>
                        </button>
                        <span :class="currentStep === 1 ? 'text-jk-blue font-bold' : 'text-gray-500'">Datos del Cliente</span>
                        
                        <div class="w-8 h-0.5 bg-gray-300"></div>
                        
                        <button @click="currentStep = 2" :class="currentStep === 2 ? 'bg-jk-blue text-white' : 'bg-gray-200 text-gray-600'" class="w-8 h-8 rounded-full font-bold flex items-center justify-center transition-all">
                            2
                        </button>
                        <span :class="currentStep === 2 ? 'text-jk-blue font-bold' : 'text-gray-400'">Datos del Vehículo</span>
                    </div>
                </div>

                <form @submit.prevent="submit" class="bg-white shadow-xl sm:rounded-2xl overflow-hidden border-t-8 border-jk-blue">
                    <div class="p-8">

                        <div v-show="currentStep === 1" class="space-y-6 animate-fade-in">
                            <h3 class="text-lg font-black text-jk-blue uppercase border-b-2 border-gray-100 pb-2 mb-6">Datos del Cliente</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Nombre Completo *</label>
                                    <input v-model="form.first_name" type="text" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" required />
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Teléfono / WhatsApp</label>
                                    <input v-model="form.phone" type="text" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Dirección</label>
                                    <input v-model="form.address" type="text" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" />
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">RFC</label>
                                    <input v-model="form.rfc" type="text" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20 uppercase" />
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end">
                                <button type="button" @click="currentStep = 2" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-3 px-8 rounded-xl transition-all">
                                    CONTINUAR &rarr;
                                </button>
                            </div>
                        </div>

                        <div v-show="currentStep === 2" class="space-y-6 animate-fade-in">
                            <h3 class="text-lg font-black text-jk-red uppercase border-b-2 border-gray-100 pb-2 mb-6">Datos del Vehículo</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Marca *</label>
                                    <select v-model="form.brand_id" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" required>
                                        <option value="" disabled>Seleccione marca...</option>
                                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Modelo *</label>
                                    <select v-model="form.vehicle_model_id" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" :disabled="!form.brand_id" required>
                                        <option value="" disabled>Seleccione modelo...</option>
                                        <option v-for="model in availableModels" :key="model.id" :value="model.id">{{ model.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Año *</label>
                                    <input v-model="form.year" type="text" maxlength="4" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" required />
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Placa</label>
                                    <input v-model="form.plate" type="text" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20 uppercase" />
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Kilometraje</label>
                                    <input v-model="form.miles" type="number" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" />
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">VIN / Número de Serie</label>
                                    <input v-model="form.vin_serial" type="text" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20 uppercase" />
                                </div>
                            </div>

                            <div class="pt-4">
                                <label class="block text-sm font-bold text-gray-700 mb-3">Nivel de Gasolina al Ingreso *</label>
                                <div class="flex gap-2">
                                    <button 
                                        v-for="level in fuelLevels" :key="level" type="button"
                                        @click="form.fuel_level = level"
                                        :class="form.fuel_level === level ? 'bg-jk-blue text-white ring-2 ring-offset-2 ring-jk-blue' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="flex-1 py-3 rounded-xl font-bold transition-all text-sm">
                                        {{ level }}
                                    </button>
                                </div>
                            </div>

                            <div class="pt-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1">Falla Reportada / Observaciones</label>
                                <textarea v-model="form.symptoms" rows="3" class="w-full rounded-lg border-gray-300 focus:border-jk-blue focus:ring focus:ring-jk-blue/20" placeholder="Ej. El cliente reporta ruido al frenar..."></textarea>
                            </div>

                            <div class="mt-8 flex justify-between pt-6 border-t border-gray-100">
                                <button type="button" @click="currentStep = 1" class="text-gray-500 hover:text-jk-blue font-bold py-3 px-6 transition-colors">
                                    &larr; REGRESAR
                                </button>
                                <button type="submit" :disabled="form.processing" class="bg-green-600 hover:bg-green-700 text-white font-black py-3 px-8 rounded-xl transition-all shadow-lg flex items-center gap-2">
                                    <span v-if="form.processing">GUARDANDO...</span>
                                    <span v-else>💾 GUARDAR CAMBIOS</span>
                                </button>
                            </div>
                        </div>

                    </div>
                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>