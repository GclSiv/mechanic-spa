<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const page = usePage();

const props = defineProps({ recepcion: Object });
const { t } = useI18n();

function getLang() { return localStorage.getItem('jk_locale') ?? 'es'; }

// Deja el teléfono en formato WhatsApp de México: 52 + 10 dígitos
function normalizarTelefono(tel) {
    const d = (tel ?? '').replace(/\D/g, '');
    if (!d) return null;
    if (d.length === 10) return '52' + d;
    if (d.length === 13 && d.startsWith('521')) return '52' + d.slice(3);
    return d;
}

const whatsappUrl = computed(() => {
    const telefono = normalizarTelefono(props.recepcion.client?.phone);
    if (!telefono) return null;
    const taller = page.props.settings?.company_name || 'nuestro taller';
    const cliente = props.recepcion.client;
    const vehiculo = props.recepcion.vehicle;
    const marca = vehiculo?.brand?.name ?? vehiculo?.brand ?? '';
    const modelo = vehiculo?.vehicle_model?.name ?? vehiculo?.vehicleModel?.name ?? vehiculo?.model ?? '';
    const texto =
        `Hola ${cliente?.first_name ?? ''}, le escribimos de ${taller}. ` +
        `Su vehículo ${marca} ${modelo} ${vehiculo?.year ?? ''} quedó registrado en recepción #${String(props.recepcion.id).padStart(4, '0')}` +
        (cliente?.client_number ? ` (cliente ${cliente.client_number})` : '') +
        '. Cualquier duda, con gusto le atendemos.';
    return `https://wa.me/${telefono}?text=${encodeURIComponent(texto)}`;
});
</script>

<template>
    <AuthenticatedLayout :title="$t('recepcion.detalleTitulo')">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                    🚗 {{ $t('recepcion.detalleTitulo') }} #{{ recepcion.id }}
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('recepcion.edit', recepcion.id)"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs uppercase px-4 py-2 rounded-lg transition">
                        ✏️ {{ $t('acciones.editar') }}
                    </Link>
                    <a :href="route('recepcion.pdf', recepcion.id) + '?lang=' + getLang()" target="_blank"
                        class="bg-[#10213E] hover:bg-blue-900 text-white font-bold text-xs uppercase px-4 py-2 rounded-lg transition">
                        🖨️ {{ $t('recepcion.imprimirPdf') }}
                    </a>
                    <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold text-xs uppercase px-4 py-2 rounded-lg transition">
                        💬 WhatsApp
                    </a>
                    <Link :href="route('recepcion.generate-order', recepcion.id)"
                        method="post" as="button"
                        class="bg-[#EE2857] hover:bg-red-700 text-white font-black text-xs uppercase px-4 py-2 rounded-lg transition shadow">
                        📋 {{ $t('recepcion.generarCotizacion') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-screen transition-colors">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <!-- Cliente -->
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#10213E] p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">👤 {{ $t('tabla.cliente') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.nombre') }}</span>
                            {{ recepcion.client?.first_name }} {{ recepcion.client?.last_name }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.telefono') }}</span>
                            {{ recepcion.client?.phone ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">RFC</span>
                            {{ recepcion.client?.rfc ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.direccion') }}</span>
                            {{ recepcion.client?.address ?? '—' }}</div>
                    </div>
                </div>

                <!-- Vehículo -->
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-400 p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">🚗 {{ $t('tabla.vehiculo') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.marca') }}</span>
                            {{ recepcion.vehicle?.brand?.name ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.modelo') }}</span>
                            {{ recepcion.vehicle?.vehicleModel?.name ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.anio') }}</span>
                            {{ recepcion.vehicle?.year ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.placas') }}</span>
                            <span class="font-bold uppercase">{{ recepcion.vehicle?.plate ?? '—' }}</span></div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.serie') }}</span>
                            {{ recepcion.vehicle?.vin ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.motor') }}</span>
                            {{ recepcion.vehicle?.engine ?? '—' }}</div>
                    </div>
                </div>

                <!-- Inspección -->
                <div class="bg-white rounded-xl shadow-sm border-l-4 border-yellow-400 p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">🔍 {{ $t('inspeccion.titulo') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm mb-4">
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.kilometraje') }}</span>
                            {{ recepcion.miles ? Number(recepcion.miles).toLocaleString('en-US') + ' mi' : '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('recepcion_form.gasolina') }}</span>
                            {{ recepcion.fuel_level ?? '—' }}</div>
                        <div><span class="text-xs text-gray-400 uppercase font-bold block">{{ $t('inspeccion.fechaIngreso') }}</span>
                            {{ new Date(recepcion.created_at).toLocaleDateString('en-US') }}</div>
                    </div>
                    <div v-if="recepcion.witnesses?.length">
                        <span class="text-xs text-gray-400 uppercase font-bold block mb-2">{{ $t('recepcion_form.testigos') }}</span>
                        <div class="flex gap-2 flex-wrap">
                            <span v-for="w in recepcion.witnesses" :key="w"
                                class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-lg">
                                {{ $t('inspeccion.' + w) }}
                            </span>
                        </div>
                    </div>
                    <div v-if="recepcion.inventory?.length" class="mt-4">
                        <span class="text-xs text-gray-400 uppercase font-bold block mb-2">{{ $t('recepcion_form.inventarioIngreso') }}</span>
                        <div class="flex gap-2 flex-wrap">
                            <span v-for="i in recepcion.inventory" :key="i"
                                class="bg-gray-100 text-gray-700 text-xs font-bold px-2 py-1 rounded-lg">
                                {{ $t('recepcion_form.inv_' + i) }}
                            </span>
                        </div>
                    </div>
                    <div v-if="recepcion.damages?.length" class="mt-4">
                        <span class="text-xs text-gray-400 uppercase font-bold block mb-2">{{ $t('recepcion_form.danos') }}</span>
                        <ul class="text-sm text-gray-700 list-disc ml-5 space-y-1">
                            <li v-for="(d, idx) in recepcion.damages" :key="idx">{{ $t('recepcion_form.zona_' + d.zone) }}<span v-if="d.severity" class="capitalize"> ({{ d.severity }})</span><span v-if="d.note"> — {{ d.note }}</span></li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <span class="text-xs text-gray-400 uppercase font-bold block mb-1">{{ $t('inspeccion.sintomas') }}</span>
                        <p class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3">{{ recepcion.symptoms ?? $t('inspeccion.sinDescripcion') }}</p>
                    </div>
                </div>

                <!-- Fotos -->
                <div v-if="recepcion.photos?.length" class="bg-white rounded-xl shadow-sm border-l-4 border-green-400 p-6">
                    <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest mb-3 border-b pb-2">📷 {{ $t('recepcion.evidencia') }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <a v-for="(photo, i) in recepcion.photos" :key="i"
                            :href="'/storage/' + photo" target="_blank"
                            class="aspect-square rounded-xl overflow-hidden border border-gray-200 hover:opacity-80 transition">
                            <img :src="'/storage/' + photo" class="w-full h-full object-cover" :alt="$t('recepcion.evidencia') + ' ' + (i+1)" />
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
