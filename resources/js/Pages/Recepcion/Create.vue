<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3'; // 👈 IMPORTANTE: Agregamos Link aquí
import IconBattery from '@/Components/Icons/IconBattery.vue';
import IconTemp from '@/Components/Icons/IconTemp.vue';
import IconAbs from '@/Components/Icons/IconAbs.vue';
import IconEngine from '@/Components/Icons/IconEngine.vue';
import IconOil from '@/Components/Icons/IconOil.vue';

// 1. DEFINIMOS LAS PROPS: Aquí recibimos lo que envía el controlador
const props = defineProps({
    brands: Array // Recibimos el arreglo de marcas
});

const form = useForm({

    first_name: '',
    miles: '',
    customer_name: '',
    vehicle_details: '',
    vin_serial: '',
    fuel_level: '1/2',
    technical_symptoms: '',
    brand_id: '',
    vehicle_model_id: '',
    // Nuevos campos
    witnesses: [], // Guardaremos los IDs de los testigos prendidos
    inventory: [], // Guardaremos los artículos que trae el auto
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

// Actualizamos las opciones de testigos
const witnessOptions = [
    { id: 'engine', name: 'Engine', component: IconEngine },
    { id: 'abs', name: 'ABS', component: IconAbs },
    { id: 'oil', name: 'Aceite', component: IconOil },
    { id: 'battery', name: 'Batería', component: IconBattery },
    { id: 'temp', name: 'Temp', component: IconTemp },
];

// Función para prender/apagar testigos
const toggleWitness = (id) => {
    const index = form.witnesses.indexOf(id);
    if (index > -1) {
        form.witnesses.splice(index, 1);
    } else {
        form.witnesses.push(id);
    }
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
                    
                    <div class="p-8 space-y-6 bg-gray-50/50 border-b border-gray-100">
                        <h3 class="text-sm font-black text-jk-blue uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-4 bg-jk-blue inline-block"></span> Datos del Cliente
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nombre Completo</label>
                                <input v-model="form.first_name" type="text" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm" placeholder="Ej. Gilberto Lara">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Teléfono / WhatsApp</label>
                                <input v-model="form.phone" type="tel" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm" placeholder="Ej. 951 000 0000">
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-8 space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Marca del Vehículo</label>
                                <select v-model="form.brand_id" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm text-sm">
                                    <option value="" disabled>Seleccione una marca</option>
                                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Modelo</label>
                                <input v-model="form.vehicle_model_id" type="text" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm" placeholder="ID del modelo">
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

                        <div class="p-8 space-y-6 bg-white rounded-3xl border border-gray-100 shadow-sm">
                            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                <span class="w-1.5 h-3 bg-jk-red inline-block"></span> Testigos del Tablero
                            </h3>
                            
                            <div class="grid grid-cols-5 gap-4">
                                <button 
                                    v-for="opt in witnessOptions" 
                                    :key="opt.id"
                                    type="button"
                                    @click="toggleWitness(opt.id)"
                                    class="flex flex-col items-center group focus:outline-none"
                                >
                                    <div 
                                        :class="[
                                            'transition-all duration-300 transform w-14 h-14 p-2 flex items-center justify-center rounded-2xl',
                                            form.witnesses.includes(opt.id) 
                                                ? 'text-red-600 bg-red-50 scale-110 shadow-inner icon-glow' 
                                                : 'text-gray-300 hover:bg-gray-50 hover:text-gray-400'
                                        ]"
                                    >
                                        <component :is="opt.component" class="w-full h-full" />
                                    </div>
                                    <span 
                                        :class="form.witnesses.includes(opt.id) ? 'text-red-700 font-black' : 'text-gray-400 font-bold'"
                                        class="text-[9px] uppercase mt-3 tracking-tighter transition-colors"
                                    >
                                        {{ opt.name }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Falla Reportada / Observaciones</label>
                            <textarea v-model="form.symptoms" rows="4" class="w-full border-gray-200 rounded-2xl focus:ring-jk-blue shadow-sm" placeholder="Describa el problema detalladamente..."></textarea>
                        </div>
                    </div>

                    <div class="p-8 bg-gray-50 border-t border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                        <Link :href="route('dashboard')" class="w-full md:w-auto text-center px-8 py-4 border-2 border-gray-200 text-gray-400 font-black uppercase tracking-widest text-[10px] rounded-2xl hover:bg-white hover:text-jk-red hover:border-jk-red transition-all">
                            ❌ Cancelar Registro
                        </Link>
                        <button type="submit" :disabled="form.processing" class="w-full md:w-auto bg-jk-blue text-white px-12 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl hover:opacity-90 transition-all disabled:opacity-50">
                            <span>{{ form.processing ? 'Procesando...' : 'Finalizar Recepción' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Evita que los iconos crezcan más allá de su contenedor de 14x14 */
:deep(svg) {
    width: 100% !important;
    height: 100% !important;
    max-width: 45px; 
    max-height: 45px;
    display: block;
    margin: 0 auto;
}

/* Efecto de luz roja intensa para testigos encendidos */
.icon-glow {
    filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
}

button:active .transform {
    transform: scale(0.95);
}
</style>




<style scoped>
/* Efecto de luz para cuando el testigo está encendido */
.icon-glow {
    filter: drop-shadow(0 0 8px rgba(220, 38, 38, 0.8));
}

/* Para que el icono ocupe todo su contenedor */
svg {
    width: 100%;
    height: 100%;
}
</style>