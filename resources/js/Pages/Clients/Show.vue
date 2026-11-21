<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import CotizacionesResumen from '@/Components/Clients/CotizacionesResumen.vue';

// Datos ya resueltos por ClientController::show. Esta vista solo los renderiza.
const props = defineProps({
    client:    { type: Object, required: true },
    resumen:   { type: Object, required: true },
    vehiculos: { type: Array, default: () => [] },
    resumen_financiero: { type: Object, default: () => ({}) },
    servicios: { type: Object, default: null },
});

const tabs = [
    { id: 'resumen',   label: 'Resumen' },
    { id: 'vehiculos', label: 'Vehículos' },
    { id: 'servicios', label: 'Servicios' },
];
const activa = ref('resumen');

const nombre = () => `${props.client.first_name ?? ''} ${props.client.last_name ?? ''}`.trim();
const dato = (v) => v || '—';
// Formateo de presentación: el backend ya entrega los montos; aquí solo se muestran.
const mxn = (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0));
const tarjetasFinancieras = [
    { clave: 'total_autorizado',       etiqueta: 'Autorizado',          nota: 'Cotizaciones aprobadas' },
    { clave: 'total_pagado',           etiqueta: 'Pagado',              nota: 'Todos los pagos del cliente' },
    { clave: 'saldo_autorizado',       etiqueta: 'Saldo por cobrar',    nota: 'Suma por orden, sin compensar' },
    { clave: 'pendiente_autorizacion', etiqueta: 'Pendiente de autorizar', nota: 'Trabajo sin cotización aprobada' },
    { clave: 'excedente_registrado',   etiqueta: 'Excedente',           nota: 'Pagado sobre lo autorizado; no es saldo a favor' },
];
const vehiculoTexto = (v) => {
    if (!v) return 'Vehículo no registrado';
    const auto = [v.marca, v.modelo, v.year].filter(Boolean).join(' ');
    return [auto, v.plate].filter(Boolean).join(' · ') || 'Vehículo no registrado';
};
// Solo resalta visualmente; el monto lo calcula el backend.
const pendienteClase = (v) => Number(v) > 0 ? 'text-[#EE2857] font-black' : 'text-gray-400';
const abiertas = ref({});
const alternarCotizaciones = (id) => { abiertas.value[id] = !abiertas.value[id]; };
const irPagina = (url) => {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true, only: ['servicios'] });
};

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

                <!-- Resumen financiero: montos ya calculados por el backend (EstadosFinancierosOrdenesAction) -->
                <div v-if="activa === 'resumen'" class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <div v-for="t in tarjetasFinancieras" :key="t.clave"
                         class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 min-w-0">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t.etiqueta }}</p>
                        <p class="text-lg sm:text-xl font-black text-[#10213E] break-words">{{ mxn(resumen_financiero[t.clave]) }}</p>
                        <p class="text-[11px] text-gray-400 mt-1 leading-snug">{{ t.nota }}</p>
                    </div>
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

            <!-- Servicios: órdenes del cliente (página actual). Estado financiero calculado en lote en el backend -->
            <div v-else-if="activa === 'servicios'" class="space-y-4">
                <div v-if="!servicios || servicios.data.length === 0"
                     class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center space-y-4">
                    <p class="text-gray-400 italic">Este cliente todavía no tiene servicios registrados.</p>
                    <Link :href="route('recepcion.create', { client_id: client.id })"
                          class="inline-flex items-center justify-center min-h-[44px] bg-[#EE2857] hover:bg-red-700 text-white font-black text-xs uppercase px-4 py-2 rounded-xl transition shadow-sm">
                        ➕ Nueva recepción
                    </Link>
                </div>

                <template v-else>
                    <!-- Escritorio: tabla -->
                    <div class="hidden lg:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-[11px] font-black text-gray-400 uppercase tracking-wider">
                                <tr>
                                    <th class="text-left px-4 py-3">Servicio</th>
                                    <th class="text-left px-4 py-3">Vehículo</th>
                                    <th class="text-left px-4 py-3">Fecha</th>
                                    <th class="text-left px-4 py-3">Estado</th>
                                    <th class="text-left px-4 py-3">Mecánico</th>
                                    <th class="text-right px-4 py-3">Técnico</th>
                                    <th class="text-right px-4 py-3">Autorizado</th>
                                    <th class="text-right px-4 py-3">Pagado</th>
                                    <th class="text-right px-4 py-3">Saldo</th>
                                    <th class="text-right px-4 py-3">Pendiente</th>
                                    <th class="px-4 py-3"><span class="sr-only">Acción</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template v-for="s in servicios.data" :key="s.id">
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="font-black text-[#10213E]">{{ dato(s.folio) }}</p>
                                        <button v-if="s.cotizaciones.length" type="button" @click="alternarCotizaciones(s.id)"
                                                class="mt-1 min-h-[44px] inline-flex items-center text-xs font-bold text-[#10213E] hover:text-[#EE2857]">
                                            {{ abiertas[s.id] ? 'Ocultar' : 'Ver' }} cotizaciones ({{ s.cotizaciones.length }})
                                        </button>
                                        <p v-else class="mt-1 text-xs text-gray-400 italic">Sin cotizaciones</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ vehiculoTexto(s.vehiculo) }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ dato(s.fecha) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap"
                                              :class="s.estado?.color_class ?? 'bg-gray-100 text-gray-600'">
                                            {{ s.estado ? $t('status.' + s.estado.slug) : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ s.mecanico ?? 'Sin asignar' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ mxn(s.financiero.total_tecnico) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ mxn(s.financiero.total_autorizado) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ mxn(s.financiero.total_pagado) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-black text-[#10213E]">{{ mxn(s.financiero.saldo_autorizado) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums" :class="pendienteClase(s.financiero.pendiente_autorizacion)">{{ mxn(s.financiero.pendiente_autorizacion) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <Link :href="route('repair-orders.show', s.id)"
                                              class="inline-flex items-center min-h-[44px] text-xs font-black uppercase text-[#10213E] hover:text-[#EE2857] whitespace-nowrap">
                                            Abrir orden →
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="abiertas[s.id] && s.cotizaciones.length" class="bg-gray-50/70">
                                    <td colspan="11" class="px-4 py-3">
                                        <CotizacionesResumen :cotizaciones="s.cotizaciones" />
                                    </td>
                                </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tablet y móvil: tarjetas por servicio -->
                    <div class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div v-for="s in servicios.data" :key="s.id"
                             class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col gap-3 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-black text-[#10213E] break-words">{{ dato(s.folio) }}</p>
                                    <p class="text-sm text-gray-600 break-words">{{ vehiculoTexto(s.vehiculo) }}</p>
                                </div>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full shrink-0"
                                      :class="s.estado?.color_class ?? 'bg-gray-100 text-gray-600'">
                                    {{ s.estado ? $t('status.' + s.estado.slug) : '—' }}
                                </span>
                            </div>

                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase">Saldo por cobrar</p>
                                <p class="text-xl font-black text-[#10213E] tabular-nums">{{ mxn(s.financiero.saldo_autorizado) }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase">Pendiente de autorizar</p>
                                <p class="text-base font-black tabular-nums" :class="pendienteClase(s.financiero.pendiente_autorizacion)">{{ mxn(s.financiero.pendiente_autorizacion) }}</p>
                            </div>

                            <dl class="grid grid-cols-3 gap-x-3 text-sm">
                                <div><dt class="text-xs font-bold text-gray-400 uppercase">Técnico</dt><dd class="tabular-nums">{{ mxn(s.financiero.total_tecnico) }}</dd></div>
                                <div><dt class="text-xs font-bold text-gray-400 uppercase">Autorizado</dt><dd class="tabular-nums">{{ mxn(s.financiero.total_autorizado) }}</dd></div>
                                <div><dt class="text-xs font-bold text-gray-400 uppercase">Pagado</dt><dd class="tabular-nums">{{ mxn(s.financiero.total_pagado) }}</dd></div>
                            </dl>

                            <p class="text-xs text-gray-500">{{ s.mecanico ?? 'Sin asignar' }} · {{ dato(s.fecha) }}</p>

                            <div>
                                <button v-if="s.cotizaciones.length" type="button" @click="alternarCotizaciones(s.id)"
                                        class="min-h-[44px] inline-flex items-center text-xs font-bold text-[#10213E] hover:text-[#EE2857]">
                                    {{ abiertas[s.id] ? 'Ocultar' : 'Ver' }} cotizaciones ({{ s.cotizaciones.length }})
                                </button>
                                <p v-else class="text-xs text-gray-400 italic">Sin cotizaciones</p>
                                <CotizacionesResumen v-if="abiertas[s.id]" :cotizaciones="s.cotizaciones" class="mt-2" />
                            </div>

                            <Link :href="route('repair-orders.show', s.id)"
                                  class="inline-flex items-center justify-center min-h-[44px] bg-[#10213E] hover:bg-blue-900 text-white font-black text-xs uppercase px-4 py-2 rounded-xl transition">
                                Abrir orden →
                            </Link>
                        </div>
                    </div>

                    <!-- Paginación: conserva cliente, pestaña y query string -->
                    <nav class="flex items-center justify-between gap-3 text-sm" aria-label="Paginación de servicios">
                        <button type="button" :disabled="!servicios.prev_page_url"
                                @click="irPagina(servicios.prev_page_url)"
                                class="min-h-[44px] px-4 rounded-xl bg-white border border-gray-200 font-bold text-[#10213E] disabled:opacity-40 disabled:cursor-not-allowed">
                            ← Anterior
                        </button>
                        <span class="text-xs font-bold text-gray-500 uppercase">Página {{ servicios.current_page }} de {{ servicios.last_page }}</span>
                        <button type="button" :disabled="!servicios.next_page_url"
                                @click="irPagina(servicios.next_page_url)"
                                class="min-h-[44px] px-4 rounded-xl bg-white border border-gray-200 font-bold text-[#10213E] disabled:opacity-40 disabled:cursor-not-allowed">
                            Siguiente →
                        </button>
                    </nav>
                </template>
            </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
