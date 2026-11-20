<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    stats:              Object,
    recentRecepcions:   Object,
    filters:            Object,
});

const page     = usePage();
const isAdmin  = computed(() => page.props.auth.isAdmin);
const { t } = useI18n();
const isSearching = ref(false);
const search   = ref(props.filters.search || "");
let timeout    = null;

const showPhotoModal  = ref(false);
const selectedPhotos  = ref([]);

const openPhotoGallery = (photos) => {
    selectedPhotos.value = Array.isArray(photos) ? photos : JSON.parse(photos || '[]');
    showPhotoModal.value = true;
};

watch(search, (value) => {
    isSearching.value = true;
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route("dashboard"), { search: value }, {
            preserveState: true, replace: true, preserveScroll: true,
            onFinish: () => (isSearching.value = false),
        });
    }, 500);
});

const confirmDelete = ref({ show: false, id: null });
const deleteRecord = (id) => { confirmDelete.value = { show: true, id }; };
const doDelete = () => {
    router.delete(route("recepcion.destroy", confirmDelete.value.id), { preserveScroll: true });
    confirmDelete.value = { show: false, id: null };
};

function formatCurrency(val) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'USD' }).format(val ?? 0);
}
</script>

<template>
    <Head :title="$t('common.panelControl')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-black leading-tight text-[#10213E] uppercase tracking-tighter">
                {{ $t('dashboard.title') }}
                <span class="text-[#10213E]">JK</span><span class="text-[#EE2857]">Automotive</span>
            </h2>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen transition-colors duration-200">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">

                <!-- Flash -->
                <div v-if="$page.props.flash.success"
                    class="bg-green-50 border-l-4 border-green-500 px-5 py-3 rounded-r-lg flex items-center gap-3 text-sm text-green-800 font-medium shadow-sm">
                    ✅ {{ $page.props.flash.success }}
                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- QUICK STATS                                     -->
                <!-- ═══════════════════════════════════════════════ -->
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">

                    <!-- Órdenes activas -->
                    <a :href="route('repair-orders.index')"
                        class="group bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-all hover:-translate-y-0.5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">{{ $t('dashboard.ordenesActivas') }}</p>
                                <p class="text-2xl font-black text-[#10213E]">{{ stats.ordenesActivas ?? 0 }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $t('dashboard.enProgreso') }}</p>
                                <p v-if="isAdmin && stats.porCotizar > 0"
                                    class="text-[10px] font-black text-amber-600 mt-1 bg-amber-50 px-1.5 py-0.5 rounded inline-block">
                                    ⏳ {{ stats.porCotizar }} {{ $t('dashboard.porCotizar') }}
                                </p>
                            </div>
                            <span class="text-xl bg-blue-50 p-1.5 rounded-lg group-hover:bg-blue-100 transition">🔧</span>
                        </div>
                    </a>

                    <!-- Recepciones hoy -->
                    <a :href="route('recepcion.create')"
                        class="group bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-all hover:-translate-y-0.5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">{{ $t('dashboard.recepcionesHoy') }}</p>
                                <p class="text-2xl font-black text-[#10213E]">{{ stats.today ?? 0 }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $t('dashboard.de') }} {{ stats.total }} {{ $t('dashboard.totalLabel') }}</p>
                            </div>
                            <span class="text-xl bg-green-50 p-1.5 rounded-lg group-hover:bg-green-100 transition">🚗</span>
                        </div>
                    </a>

                    <!-- Alertas stock (solo admin/owner: inventario global) -->
                    <a v-if="isAdmin" :href="route('parts.index')"
                        class="group rounded-xl shadow-sm border p-4 transition-all hover:-translate-y-0.5"
                        :class="stats.stockAlertas > 0
                            ? 'bg-red-50 border-red-200 hover:shadow-md'
                            : 'bg-white border-gray-100 hover:shadow-md'">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest mb-1"
                                    :class="stats.stockAlertas > 0 ? 'text-red-400' : 'text-gray-400'">
                                    {{ $t('dashboard.alertas') }}
                                </p>
                                <p class="text-2xl font-black"
                                    :class="stats.stockAlertas > 0 ? 'text-red-600' : 'text-[#10213E]'">
                                    {{ stats.stockAlertas ?? 0 }}
                                </p>
                                <p class="text-xs mt-1"
                                    :class="stats.stockAlertas > 0 ? 'text-red-400' : 'text-gray-400'">
                                    {{ stats.stockAlertas > 0 ? $t('inventario.stockBajo') : $t('dashboard.sinAlertas') }}
                                </p>
                            </div>
                            <span class="text-2xl p-2 rounded-xl transition"
                                :class="stats.stockAlertas > 0 ? 'bg-red-100 group-hover:bg-red-200' : 'bg-gray-50 group-hover:bg-gray-100'">
                                📦
                            </span>
                        </div>
                    </a>

                    <!-- Por Recoger +48h — solo admin -->
                    <a v-if="isAdmin"
                        :href="route('repair-orders.index')"
                        class="group rounded-xl shadow-sm border p-4 transition-all hover:-translate-y-0.5"
                        :class="stats.pendingPickupCount > 0
                            ? 'bg-orange-50 border-orange-200 hover:shadow-md'
                            : 'bg-white border-gray-100 hover:shadow-md'">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest mb-1"
                                    :class="stats.pendingPickupCount > 0 ? 'text-orange-500' : 'text-gray-400'">
                                    {{ $t('dashboard.porRecoger') }}
                                </p>
                                <p class="text-2xl font-black"
                                    :class="stats.pendingPickupCount > 0 ? 'text-orange-600' : 'text-[#10213E]'">
                                    {{ stats.pendingPickupCount ?? 0 }}
                                </p>
                                <p class="text-xs mt-1"
                                    :class="stats.pendingPickupCount > 0 ? 'text-orange-400' : 'text-gray-400'">
                                    {{ $t('dashboard.porRecogerDesc') }}
                                </p>
                            </div>
                            <span class="text-2xl p-2 rounded-xl transition"
                                :class="stats.pendingPickupCount > 0 ? 'bg-orange-100 group-hover:bg-orange-200' : 'bg-gray-50 group-hover:bg-gray-100'">
                                🚗
                            </span>
                        </div>
                    </a>

                    <!-- Ingresos del mes — solo admin -->
                    <a v-if="isAdmin"
                        :href="route('reports.index')"
                        class="bg-gradient-to-br from-[#10213E] to-blue-900 rounded-xl shadow-sm p-4 block hover:opacity-90 transition-opacity">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-200 uppercase tracking-widest mb-1">{{ $t('dashboard.ingresos') }}</p>
                                <p class="text-xl font-black text-white">{{ formatCurrency(stats.ingresosMes) }}</p>
                                <p class="text-xs text-blue-300 mt-1">{{ $t('dashboard.pagosRegistrados') }}</p>
                            </div>
                            <span class="text-xl bg-white/10 p-1.5 rounded-lg">📊</span>
                        </div>
                        <p class="text-[9px] text-blue-300 mt-2 font-bold uppercase tracking-widest">{{ $t('dashboard.verReporte') }} →</p>
                    </a>

                    <!-- Placeholder para mechanic (no ve ingresos) -->
                    <div v-else
                        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center justify-center">
                        <p class="text-xs text-gray-300 italic text-center">{{ $t('dashboard.bienvenido') }}</p>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- BUSCADOR + TABLA RECEPCIONES                   -->
                <!-- ═══════════════════════════════════════════════ -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                    <!-- Header con buscador -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-gray-100">
                        <h3 class="font-black text-sm text-[#10213E] uppercase tracking-wider">
                            📋 {{ $t('dashboard.recientes') }}
                        </h3>
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Exportar CSV (solo admin) -->
                            <a v-if="isAdmin"
                                :href="route('export.ordenes') + '?mes=' + new Date().toISOString().slice(0,7)"
                                class="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white font-black text-xs uppercase px-3 py-2 rounded-lg transition shadow-sm"
                                title="Exportar órdenes del mes a Excel/CSV">
                                {{ $t('dashboard.exportar') }}
                            </a>
                            <div class="relative w-full sm:w-72">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="$t('dashboard.buscar')"
                                class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E] bg-gray-50"
                            />
                            <span v-if="isSearching" class="absolute right-3 top-1/2 -translate-y-1/2 animate-spin text-sm">⟳</span>
                        </div>
                        </div>
                        <Link :href="route('recepcion.create')"
                            class="shrink-0 inline-flex items-center min-h-[44px] bg-[#EE2857] hover:bg-red-700 text-white font-black text-xs uppercase px-4 py-2 rounded-xl transition shadow-sm">
                            {{ $t('dashboard.nuevaRecepcion') }}
                        </Link>
                    </div>

                    <!-- Tabla -->
                    <div class="overflow-x-auto">
                        <div class="overflow-x-auto"><table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b">
                                <tr>
                                    <th class="px-6 py-3">#</th>
                                    <th class="px-6 py-3">{{ $t('tabla.cliente') }}</th>
                                    <th class="px-6 py-3">{{ $t('tabla.vehiculo') }}</th>
                                    <th class="px-6 py-3">{{ $t('tabla.placa') }}</th>
                                    <th class="px-6 py-3 text-center">{{ $t('tabla.estado') }}</th>
                                    <th class="px-6 py-3">{{ $t('tabla.fecha') }}</th>
                                    <th class="px-6 py-3 text-center">{{ $t('tabla.acciones') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="rec in recentRecepcions.data" :key="rec.id"
                                    class="hover:bg-gray-50 transition-colors group">
                                    <td class="px-6 py-4 font-mono text-gray-400 text-xs">{{ rec.id }}</td>
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-[#10213E]">
                                            {{ rec.client?.first_name }} {{ rec.client?.last_name }}
                                        </p>
                                        <p class="text-xs text-gray-400">{{ rec.client?.phone ?? '—' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        {{ rec.vehicle?.brand?.name ?? 'S/M' }}
                                        {{ rec.vehicle?.vehicleModel?.name ?? '' }}
                                        <span class="text-gray-400 text-xs">({{ rec.vehicle?.year ?? '—' }})</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold uppercase text-xs text-gray-600">
                                        {{ rec.vehicle?.plate ?? '—' }}
                                    </td>
                                    <!-- Badge de estado del taller -->
                                    <td class="px-6 py-4 text-center">
                                        <span v-if="rec.repair_order?.status"
                                            class="inline-block text-[10px] font-black uppercase px-2.5 py-1 rounded-full"
                                            :class="rec.repair_order.status.color_class ?? 'bg-gray-100 text-gray-600'">
                                            {{ $t('status.' + rec.repair_order.status.slug) }}
                                        </span>
                                        <span v-else
                                            class="inline-block text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 italic">
                                            {{ $t('dashboard.sinOrden') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 text-xs">
                                        {{ new Date(rec.created_at).toLocaleDateString('es-MX') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <button
                                                @click="openPhotoGallery(rec.photos ?? [])"
                                                title="Ver fotos del vehículo"
                                                class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                📷
                                            </button>
                                            <Link :href="route('recepcion.show', rec.id)"
                                                class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                {{ $t('acciones.ver') }}
                                            </Link>
                                            <button v-if="isAdmin" @click="deleteRecord(rec.id)"
                                                class="bg-red-50 hover:bg-red-100 text-[#EE2857] px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                ✕
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!recentRecepcions.data?.length">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-300 italic text-sm">
                                        {{ search ? $t('dashboard.sinResultadosBusqueda') + ' \"' + search + '\"' : $t('dashboard.sinRecepciones') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table></div>
                    </div>

                    <!-- Ver todos -->
                    <div class="px-6 py-3 border-t border-gray-100 flex justify-between items-center">
                        <Link :href="route('recepcion.index')"
                            class="text-xs text-[#10213E] font-black hover:underline flex items-center gap-1">
                            {{ $t('dashboard.verTodos') }}
                        </Link>
                    </div>

                    <!-- Paginación -->
                    <div v-if="recentRecepcions.last_page > 1"
                        class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <span>{{ $t('dashboard.mostrando') }} {{ recentRecepcions.from }}–{{ recentRecepcions.to }} {{ $t('dashboard.de') }} {{ recentRecepcions.total }} {{ $t('dashboard.recepciones') }}</span>
                        <div class="flex gap-1">
                            <Link v-for="link in recentRecepcions.links" :key="link.label"
                                :href="link.url ?? '#'"
                                v-html="link.label"
                                class="px-3 py-1.5 rounded-lg border transition"
                                :class="link.active
                                    ? 'bg-[#10213E] text-white border-[#10213E] font-black'
                                    : link.url
                                        ? 'bg-white border-gray-200 hover:bg-gray-50 text-gray-600'
                                        : 'bg-gray-50 border-gray-100 text-gray-300 cursor-default'"
                            />
                        </div>
                    </div>
                </div>

            </div>
        </div>



        <!-- Modal fotos -->
        <div v-if="showPhotoModal"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4 backdrop-blur-sm"
            @click.self="showPhotoModal = false">
            <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full p-6 max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-black text-[#10213E] uppercase tracking-wider text-sm">{{ $t('recepcion.evidencia') }}</h3>
                    <button @click="showPhotoModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">✕</button>
                </div>
                <div v-if="selectedPhotos.length" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <img v-for="(photo, i) in selectedPhotos" :key="i"
                        :src="'/storage/' + (typeof photo === 'string' ? photo : photo.path)"
                        class="rounded-xl object-cover w-full aspect-square cursor-zoom-in hover:opacity-90 transition" />
                </div>
                <p v-else class="text-center text-gray-400 italic py-8">{{ $t('recepcion.sinFotos') }}</p>
            </div>
        </div>


        <!-- Modal confirmación eliminar -->
        <ConfirmModal
            :show="confirmDelete.show"
            :title="$t('dashboard.eliminarTitulo')"
            :message="$t('dashboard.eliminarMsg')"
            :confirm-text="$t('acciones.eliminar')"
            @confirm="doDelete"
            @cancel="confirmDelete.show = false"
        />
    </AuthenticatedLayout>
</template>
