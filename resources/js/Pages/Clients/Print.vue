<script setup>
import { Head,Link } from '@inertiajs/vue3';

import { onMounted } from 'vue';

const props = defineProps({
    client: Object,
    setting: Object
});

// Función para imprimir automáticamente al cargar (opcional)
const imprimirPagina = () => {
    window.print();
};
// onMounted(() => { window.print(); });
const volver = () => {
    window.history.back();
};
</script>

<template>
    <Head :title="'Nota de Recepción - ' + client.first_name" />

    <div class="min-h-screen bg-white p-4 md:p-10 font-sans text-gray-900">
        <div class="mb-6 no-print flex justify-between items-center bg-gray-100 p-4 rounded-lg">
    <Link 
        :href="route('dashboard')" 
        class="text-jk-blue font-bold flex items-center gap-2 hover:underline"
    >
        ← Volver al Panel
    </Link>

    <button 
        @click="imprimirPagina" 
        class="bg-jk-blue text-white px-6 py-2 rounded-lg font-bold shadow-md hover:bg-blue-900 transition"
    >
        🖨️ IMPRIMIR NOTA
    </button>
</div>

        <div class="max-w-4xl mx-auto border-2 border-gray-200 p-8 rounded-sm shadow-sm print:border-0 print:shadow-none">
            
            <div class="flex justify-between items-start mb-10 border-b-2 border-jk-red pb-6">
                <div class="w-1/3">
                    <img src="/images/logo-taller.svg" alt="Logo" class="h-24 w-auto mb-2" />
                </div>
                <div class="w-2/3 text-right">
                    <h1 class="text-2xl font-black text-jk-blue uppercase">{{ setting.company_name }}</h1>
                    <p class="text-sm text-gray-600 italic">{{ setting.address }}</p>
                    <p class="text-sm font-bold">Tel: {{ setting.phone }} | Lic: #{{ setting.license_number }}</p>
                    <p class="text-sm">{{ setting.email }}</p>
                    <div class="mt-4 inline-block bg-gray-100 px-4 py-2 rounded">
                        <span class="text-xs uppercase font-bold text-gray-500">Folio de Recepción:</span>
                        <span class="text-xl font-black text-jk-red ml-2">#{{ client.id.toString().padStart(5, '0') }}</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-10 mb-8">
                <div class="space-y-2">
                    <h3 class="bg-jk-blue text-white px-3 py-1 text-xs font-bold uppercase tracking-widest">Información del Cliente</h3>
                    <div class="text-sm px-1">
                        <p><span class="font-bold">Nombre:</span> {{ client.first_name }}</p>
                        <p><span class="font-bold">Teléfono:</span> {{ client.phone }}</p>
                        <p><span class="font-bold">Dirección:</span> {{ client.address || 'N/A' }}</p>
                        <p><span class="font-bold">RFC:</span> {{ client.rfc || 'N/A' }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <h3 class="bg-jk-red text-white px-3 py-1 text-xs font-bold uppercase tracking-widest">Información del Vehículo</h3>
                    <div class="text-sm px-1 grid grid-cols-2 gap-x-2">
                        <p><span class="font-bold">Marca:</span> {{ client.brand?.name }}</p>
                        <p><span class="font-bold">Modelo:</span> {{ client.vehicle_model?.name }}</p>
                        <p><span class="font-bold">Año:</span> {{ client.year }}</p>
                        <p><span class="font-bold">Placas:</span> {{ client.plate }}</p>
                        <p><span class="font-bold">Motor:</span> {{ client.engine || 'N/A' }}</p>
                        <p><span class="font-bold">Millas/Km:</span> {{ client.miles || 'N/A' }}</p>
                        <p class="col-span-2"><span class="font-bold">VIN:</span> {{ client.vin || 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-10 space-y-2">
                <h3 class="border-b-2 border-gray-800 text-sm font-bold uppercase pb-1">Descripción del Servicio / Falla Reportada</h3>
                <div class="min-h-[150px] p-4 bg-gray-50 rounded italic text-gray-700">
                    {{ client.description || 'No se proporcionó descripción.' }}
                </div>
            </div>

            <div class="mt-12 pt-6 border-t border-gray-200">
                <p class="text-[9px] text-gray-500 leading-tight mb-8 text-justify">
                    {{ setting.clauses }}
                </p>
                
                <div class="flex justify-around items-end mt-16">
                    <div class="w-1/3 text-center">
                        <div class="border-b border-gray-900 mb-2"></div>
                        <p class="text-[10px] font-bold uppercase">Firma del Cliente</p>
                    </div>
                    <div class="w-1/3 text-center">
                        <div class="border-b border-gray-900 mb-2"></div>
                        <p class="text-[10px] font-bold uppercase">Autorizado por: {{ setting.company_name }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* COLORES JK */
.text-jk-blue { color: #003366; }
.bg-jk-blue { background-color: #003366; }
.text-jk-red { color: #cc0000; }
.border-jk-red { border-color: #cc0000; }

/* REGLAS DE IMPRESIÓN */
@media print {
    .no-print { display: none !important; }
    body { background-color: white !important; }
    .shadow-sm { box-shadow: none !important; }
}
</style>