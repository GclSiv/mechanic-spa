<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ recepcion: Object });

const witnesses = {
    engine: '🔴 Motor', abs: '🔴 ABS', oil: '🔴 Aceite',
    battery: '🔴 Batería', temp: '🔴 Temperatura',
};
</script>

<template>
    <AuthenticatedLayout title="Detalle de Recepción">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                    🚗 Recepción #{{ recepcion.id }}
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('recepcion.edit', recepcion.id)"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs uppercase px-4 py-2 rounded-lg transition">
                        ✏️ Editar
                    </Link>
                    <a :href="route('recepcion.pdf', recepcion.id)" target="_blank"
                        class="bg-[#10213E] hover:bg-blue-900 text-white font-bold text-xs uppercase px-4 py-2 rounded-lg transition">
                        🖨️ Imprimir PDF
                    </a>
                    <Link :href="route('recepcion.generate-order', recepcion.id)"
                        method="post" as="button"
                        class="bg-[#EE2857] hover:bg-red-700 text-white font-black text-xs uppercase px-4 py-2 rounded-lg transition shadow">
                        📋 Generar Cotización
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <!-- Cliente -->
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#10213E] p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">👤 Cliente</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Nombre</span>
                            {{ recepcion.client?.first_name }} {{ recepcion.client?.last_name }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Teléfono</span>
                            {{ recepcion.client?.phone ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">RFC</span>
                            {{ recepcion.client?.rfc ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Dirección</span>
                            {{ recepcion.client?.address ?? '—' }}</div>
                    </div>
                </div>

                <!-- Vehículo -->
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-400 p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">🚗 Vehículo</h3>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Marca</span>
                            {{ recepcion.vehicle?.brand?.name ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Modelo</span>
                            {{ recepcion.vehicle?.vehicleModel?.name ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Año</span>
                            {{ recepcion.vehicle?.year ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Placa</span>
                            <span class="font-bold uppercase">{{ recepcion.vehicle?.plate ?? '—' }}</span></div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">VIN</span>
                            {{ recepcion.vehicle?.vin ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Motor</span>
                            {{ recepcion.vehicle?.engine ?? '—' }}</div>
                    </div>
                </div>

                <!-- Inspección -->
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-yellow-400 p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">🔍 Inspección de Entrada</h3>
                    <div class="grid grid-cols-3 gap-4 text-sm mb-4">
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Kilometraje</span>
                            {{ recepcion.miles ? Number(recepcion.miles).toLocaleString('es-MX') + ' km' : '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Gasolina</span>
                            {{ recepcion.fuel_level ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">Fecha ingreso</span>
                            {{ new Date(recepcion.created_at).toLocaleDateString('es-MX') }}</div>
                    </div>
                    <div v-if="recepcion.witnesses?.length">
                        <span class="text-xs text-gray-400 uppercase font-bold block mb-2">Testigos del tablero</span>
                        <div class="flex gap-2 flex-wrap">
                            <span v-for="w in recepcion.witnesses" :key="w"
                                class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-lg">
                                {{ witnesses[w] ?? w }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-xs text-gray-400 uppercase font-bold block mb-1">Síntomas reportados</span>
                        <p class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3">{{ recepcion.symptoms ?? 'Sin descripción' }}</p>
                    </div>
                </div>

                <!-- Fotos -->
                <div v-if="recepcion.photos?.length" class="bg-white rounded-xl shadow-sm border-l-4 border-green-400 p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">📷 Evidencia Fotográfica</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <a v-for="(photo, i) in recepcion.photos" :key="i"
                            :href="'/storage/' + photo" target="_blank"
                            class="aspect-square rounded-xl overflow-hidden border border-gray-200 hover:opacity-80 transition">
                            <img :src="'/storage/' + photo" class="w-full h-full object-cover" :alt="'Foto ' + (i+1)" />
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
