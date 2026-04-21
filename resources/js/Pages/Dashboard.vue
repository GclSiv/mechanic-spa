<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";

const props = defineProps({
    stats:             Object,
    recentRecepcions:  Object,
    filters:           Object,
});

const page     = usePage();
const isAdmin  = computed(() => page.props.auth.role === 'admin');
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
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-black leading-tight text-[#10213E] uppercase tracking-tighter">
                Panel Principal
                <span class="text-[#10213E]">JK</span><span class="text-[#EE2857]">Automotive</span>
            </h2>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">

                <!-- Flash -->
                <div v-if="$page.props.flash.success"
                    class="bg-green-50 border-l-4 border-green-500 px-5 py-3 rounded-r-lg flex items-center gap-3 text-sm text-green-800 font-medium shadow-sm">
                    ✅ {{ $page.props.flash.success }}
                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- QUICK STATS                                     -->
                <!-- ═══════════════════════════════════════════════ -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- Órdenes activas -->
                    <a :href="route('repair-orders.index')"
                        class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all hover:-translate-y-0.5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Órdenes Activas</p>
                                <p class="text-3xl font-black text-[#10213E]">{{ stats.ordenesActivas ?? 0 }}</p>
                                <p class="text-xs text-gray-400 mt-1">en progreso ahora</p>
                            </div>
                            <span class="text-2xl bg-blue-50 p-2 rounded-xl group-hover:bg-blue-100 transition">🔧</span>
                        </div>
                    </a>

                    <!-- Recepciones hoy -->
                    <a :href="route('recepcion.create')"
                        class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all hover:-translate-y-0.5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Recepciones Hoy</p>
                                <p class="text-3xl font-black text-[#10213E]">{{ stats.today ?? 0 }}</p>
                                <p class="text-xs text-gray-400 mt-1">de {{ stats.total }} total</p>
                            </div>
                            <span class="text-2xl bg-green-50 p-2 rounded-xl group-hover:bg-green-100 transition">🚗</span>
                        </div>
                    </a>

                    <!-- Alertas stock -->
                    <a :href="isAdmin ? route('parts.index') : '#'"
                        class="group rounded-2xl shadow-sm border p-5 transition-all hover:-translate-y-0.5"
                        :class="stats.stockAlertas > 0
                            ? 'bg-red-50 border-red-200 hover:shadow-md'
                            : 'bg-white border-gray-100 hover:shadow-md'">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest mb-1"
                                    :class="stats.stockAlertas > 0 ? 'text-red-400' : 'text-gray-400'">
                                    Alertas Inventario
                                </p>
                                <p class="text-3xl font-black"
                                    :class="stats.stockAlertas > 0 ? 'text-red-600' : 'text-[#10213E]'">
                                    {{ stats.stockAlertas ?? 0 }}
                                </p>
                                <p class="text-xs mt-1"
                                    :class="stats.stockAlertas > 0 ? 'text-red-400' : 'text-gray-400'">
                                    {{ stats.stockAlertas > 0 ? 'refacciones con stock bajo' : 'sin alertas' }}
                                </p>
                            </div>
                            <span class="text-2xl p-2 rounded-xl transition"
                                :class="stats.stockAlertas > 0 ? 'bg-red-100 group-hover:bg-red-200' : 'bg-gray-50 group-hover:bg-gray-100'">
                                📦
                            </span>
                        </div>
                    </a>

                    <!-- Ingresos del mes — solo admin -->
                    <div v-if="isAdmin"
                        class="bg-gradient-to-br from-[#10213E] to-blue-900 rounded-2xl shadow-sm p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-200 uppercase tracking-widest mb-1">Ingresos del Mes</p>
                                <p class="text-2xl font-black text-white">{{ formatCurrency(stats.ingresosMes) }}</p>
                                <p class="text-xs text-blue-300 mt-1">pagos registrados</p>
                            </div>
                            <span class="text-2xl bg-white/10 p-2 rounded-xl">💰</span>
                        </div>
                    </div>

                    <!-- Placeholder para mechanic (no ve ingresos) -->
                    <div v-else
                        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center justify-center">
                        <p class="text-xs text-gray-300 italic text-center">Bienvenido al taller</p>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- BUSCADOR + TABLA RECEPCIONES                   -->
                <!-- ═══════════════════════════════════════════════ -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                    <!-- Header con buscador -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-gray-100">
                        <h3 class="font-black text-sm text-[#10213E] uppercase tracking-wider">
                            📋 Recepciones Recientes
                        </h3>
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Exportar CSV (solo admin) -->
                            <a v-if="isAdmin"
                                :href="route('export.ordenes') + '?mes=' + new Date().toISOString().slice(0,7)"
                                class="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white font-black text-xs uppercase px-3 py-2 rounded-lg transition shadow-sm"
                                title="Exportar órdenes del mes a Excel/CSV">
                                📊 Exportar CSV
                            </a>
                            <div class="relative w-full sm:w-72">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cliente, placa, folio..."
                                class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E] bg-gray-50"
                            />
                            <span v-if="isSearching" class="absolute right-3 top-1/2 -translate-y-1/2 animate-spin text-sm">⟳</span>
                        </div>
                        </div>
                        <Link :href="route('recepcion.create')"
                            class="shrink-0 bg-[#EE2857] hover:bg-red-700 text-white font-black text-xs uppercase px-4 py-2 rounded-xl transition shadow-sm">
                            + Nueva Recepción
                        </Link>
                    </div>

                    <!-- Tabla -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b">
                                <tr>
                                    <th class="px-6 py-3">#</th>
                                    <th class="px-6 py-3">Cliente</th>
                                    <th class="px-6 py-3">Vehículo</th>
                                    <th class="px-6 py-3">Placa</th>
                                    <th class="px-6 py-3">Fecha</th>
                                    <th class="px-6 py-3 text-center">Acciones</th>
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
                                                Ver
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
                                        {{ search ? 'Sin resultados para "' + search + '"' : 'No hay recepciones registradas.' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="recentRecepcions.last_page > 1"
                        class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <span>Mostrando {{ recentRecepcions.from }}–{{ recentRecepcions.to }} de {{ recentRecepcions.total }}</span>
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
                    <h3 class="font-black text-[#10213E] uppercase tracking-wider text-sm">Evidencia Fotográfica</h3>
                    <button @click="showPhotoModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">✕</button>
                </div>
                <div v-if="selectedPhotos.length" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <img v-for="(photo, i) in selectedPhotos" :key="i"
                        :src="'/storage/' + (typeof photo === 'string' ? photo : photo.path)"
                        class="rounded-xl object-cover w-full aspect-square cursor-zoom-in hover:opacity-90 transition" />
                </div>
                <p v-else class="text-center text-gray-400 italic py-8">Sin fotos registradas.</p>
            </div>
        </div>


        <!-- Modal confirmación eliminar -->
        <ConfirmModal
            :show="confirmDelete.show"
            title="Eliminar recepción"
            message="¿Estás seguro? Se eliminará permanentemente este registro de recepción."
            confirm-text="Sí, eliminar"
            @confirm="doDelete"
            @cancel="confirmDelete.show = false"
        />
    </AuthenticatedLayout>
</template>
