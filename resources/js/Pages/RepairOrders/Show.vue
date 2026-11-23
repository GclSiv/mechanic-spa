<script setup>
import OrderTotals from '@/Components/OrderTotals.vue';
import FollowUpLogger from '@/Components/FollowUpLogger.vue';
import PaymentPanel from '@/Components/PaymentPanel.vue';
import QuotesPanel from '@/Components/QuotesPanel.vue';
import AvancePhotos from '@/Components/AvancePhotos.vue';
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import OrderHeader from '@/Components/UI/OrderHeader.vue';
import OrderStatusTrack from '@/Components/UI/OrderStatusTrack.vue';
import ContentTabs from '@/Components/UI/ContentTabs.vue';
import SideInfoPanel from '@/Components/UI/SideInfoPanel.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    orden: Object,
    recepcion: Object,
    financial_breakdown: Object,
    estado_financiero: Object,
    settings: Object,
    statuses: Array,
    mechanics: Array,
    parts: Array,
    isAdmin: Boolean,
});

const page = usePage();

// ── Expediente: pestañas, condición financiera y fechas ───────────────
// Mecánico: no ve Cotizaciones ni Pagos. El backend ya no le envía esos datos; esto solo oculta pestañas.
const tabActiva = ref('resumen');
const tabs = computed(() => [
    { key: 'resumen', label: t('expediente.tabs.resumen') },
    { key: 'diagnostico', label: t('expediente.tabs.diagnostico') },
    { key: 'trabajo', label: t('expediente.tabs.trabajo') },
    ...(props.isAdmin ? [
        { key: 'cotizaciones', label: t('expediente.tabs.cotizaciones') },
        { key: 'pagos', label: t('expediente.tabs.pagos') },
    ] : []),
    { key: 'evidencia', label: t('expediente.tabs.evidencia') },
    { key: 'historial', label: t('expediente.tabs.historial') },
]);

// Mecánico y bitácora: mismo alcance de estados que antes de este frente.
const bitacoraVisible = computed(() => ['recibido', 'diagnostico', 'espera-piezas', 'reparado', 'entregado'].includes(props.orden.status?.slug));

// Condición derivada y montos: solo se leen de la fuente financiera central (estado_financiero). No se recalcula.
const pendienteAutorizacion = computed(() => Number(props.estado_financiero?.pendiente_autorizacion ?? 0));
const dinero = (n) => '$' + Number(n ?? 0).toFixed(2);
const saldoTexto = computed(() => {
    const saldo = Number(props.estado_financiero?.saldo_autorizado ?? 0);
    return saldo <= 0 ? t('expediente.liquidado') : dinero(saldo);
});
const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');
const fechaRecepcion = computed(() => fecha(props.recepcion?.created_at));
const fechaCreada = computed(() => fecha(props.orden.created_at));
const fechaActualizada = computed(() => fecha(props.orden.updated_at));

// Refresca los datos de la orden (cotizaciones, fotos, pagos, estado) cada 15 s para ver
// al instante si el cliente aceptó o rechazó. Recarga la página completa de datos (sin "only")
// para que nada quede desactualizado, evita dos recargas a la vez y vuelve a cargar al
// regresar a la pestaña o a la ventana, porque el navegador pausa los temporizadores en segundo plano.
let refrescoTimer = null;
let recargando = false;

const refrescar = () => {
    if (document.hidden || recargando) return;
    recargando = true;
    router.reload({
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (recargando = false),
    });
};

const alVolverAVisible = () => {
    if (!document.hidden) refrescar();
};

onMounted(() => {
    refrescoTimer = setInterval(refrescar, 15000);
    document.addEventListener('visibilitychange', alVolverAVisible);
    window.addEventListener('focus', refrescar);
});

onUnmounted(() => {
    clearInterval(refrescoTimer);
    document.removeEventListener('visibilitychange', alVolverAVisible);
    window.removeEventListener('focus', refrescar);
});
const showModal = ref(false);

// ── Modal reasignación con motivo ─────────────────────────────────────
const showReassignModal = ref(false);
const reassignForm = useForm({ mechanic_id: '', reason: '' });

function openReassign() {
    reassignForm.mechanic_id = page.props.orden?.mechanic_id ?? '';
    reassignForm.reason = '';
    showReassignModal.value = true;
}
function submitReassign() {
    reassignForm.post(route('repair-orders.mechanic.reassign', page.props.orden?.id), {
        onSuccess: () => { showReassignModal.value = false; reassignForm.reset(); },
    });
}

// ── Diagnóstico terminado ─────────────────────────────────────────────
const diagnosisForm = useForm({});
function markDiagnosisComplete() {
    diagnosisForm.post(route('repair-orders.diagnosis.complete', page.props.orden?.id), {
        preserveScroll: true,
    });
}

// ── Lightbox fotos de recepción ───────────────────────────────────────
const lightboxSrc = ref(null);
function openPhotoLightbox(src) { lightboxSrc.value = src; }
function closeLightbox() { lightboxSrc.value = null; }

// Función ÚNICA para actualizar el impuesto sin recargar/cerrar el modal
function updateTax(newTax) {
    router.patch(route('repair-orders.tax.update', page.props.orden?.id), { tax_rate: newTax }, {
        preserveScroll: true,
        preserveState: true, 
    });
}

const form = useForm({
    description: '',
    type: 'part',
    part_id: null,
    quantity: 1,
    unit_price: 0,
});

// Refacciones: entero sin decimales. Mano de obra: dos decimales (p. ej. 1.50). Solo presentación; el servidor valida.
function cantidadTexto(item) {
    const n = Number(item.quantity);
    return item.type === 'part' ? String(Math.round(n)) : n.toFixed(2);
}

function selectPart(partId) {
    const part = (page.props.parts ?? []).find(p => p.id === Number(partId));
    if (part) {
        form.unit_price = part.sale_price;
        form.part_id = part.id;
    }
}

// Combobox buscador de refacciones
const partSearch   = ref('');
const showPartList = ref(false);
const filteredParts = computed(() => {
    const q = partSearch.value.toLowerCase().trim();
    return (page.props.parts ?? []).filter(p =>
        p.name.toLowerCase().includes(q)
    ).slice(0, 50);
});

function pickPart(part) {
    form.part_id    = part.id;
    form.unit_price = part.sale_price;
    partSearch.value = part.name;
    showPartList.value = false;
}

function getLang() { return localStorage.getItem('jk_locale') ?? 'es'; }
function imprimirCotizacion() {
    window.open(route('repair-orders.pdf', page.props.orden?.id) + '?lang=' + getLang(), '_blank');
}

function submitItem() {
    form.post(route('repair-orders.items.store', page.props.orden?.id), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
}

function removeItem(itemId) {
    if (confirm(t('cotizacion.eliminarConfirm'))) {
        router.delete(route('repair-orders.items.destroy', {
            order: page.props.orden?.id,
            item: itemId,
        }));
    }
}
</script>

<template>
    <AppShellLayout>
        <Head :title="$t('expediente.titulo')" />

        <div class="space-y-6">
            <OrderHeader :orden="orden" :statuses="statuses ?? []" :is-admin="isAdmin" :pendiente="pendienteAutorizacion">
                <template #acciones>
                    <Link
                        :href="route('repair-orders.index')"
                        class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-navy hover:bg-surface-2"
                    >{{ $t('expediente.volver') }}</Link>
                    <button
                        v-if="isAdmin"
                        type="button"
                        class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:opacity-90"
                        @click="imprimirCotizacion"
                    >{{ $t('cotizacion.descargarPdf') }}</button>
                </template>
            </OrderHeader>

            <OrderStatusTrack :slug="orden.status?.slug ?? null" />

            <ContentTabs v-model="tabActiva" :tabs="tabs" :label="$t('expediente.tabsLabel')" />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                <div :id="`panel-${tabActiva}`" role="tabpanel" :aria-labelledby="`tab-${tabActiva}`" class="min-w-0 space-y-6">

                    <!-- RESUMEN -->
                    <div v-if="tabActiva === 'resumen'" class="grid gap-4 md:grid-cols-2">
                        <section class="rounded-xl border border-line bg-surface p-4 md:col-span-2">
                            <h2 class="mb-2 text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('expediente.motivo') }}</h2>
                            <p class="text-ds-body text-ink">{{ recepcion?.symptoms || $t('expediente.sinMotivo') }}</p>
                        </section>

                        <section class="rounded-xl border border-line bg-surface p-4">
                            <h2 class="mb-3 text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('expediente.datosCliente') }}</h2>
                            <dl class="space-y-2 text-ds-body">
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.cliente') }}</dt>
                                    <dd class="text-ink">{{ recepcion?.client?.first_name }} {{ recepcion?.client?.last_name }}</dd>
                                </div>
                                <div v-if="isAdmin">
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.telefono') }}</dt>
                                    <dd class="text-ink">{{ recepcion?.client?.phone || $t('expediente.sinDato') }}</dd>
                                </div>
                                <div v-if="isAdmin">
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.rfc') }}</dt>
                                    <dd class="text-ink">{{ recepcion?.client?.rfc || $t('expediente.sinDato') }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="rounded-xl border border-line bg-surface p-4">
                            <h2 class="mb-3 text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('expediente.datosVehiculo') }}</h2>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-ds-body">
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.marca') }}</dt>
                                    <dd class="text-ink">{{ recepcion?.vehicle?.brand?.name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.modelo') }}</dt>
                                    <dd class="text-ink">{{ recepcion?.vehicle?.vehicleModel?.name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.anio') }}</dt>
                                    <dd class="text-ink">{{ recepcion?.vehicle?.year ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.placa') }}</dt>
                                    <dd class="font-mono uppercase text-ink">{{ recepcion?.vehicle?.plate ?? '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.vin') }}</dt>
                                    <dd class="break-all font-mono text-ink">{{ recepcion?.vehicle?.vin ?? '—' }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section v-if="isAdmin" class="rounded-xl border border-line bg-surface p-4 md:col-span-2">
                            <h2 class="mb-3 text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('expediente.financiero') }}</h2>
                            <dl class="grid grid-cols-2 gap-4 text-ds-body md:grid-cols-4">
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.autorizado') }}</dt>
                                    <dd class="font-semibold tabular-nums text-ink">{{ dinero(estado_financiero?.total_autorizado) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.pagado') }}</dt>
                                    <dd class="font-semibold tabular-nums text-ink">{{ dinero(estado_financiero?.total_pagado) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.saldo') }}</dt>
                                    <dd class="font-semibold tabular-nums text-ink">{{ saldoTexto }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.pendienteAutorizacion') }}</dt>
                                    <dd class="font-semibold tabular-nums text-ink">{{ dinero(estado_financiero?.pendiente_autorizacion) }}</dd>
                                </div>
                            </dl>
                            <p v-if="pendienteAutorizacion > 0" class="mt-3 text-ds-caption text-ink-secondary">{{ $t('expediente.pendienteNota') }}</p>
                        </section>
                    </div>

                    <!-- DIAGNÓSTICO -->
                    <div v-if="tabActiva === 'diagnostico'" class="space-y-4">
                        <template v-if="bitacoraVisible">
                            <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black text-[#10213E] uppercase tracking-wider">
                                🔧 {{ $t('show.mecanico') }}:
                                <span class="text-blue-600 font-bold normal-case">
                                    {{ orden.mechanic?.name ?? $t('taller.sinAsignar') }}
                                </span>
                            </p>
                            <p v-if="orden.needs_quote"
                                class="text-xs text-amber-600 font-bold mt-1">
                                ⏳ {{ $t('show.pendienteCotizacion') }}
                            </p>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <!-- Diagnóstico Terminado — visible para mecánico y admin en cualquier estado activo -->
                            <button
                                v-if="!orden.needs_quote && !['entregado','rechazado'].includes(orden.status?.slug)"
                                @click="markDiagnosisComplete"
                                :disabled="diagnosisForm.processing"
                                class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black text-xs px-3 py-2 rounded-lg transition shadow-sm">
                                ✅ {{ $t('show.diagnosticoTerminado') }}
                            </button>
                            <!-- Reasignar — solo admin -->
                            <button
                                v-if="isAdmin"
                                @click="openReassign"
                                class="flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs px-3 py-2 rounded-lg transition">
                                🔄 {{ $t('show.reasignar') }}
                            </button>
                        </div>
                    </div>
                        </template>
                        <p v-else class="text-ds-body text-ink-muted">{{ $t('expediente.sinEtapa') }}</p>
                    </div>

                    <!-- TRABAJO -->
                    <div v-if="tabActiva === 'trabajo'" class="space-y-6">
                        <!-- CONCEPTOS DE LA COTIZACIÓN -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-xl">
                    <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                        <h3 class="text-white font-black text-sm uppercase tracking-wider">{{ $t('cotizacion.conceptos') }}</h3>
                        <button v-if="isAdmin || !orden.esta_cerrada" @click="showModal = true"
                            class="bg-[#EE2857] hover:bg-red-700 text-white px-5 py-2 rounded-lg font-bold shadow-md transition-all text-xs uppercase">
                            {{ $t('cotizacion.agregarConcepto') }}
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div class="overflow-x-auto"><table class="w-full text-left border-collapse mb-6">
                            <thead>
                                <tr class="bg-gray-100 text-gray-500 uppercase text-xs tracking-wider font-bold border-b border-gray-200">
                                    <th class="p-3">{{ $t('cotizacion.cant') }}</th>
                                    <th class="p-3">{{ $t('cotizacion.tipo') }}</th>
                                    <th class="p-3">{{ $t('cotizacion.descripcion') }}</th>
                                    <th v-if="isAdmin" class="p-3 text-right">{{ $t('cotizacion.unitario') }}</th>
                                    <th v-if="isAdmin" class="p-3 text-right">{{ $t('cotizacion.totalParcial') }}</th>
                                    <th class="p-3 text-center">{{ $t('cotizacion.accion') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 text-sm">
                                <tr v-for="item in orden.items" :key="item.id" class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                                    <td class="p-3 font-medium">{{ cantidadTexto(item) }}</td>
                                    <td class="p-3">
                                        <span :class="item.type === 'part' ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-700'"
                                              class="px-2 py-1 rounded-md text-[10px] font-black uppercase">
                                            {{ item.type === 'part' ? $t('cotizacion.refaccion') : $t('cotizacion.manoDeObra') }}
                                        </span>
                                    </td>
                                    <td class="p-3">{{ item.description }}</td>
                                    <td v-if="isAdmin" class="p-3 text-right">${{ Number(item.unit_price).toFixed(2) }}</td>
                                    <td v-if="isAdmin" class="p-3 text-right font-black">${{ Number(item.subtotal).toFixed(2) }}</td>
                                    <td class="p-3 text-center">
                                        <button v-if="isAdmin || (item.type === 'part' && !orden.esta_cerrada)" @click="removeItem(item.id)" class="text-gray-400 hover:text-[#EE2857] text-sm font-bold transition">
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!orden.items || orden.items.length === 0">
                                    <td colspan="6" class="p-10 text-center text-gray-400 italic font-medium">{{ $t('cotizacion.sinArticulos') }}</td>
                                </tr>
                            </tbody>
                        </table></div>

                        <!-- Calculadora Financiera — solo admin -->
                        <div v-if="isAdmin" class="flex justify-end mt-4 pt-4 border-t border-gray-200">
                            <OrderTotals :orden="orden" :breakdown="financial_breakdown" />
                        </div>
                    </div>
                </div>
                    </div>

                    <!-- COTIZACIONES: solo admin -->
                    <div v-if="tabActiva === 'cotizaciones' && isAdmin">
                        <QuotesPanel :orden="orden" />
                    </div>

                    <!-- PAGOS: solo admin -->
                    <div v-if="tabActiva === 'pagos' && isAdmin" id="pagos" class="scroll-mt-6">
                        <PaymentPanel :orden="orden" :estado="estado_financiero" />
                    </div>

                    <!-- EVIDENCIA -->
                    <div v-if="tabActiva === 'evidencia'" class="space-y-6">
                        <section v-if="recepcion?.photos && recepcion.photos.length > 0" class="rounded-xl border border-line bg-surface p-4">
                            <h2 class="mb-3 text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('cotizacion.evidenciaFotos') }}</h2>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 md:grid-cols-8">
                                <button
                                    v-for="(photo, i) in recepcion.photos"
                                    :key="i"
                                    type="button"
                                    class="ds-focus aspect-square overflow-hidden rounded-lg border border-line transition-all hover:border-navy"
                                    @click="openPhotoLightbox('/storage/' + photo)"
                                >
                                    <img :src="'/storage/' + photo" class="h-full w-full object-cover" :alt="'Foto ' + (i + 1)" />
                                </button>
                            </div>
                        </section>
                        <AvancePhotos :orden="orden" :puede-borrar="isAdmin || !orden.esta_cerrada" />
                    </div>

                    <!-- HISTORIAL: bitácora y vehículo -->
                    <div v-if="tabActiva === 'historial'" class="space-y-4">
                        <template v-if="bitacoraVisible">
                            <FollowUpLogger :orden="orden" :mechanics="mechanics ?? []" />
                        </template>
                        <p v-else class="text-ds-body text-ink-muted">{{ $t('expediente.sinEtapa') }}</p>
                        <a
                            v-if="recepcion?.vehicle && isAdmin"
                            :href="route('vehicles.history', recepcion.vehicle.id)"
                            class="ds-focus inline-flex text-ds-control font-semibold text-navy underline-offset-2 hover:underline"
                        >{{ $t('expediente.verHistorial') }}</a>
                    </div>
                </div>

                <!-- PANEL LATERAL: información complementaria, sin acciones -->
                <div class="min-w-0 space-y-4">
                    <SideInfoPanel :title="$t('expediente.recepcion')">
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.fechaRecepcion') }}</dt>
                                <dd class="text-ink">{{ fechaRecepcion }}</dd>
                            </div>
                            <div>
                                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.kilometraje') }}</dt>
                                <dd class="text-ink">{{ recepcion?.miles ?? $t('expediente.sinDato') }}</dd>
                            </div>
                            <div>
                                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.combustible') }}</dt>
                                <dd class="text-ink">{{ recepcion?.fuel_level ?? $t('expediente.sinDato') }}</dd>
                            </div>
                        </dl>
                    </SideInfoPanel>

                    <SideInfoPanel :title="$t('expediente.mecanico')">
                        <p class="text-ink">{{ orden.mechanic?.name ?? $t('taller.sinAsignar') }}</p>
                    </SideInfoPanel>

                    <SideInfoPanel :title="$t('expediente.fechas')">
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.creada') }}</dt>
                                <dd class="text-ink">{{ fechaCreada }}</dd>
                            </div>
                            <div>
                                <dt class="text-ds-caption text-ink-muted">{{ $t('expediente.actualizada') }}</dt>
                                <dd class="text-ink">{{ fechaActualizada }}</dd>
                            </div>
                        </dl>
                    </SideInfoPanel>
                </div>
            </div>
        </div>

        <!-- Modal: Agregar Concepto -->
        <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                <!-- Header Dinámico con los Colores y Nombre de tu empresa -->
                <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                    <h3 class="text-white font-black text-sm tracking-wider uppercase">
                        {{ $t('cotizacion.modalTitulo') }} - {{ settings?.company_name || 'Taller' }}
                    </h3>
                    <button @click="showModal = false; form.reset()" class="text-gray-400 hover:text-white transition-colors text-xl font-bold"> ✕ </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- BOTONES DE CONFIGURACIÓN FISCAL FUNCIONALES -->
                    <div class="flex justify-between items-center bg-gray-100 p-1 rounded-lg mb-2">
                        <span class="text-xs font-bold text-gray-500 pl-3">{{ $t('cotizacion.fiscalConfig') }}</span>
                        <div class="flex gap-1">
                            <button type="button" @click="updateTax(8)"
                                    :class="Number(page.props.orden?.tax_rate) == 8 ? 'bg-[#10213E] text-white shadow-sm' : 'bg-transparent text-gray-500 hover:bg-gray-200'"
                                    class="px-4 py-1.5 rounded text-xs font-bold transition-all">
                                {{ $t('cotizacion.ivaFrontera') }}
                            </button>
                            <button type="button" @click="updateTax(16)"
                                    :class="Number(page.props.orden?.tax_rate) == 16 ? 'bg-[#10213E] text-white shadow-sm' : 'bg-transparent text-gray-500 hover:bg-gray-200'"
                                    class="px-4 py-1.5 rounded text-xs font-bold transition-all">
                                {{ $t('cotizacion.ivaGeneral') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('cotizacion.tipoLabel') }}</label>
                        <select v-model="form.type" @change="form.part_id = null; form.unit_price = 0; form.quantity = 1; form.description = ''; partSearch = ''; showPartList = false" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                            <option value="part">{{ $t('cotizacion.refaccion') }}</option>
                            <option v-if="isAdmin" value="labor">{{ $t('cotizacion.manoDeObra') }}</option>
                        </select>
                    </div>

                    <!-- Refacción: combobox buscador -->
                    <div v-if="form.type === 'part'" class="relative">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('cotizacion.refaccionLabel') }}</label>
                        <input
                            v-model="partSearch"
                            @focus="showPartList = true"
                            @input="showPartList = true"
                            type="text"
                            :placeholder="$t('cotizacion.buscarPh')"
                            autocomplete="off"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]"
                        />
                        <!-- Lista desplegable -->
                        <div v-if="showPartList && filteredParts.length > 0"
                            class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl max-h-52 overflow-y-auto">
                            <button v-for="p in filteredParts" :key="p.id"
                                type="button"
                                @mousedown.prevent="pickPart(p)"
                                class="w-full flex justify-between items-center px-4 py-2.5 text-sm hover:bg-gray-50 transition text-left"
                                :class="form.part_id === p.id ? 'bg-blue-50 font-black text-[#10213E]' : 'text-gray-700'">
                                <span>{{ p.name }}</span>
                                <span class="text-xs text-gray-400 ml-2 shrink-0">Stock: {{ p.stock }}</span>
                            </button>
                        </div>
                        <div v-if="showPartList && filteredParts.length === 0 && partSearch"
                            class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl px-4 py-3 text-sm text-gray-400 italic">
                            {{ $t('cotizacion.sinResultados') }} "{{ partSearch }}"
                        </div>
                        <p v-if="!page.props.parts || page.props.parts.length === 0" class="text-amber-600 text-xs mt-1">
                            {{ $t('cotizacion.sinStock') }}
                        </p>
                    </div>

                    <!-- Mano de obra: texto libre -->
                    <div v-if="form.type === 'labor' && isAdmin">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('cotizacion.descripcionLabel') }}</label>
                        <input v-model="form.description" type="text" :placeholder="$t('cotizacion.descripcionPh')"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('cotizacion.cantidad') }}</label>
                            <input v-model="form.quantity" type="number" :min="form.type === 'part' ? 1 : 0.01" :step="form.type === 'part' ? 1 : 0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                        </div>
                        <div v-if="isAdmin">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('cotizacion.precioUnitario') }}</label>
                            <input v-model="form.unit_price" type="number" min="0" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                        </div>
                    </div>
                    
                    <div v-if="isAdmin" class="bg-gray-100 rounded-lg px-4 py-3 flex justify-between text-sm font-black text-[#10213E] border border-gray-200 mt-2">
                        <span>{{ $t('cotizacion.subtotalEst') }}</span>
                        <span>${{ (Number(form.quantity) * Number(form.unit_price)).toFixed(2) }}</span>
                    </div>
                    
                    <div class="flex gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button @click="showModal = false; form.reset()"
                            class="flex-1 text-gray-500 font-bold py-2 rounded-lg hover:bg-gray-100 transition text-sm uppercase tracking-wider">
                            {{ $t('cotizacion.cancelar') }}
                        </button>
                        <button @click="submitItem" :disabled="form.processing"
                            class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2 rounded-lg transition text-sm uppercase tracking-wider shadow-md">
                            {{ form.processing ? $t('cotizacion.guardando') : $t('cotizacion.agregar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Lightbox fotos de recepción -->
        <Teleport to="body">
            <div v-if="lightboxSrc"
                class="fixed inset-0 bg-black/85 flex items-center justify-center z-[200] p-4 backdrop-blur-sm"
                @click.self="closeLightbox">
                <div class="relative max-w-4xl w-full">
                    <button @click="closeLightbox"
                        class="absolute -top-10 right-0 text-white text-2xl font-bold hover:text-gray-300 transition">
                        ✕
                    </button>
                    <img :src="lightboxSrc" class="w-full rounded-xl shadow-2xl max-h-[85vh] object-contain" />
                </div>
            </div>
        </Teleport>

        <!-- Modal reasignación con motivo -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showReassignModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                    @click.self="showReassignModal = false">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                            <h3 class="text-white font-black text-sm uppercase tracking-wider">🔄 {{ $t('show.reasignar') }}</h3>
                            <button @click="showReassignModal = false" class="text-gray-400 hover:text-white text-xl">✕</button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('show.nuevoMecanico') }} *</label>
                                <select v-model="reassignForm.mechanic_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                    <option value="" disabled>{{ $t('show.seleccionaMecanico') }}</option>
                                    <option v-for="m in mechanics" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                                <p v-if="reassignForm.errors.mechanic_id" class="text-red-500 text-xs mt-1">{{ reassignForm.errors.mechanic_id }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('show.motivoCambio') }} *</label>
                                <textarea v-model="reassignForm.reason" rows="3"
                                    :placeholder="$t('show.motivoPlaceholder')"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E] resize-none"></textarea>
                                <p v-if="reassignForm.errors.reason" class="text-red-500 text-xs mt-1">{{ reassignForm.errors.reason }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3 px-6 pb-6">
                            <button @click="showReassignModal = false"
                                class="flex-1 border border-gray-200 text-gray-500 font-bold py-2.5 rounded-xl hover:bg-gray-50 transition text-sm">
                                {{ $t('acciones.cancelar') }}
                            </button>
                            <button @click="submitReassign" :disabled="reassignForm.processing"
                                class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2.5 rounded-xl transition text-sm">
                                {{ reassignForm.processing ? '...' : $t('show.confirmarReasignacion') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppShellLayout>
</template>
