<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3'; // 👈 IMPORTANTE: Agregamos Link aquí

const form = useForm({
    customer_name: '',
    vehicle_details: '',
    vin_serial: '',
    fuel_level: '1/2',
    symptoms: '',
});

const fuelOptions = [
    { label: 'E', value: '0', color: 'bg-red-500' },
    { label: '1/4', value: '1/4', color: 'bg-orange-500' },
    { label: '1/2', value: '1/2', color: 'bg-yellow-500' },
    { label: '3/4', value: '3/4', color: 'bg-green-400' },
    { label: 'F', value: '1', color: 'bg-green-600' },
];

const submit = () => {
    form.post(route('recepcion.store'));
};
</script>

<template>
    <Head title="Nueva Recepción" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-xl text-gray-800 uppercase tracking-tighter">
                    Registrar Entrada de Vehículo
                </h2>
                <Link 
                    :href="route('dashboard')" 
                    class="text-xs font-bold text-gray-400 hover:text-jk-red transition-colors flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    VOLVER AL PANEL
                </Link>
            </div>
        </template>

        <div class="py-12 bg-gray-50 min-h-screen">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow-2xl rounded-3xl overflow-hidden border border-gray-100">
                    
                    <div class="p-8 space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <h3 class="text-sm font-black text-jk-blue uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span class="w-2 h-4 bg-jk-blue inline-block"></span> Información General
                                </h3>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nombre del Cliente</label>
                                <input v-model="form.customer_name" type="text" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm" placeholder="Ej. Juan Pérez">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Vehículo (Marca/Modelo)</label>
                                <input v-model="form.vehicle_details" type="text" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm" placeholder="Ej. Toyota Tacoma 2022">
                            </div>
                        </div>

                        <div class="bg-gray-50 p-6 rounded-3xl border border-gray-100">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-6 text-center">Nivel de Gasolina al Ingreso</label>
                            <div class="relative flex justify-between items-center max-w-md mx-auto">
                                <div class="absolute h-1 w-full bg-gray-200 rounded-full"></div>
                                <div v-for="option in fuelOptions" :key="option.value" class="z-10 flex flex-col items-center">
                                    <button 
                                        type="button"
                                        @click="form.fuel_level = option.value"
                                        :class="[
                                            'w-8 h-8 rounded-full border-4 transition-all duration-300',
                                            form.fuel_level === option.value 
                                                ? `${option.color} border-white ring-4 ring-jk-blue/20 scale-125` 
                                                : 'bg-white border-gray-200 hover:border-gray-300'
                                        ]"
                                    ></button>
                                    <span :class="['text-[10px] mt-2 font-black', form.fuel_level === option.value ? 'text-gray-900' : 'text-gray-400']">
                                        {{ option.label }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Falla Reportada / Observaciones</label>
                            <textarea v-model="form.symptoms" rows="4" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm" placeholder="Describa el problema detalladamente..."></textarea>
                        </div>
                    </div>

                    <div class="p-8 bg-gray-50 border-t border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                        <Link 
                            :href="route('dashboard')" 
                            class="w-full md:w-auto text-center px-8 py-4 border-2 border-gray-200 text-gray-400 font-black uppercase tracking-widest text-[10px] rounded-2xl hover:bg-white hover:text-jk-red hover:border-jk-red transition-all active:scale-95"
                        >
                            ❌ Cancelar Registro
                        </Link>

                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="w-full md:w-auto bg-jk-blue text-white px-12 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl hover:opacity-90 transition-all active:scale-95 disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ form.processing ? 'Procesando...' : 'Finalizar Recepción' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>