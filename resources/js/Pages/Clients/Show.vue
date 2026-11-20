<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Datos ya resueltos por ClientController::show. Esta vista solo los renderiza.
const props = defineProps({
    client:    { type: Object, required: true },
    resumen:   { type: Object, required: true },
    vehiculos: { type: Array, default: () => [] },
});

const tabs = [
    { id: 'resumen',   label: 'Resumen' },
    { id: 'vehiculos', label: 'Vehículos' },
];
const activa = ref('resumen');

const nombre = () => `${props.client.first_name ?? ''} ${props.client.last_name ?? ''}`.trim();
const dato = (v) => v || '—';
</script>

<template>
    <Head :title="'Expediente - ' + nombre()" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-bold text-xl text-gray-800 leading-tight uppercase tracking-wider">
                    Expediente del cliente
                </h2>
                <Link :href="route('clients.index')"
                      class="text-sm font-bold text-gray-500 hover:text-[#10213E] min-h-[44px] inline-flex items-center">
                    ← Volver al directorio
                </Link>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Encabezado: siempre visible -->
                <div class="bg-white rounded-2xl shadow-sm border-t-4 border-[#10213E] p-5 sm:p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-black text-[#EE2857] uppercase tracking-widest">
                                Cliente {{ dato(client.client_number) }}
                            </p>
                            <h3 class="text-2xl font-black text-[#10213E] break-words">{{ nombre() }}</h3>
                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-1 text-sm text-gray-600">
                                <p><span class="text-xs font-bold text-gray-400 uppercase block">Teléfono</span>{{ dato(client.phone) }}</p>
                                <p class="break-all"><span class="text-xs font-bold text-gray-400 uppercase block">Correo</span>{{ dato(client.email) }}</p>
                                <p><span class="text-xs font-bold text-gray-400 uppercase block">RFC</span>{{ dato(client.rfc) }}</p>
                            </div>
                        </div>

                        <!-- Acciones contextuales: reutilizan rutas existentes -->
                        <div class="flex flex-col gap-2 sm:flex-row md:flex-col lg:flex-row shrink-0">
                            <Link :href="route('recepcion.create', { client_id: client.id })"
                                  class="inline-flex items-center justify-center min-h-[44px] bg-[#EE2857] hover:bg-red-700 text-white font-black text-xs uppercase px-4 py-2 rounded-xl transition shadow-sm">
                                ➕ Nueva recepción
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Pestañas -->
                <div class="flex gap-2 overflow-x-auto border-b border-gray-200">
                    <button v-for="p in tabs" :key="p.id" type="button"
                            @click="activa = p.id"
                            class="min-h-[44px] px-4 text-sm font-black uppercase tracking-wider whitespace-nowrap border-b-2 transition"
                            :class="activa === p.id
                                ? 'border-[#EE2857] text-[#10213E]'
                                : 'border-transparent text-gray-400 hover:text-gray-600'">
                        {{ p.label }}
                    </button>
                </div>

                <!-- Resumen -->
                <div v-if="activa === 'resumen'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 lg:col-span-2">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3">Datos generales</h4>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="text-xs font-bold text-gray-400 uppercase">Dirección</dt><dd class="text-gray-800">{{ dato(client.address) }}</dd></div>
                            <div><dt class="text-xs font-bold text-gray-400 uppercase">País</dt><dd class="text-gray-800">{{ dato(client.country) }}</dd></div>
                        </dl>

                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mt-6 mb-3">Datos fiscales</h4>
                        <ul v-if="client.fiscal_profiles.length" class="space-y-2 text-sm">
                            <li v-for="f in client.fiscal_profiles" :key="f.id" class="bg-gray-50 rounded-lg px-3 py-2">
                                <span class="font-black text-[#10213E]">{{ f.rfc }}</span>
                                <span class="text-gray-500"> · {{ dato(f.razon_social) }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-400 italic">Sin perfiles fiscales registrados.</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Resumen</h4>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Vehículos</p>
                            <p class="text-2xl font-black text-[#10213E]">{{ resumen.vehiculos }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Órdenes</p>
                            <p class="text-2xl font-black text-[#10213E]">{{ resumen.ordenes }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Última visita</p>
                            <p class="text-base font-bold text-gray-800">{{ dato(resumen.ultima_visita) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Vehículos: tarjetas en móvil y tablet, legibles en escritorio -->
                <div v-else-if="activa === 'vehiculos'">
                    <div v-if="vehiculos.length === 0"
                         class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-400 italic">
                        Este cliente no tiene vehículos registrados.
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="v in vehiculos" :key="v.id"
                             class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-black text-[#10213E] break-words">{{ dato(v.marca) }} {{ dato(v.modelo) }}</p>
                                    <p class="text-xs text-gray-400">{{ dato(v.year) }}</p>
                                </div>
                                <span class="font-mono font-bold uppercase text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                    {{ dato(v.plate) }}
                                </span>
                            </div>

                            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div><dt class="text-xs font-bold text-gray-400 uppercase">VIN</dt><dd class="font-mono text-xs break-all">{{ dato(v.vin) }}</dd></div>
                                <div><dt class="text-xs font-bold text-gray-400 uppercase">Color</dt><dd>{{ dato(v.color) }}</dd></div>
                                <div><dt class="text-xs font-bold text-gray-400 uppercase">Kilometraje</dt><dd>{{ v.kilometraje != null ? Number(v.kilometraje).toLocaleString('es-MX') + ' km' : '—' }}</dd></div>
                                <div><dt class="text-xs font-bold text-gray-400 uppercase">Visitas</dt><dd>{{ v.visitas }}</dd></div>
                                <div class="col-span-2"><dt class="text-xs font-bold text-gray-400 uppercase">Última visita</dt><dd>{{ dato(v.ultima_visita) }}</dd></div>
                            </dl>

                            <div class="flex flex-col sm:flex-row gap-2 pt-2 border-t border-gray-100">
                                <Link :href="route('vehicles.history', v.id)"
                                      class="inline-flex items-center justify-center min-h-[44px] flex-1 bg-gray-100 hover:bg-gray-200 text-[#10213E] font-black text-xs uppercase px-4 py-2 rounded-xl transition">
                                    🕘 Historial
                                </Link>
                                <Link :href="route('recepcion.create', { client_id: client.id, vehicle_id: v.id })"
                                      class="inline-flex items-center justify-center min-h-[44px] flex-1 bg-[#10213E] hover:bg-blue-900 text-white font-black text-xs uppercase px-4 py-2 rounded-xl transition">
                                    ➕ Nueva visita
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
