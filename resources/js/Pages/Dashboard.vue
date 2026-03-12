<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

// 1. Recibimos los datos del controlador
const props = defineProps({
    stats: Object,
    recentClients: Array,
    filters: Object,
});

// 2. Variables de estado
const isSearching = ref(false); 
const search = ref(props.filters.search || '');
let timeout = null;

// 3. Vigilante con Debounce y Spinner de Carrito
watch(search, (value) => {
    isSearching.value = true; // Arranca el motor del carrito
    
    clearTimeout(timeout);

    timeout = setTimeout(() => {
        router.get(route('dashboard'), { search: value }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false; // Se detiene al llegar los datos
            }
        });
    }, 500); // Medio segundo de espera
});
</script>

<template>
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-gray-800 uppercase tracking-tighter">
                Panel Principal <span class="text-jk-blue">JK</span><span class="text-jk-red">Automotive</span>
            </h2>
        </template>
       

        <div class="py-12 bg-gray-50">
            
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white shadow-sm sm:rounded-xl border-l-8 border-jk-blue p-6">
                        <p class="text-sm font-bold text-gray-500 uppercase">Total Vehículos</p>
                        <p class="text-4xl font-black text-gray-900">{{ stats.total }}</p>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-xl border-l-8 border-jk-red p-6">
                        <p class="text-sm font-bold text-gray-500 uppercase">Ingresos de Hoy</p>
                        <p class="text-4xl font-black text-gray-900">{{ stats.today }}</p>
                    </div>
<Link 
    href="/recepcion/create" 
    class="bg-jk-blue flex items-center justify-center gap-3 px-8 py-10 text-white font-black rounded-3xl shadow-xl transition-all hover:scale-105 active:scale-95 cursor-pointer"
>
    <span class="text-2xl">+</span>
    NUEVA RECEPCIÓN
</Link>

 
                </div>

                <div class="bg-white shadow-sm sm:rounded-xl overflow-hidden">
                    <div class="p-8 text-gray-900">
                        
                        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <h3 class="text-lg font-bold flex items-center gap-2 uppercase tracking-wider">
                                <span class="w-2 h-7 bg-jk-red inline-block rounded-full"></span>
                                Últimos Vehículos Registrados
                            </h3>
                            
                           <div class="relative w-full md:w-80">
    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
        <svg v-if="!isSearching" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>

        <div v-else class="flex items-center">
            <svg class="w-6 h-6 text-jk-red animate-vroom" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
            </svg>
            <div class="flex flex-col gap-0.5 ml-1">
                <div class="w-2 h-0.5 bg-jk-red animate-dash"></div>
                <div class="w-3 h-0.5 bg-jk-red animate-dash-slow"></div>
            </div>
        </div>
    </span>

    <input 
        v-model="search"
        type="text" 
        placeholder="Buscar cliente o placa..." 
        class="w-full pl-14 pr-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-jk-blue/20 focus:border-jk-blue outline-none transition-all text-sm shadow-sm"
    />
</div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase font-bold">
                                    <tr>
                                        <th class="p-4">Cliente</th>
                                        <th class="p-4">Vehículo</th>
                                        <th class="p-4">Serie</th>
                                        <th class="p-4 text-center">Fecha</th>
                                        <th class="p-4 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="client in recentClients" :key="client.id" class="hover:bg-blue-50/50 transition">
                                        <td class="p-4 font-bold text-jk-blue">{{ client.first_name }}</td>
                                        <td class="p-4">
                                            <span class="bg-gray-100 px-2 py-1 rounded text-xs font-mono text-gray-700">
                                                {{ client.brand?.name || 'S/M' }} / {{ client.vehicle_model?.name || 'S/M' }}
                                            </span>
                                        </td>
                                        <td class="p-4">
                                            <span class="bg-gray-100 px-2 py-1 rounded text-xs font-mono text-gray-600">
                                                {{ client.vin || 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-center text-sm text-gray-500">
                                            {{ new Date(client.created_at).toLocaleDateString() }}
                                        </td>
                                        <td class="p-4 text-right">
                                            <a 
    :href="route('recepcion.print', client.id)" 
    target="_blank"
    class="bg-gray-100 hover:bg-jk-red hover:text-white px-4 py-1 rounded-full text-xs font-bold transition inline-block text-center"
>
    IMPRIMIR NOTA
</a>
                                        </td>
                                    </tr>
                                    <tr v-if="recentClients.length === 0">
                                        <td colspan="5" class="p-10 text-center text-gray-400">
                                            No se encontraron vehículos con esa búsqueda.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Quitamos los colores fosforitos/fijos para que hereden del Layout */

/* Animación del Carrito (Vibración de motor) */
.animate-vroom {
    animation: vroom 0.15s infinite alternate;
}

/* Animación de líneas de velocidad */
.animate-dash {
    animation: dash 0.3s infinite linear;
}

.animate-dash-slow {
    animation: dash 0.5s infinite linear;
}

@keyframes vroom {
    from { transform: translateY(0); }
    to { transform: translateY(-2px); }
}

@keyframes dash {
    0% { opacity: 0; transform: translateX(4px); }
    50% { opacity: 1; }
    100% { opacity: 0; transform: translateX(-8px); }
}
</style>