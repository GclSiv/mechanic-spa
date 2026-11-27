<script setup>
import { computed, reactive, ref } from 'vue';
import TarjetaCotizacion from '@/Components/Tracking/TarjetaCotizacion.vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    orden: Object,
    client_first_name: String,
    vehicle: Object,
    quotes: Array,
    current_quote_id: Number,
    settings: Object,
    timeline: Object,
    pendientes: Array,
    financiero: Object,
    ultima_actualizacion: String,
    token: String,
    avance_fotos: Array,
    cita: Object,
    recordatorios: Array,
});

const { t } = useI18n();

const money = (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0));

const statusLabel = computed(() => {
    const slug = props.orden?.status?.slug;
    if (slug === 'entregado') return 'Servicio finalizado';
    const key = `status.${slug}`;
    const translated = slug ? t(key) : '';
    return translated && translated !== key ? translated : (props.orden?.status?.name ?? '');
});

const whatsappUrl = computed(() => {
    let digits = String(props.settings?.whatsapp ?? '').replace(/\D/g, '');
    if (!digits) return null;
    if (digits.length === 10) digits = '52' + digits;
    return `https://wa.me/${digits}`;
});

// Solicitud de cita: el backend decide cliente, vehículo y orden; aquí solo van preferencias.
const modalCita = reactive({ abierto: false, enviando: false, error: '', motivo: 'mantenimiento', fecha: '', horario: '', comentario: '' });
const motivosCita = [
    { value: 'mantenimiento', label: 'Mantenimiento' },
    { value: 'revision', label: 'Revisión' },
    { value: 'garantia', label: 'Garantía / revisión posterior' },
    { value: 'diagnostico', label: 'Diagnóstico' },
    { value: 'otro', label: 'Otro' },
];
const fechaMinima = new Date().toISOString().slice(0, 10);
const estadoCitaTexto = { pendiente: 'Pendiente', confirmada: 'Confirmada', rechazada: 'Rechazada', cancelada: 'Cancelada', completada: 'Completada' };
const fechaLegible = (f) => f ? new Date(f + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
const citaActiva = computed(() => props.cita?.activa === true);

function abrirCita() { if (citaActiva.value) return; modalCita.abierto = true; modalCita.error = ''; }
function cerrarCita() { modalCita.abierto = false; }
function enviarCita() {
    modalCita.enviando = true;
    modalCita.error = '';
    router.post(route('tracking.appointment.store', { token: props.token }), {
        reason: modalCita.motivo,
        preferred_date: modalCita.fecha,
        preferred_time: modalCita.horario || null,
        notes: modalCita.comentario.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { modalCita.abierto = false; modalCita.comentario = ''; modalCita.fecha = ''; modalCita.horario = ''; },
        onError: (errores) => { modalCita.error = Object.values(errores)[0] ?? 'No se pudo enviar la solicitud.'; },
        onFinish: () => { modalCita.enviando = false; },
    });
}

// Estado local de aceptar/rechazar por cotización (solo en pantalla)
const decision = reactive({});
function estado(id) {
    if (!decision[id]) {
        decision[id] = { rechazando: false, nombre: '', acepto: false, motivo: '', enviando: false, error: '' };
    }
    return decision[id];
}

function enviar(q, tipo) {
    const e = estado(q.id);
    e.enviando = true;
    e.error = '';
    const datos = tipo === 'aceptar'
        ? { nombre: e.nombre.trim(), acepto: e.acepto, hash: q.hash }
        : { motivo: e.motivo.trim() };
    const ruta = tipo === 'aceptar' ? 'tracking.quote.accept' : 'tracking.quote.reject';
    router.post(route(ruta, { token: props.token, quote: q.id }), datos, {
        preserveScroll: true,
        onError: (errores) => { e.error = Object.values(errores)[0] ?? 'No se pudo completar la acción.'; },
        onFinish: () => { e.enviando = false; },
    });
}

const quoteStatusClass = (status) => ({
    borrador:    'bg-gray-100 text-gray-700',
    enviada:     'bg-amber-100 text-amber-800',
    aprobada:    'bg-green-100 text-green-800',
    rechazada:   'bg-red-100 text-red-800',
    reemplazada: 'bg-gray-200 text-gray-500',
}[status] ?? 'bg-gray-100 text-gray-700');

// En orden cerrada una cotización enviada se muestra como histórica: sin "Pendiente de aprobación"
const enHistorico = (q) => props.orden?.cerrada && q.status === 'enviada';
const claseCotizacion = (q) => enHistorico(q) ? 'bg-gray-100 text-gray-700' : quoteStatusClass(q.status);
const textoCotizacion = (q) => enHistorico(q)
    ? t('seguimiento.quoteStatus.enviadaHistorica')
    : t('seguimiento.quoteStatus.' + q.status);
// Clasificación de cotizaciones: visibles (aprobadas o la enviada vigente) e históricas (colapsadas).
const historialAbierto = ref(false);
// El backend ya entrega las cotizaciones ordenadas por prioridad (enviada vigente, aprobada principal, aprobada adicional).
const cotizacionesVisibles = computed(() => (props.quotes ?? [])
    .filter((q) => q.bloque !== 'historico')
);
const cotizacionesHistoricas = computed(() => (props.quotes ?? []).filter((q) => q.bloque === 'historico'));

</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-800">
        <header class="bg-[#10213E] text-white">
            <div class="max-w-3xl mx-auto px-4 py-5">
                <p class="text-xs uppercase tracking-wider text-blue-200">{{ $t('seguimiento.titulo') }}</p>
                <h1 class="text-xl font-bold">{{ settings?.company_name }}</h1>
            </div>
        </header>

        <main class="max-w-3xl mx-auto px-4 py-6 space-y-6">
            <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-sm text-gray-500" v-if="client_first_name">{{ $t('seguimiento.hola', { nombre: client_first_name }) }}</p>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold">{{ $t('seguimiento.vehiculo') }}</p>
                        <p class="text-lg font-bold">
                            {{ vehicle?.brand }} {{ vehicle?.model }} {{ vehicle?.year }}
                        </p>
                        <p class="text-sm text-gray-500">
                            {{ $t('seguimiento.placas') }}: {{ vehicle?.plate ?? '—' }}
                            <span v-if="vehicle?.color"> · {{ vehicle.color }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400 uppercase font-bold">{{ $t('seguimiento.estado') }}</p>
                        <span class="inline-block mt-1 px-3 py-1 rounded-full text-sm font-bold text-white"
                            :class="orden?.status?.color ?? 'bg-gray-500'">
                            {{ statusLabel }}
                        </span>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-4">
                    {{ $t('seguimiento.folio') }}: {{ orden?.folio }} · {{ $t('seguimiento.ingreso') }}: {{ orden?.entry_date ?? '—' }}
                </p>
            </section>

            <section v-if="timeline?.rechazado" class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-5 text-sm font-bold">
                Esta orden fue rechazada. Comunícate con el taller para más detalles.
            </section>

            <section v-else-if="timeline?.steps" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-sm font-bold uppercase text-gray-500 mb-4">Avance de tu vehículo</h2>
                <ol class="grid grid-cols-5 gap-1 text-center">
                    <li v-for="(s, i) in timeline.steps" :key="s.key" class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                            :class="s.current ? 'bg-[#EE2857] text-white ring-4 ring-red-100' : s.done ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'">
                            <span v-if="s.done">✓</span>
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <p class="text-[11px] mt-2 font-bold leading-tight" :class="s.done || s.current ? 'text-gray-800' : 'text-gray-400'">{{ s.label }}</p>
                    </li>
                </ol>
                <p class="mt-5 text-sm text-gray-700 text-center font-medium">{{ timeline.mensaje }}</p>
                <p v-if="ultima_actualizacion" class="mt-2 text-xs text-gray-400 text-center">Última actualización: {{ ultima_actualizacion }}</p>
            </section>

            <section v-if="avance_fotos?.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-sm font-bold uppercase text-gray-500 mb-4">Fotos del avance</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <figure v-for="f in avance_fotos" :key="f.id" class="rounded-lg overflow-hidden border border-gray-200">
                        <a :href="f.url" target="_blank">
                            <img :src="f.url" alt="Foto del avance" class="w-full h-36 object-cover" />
                        </a>
                        <figcaption class="p-2 text-xs text-gray-600">
                            <span class="font-bold">{{ f.fecha }}</span>
                            <span v-if="f.note"> · {{ f.note }}</span>
                        </figcaption>
                    </figure>
                </div>
            </section>

            <section v-if="pendientes?.length" class="space-y-2">
                <h2 class="text-sm font-bold uppercase text-gray-500">Pendientes</h2>
                <div v-for="(p, i) in pendientes" :key="i"
                    class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900 font-medium">
                    {{ p.texto }}
                </div>
            </section>

            <section v-if="financiero" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-xs text-gray-400 uppercase font-bold">Total</p>
                    <p class="font-black text-[#10213E]">{{ money(financiero.total) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase font-bold">Pagado</p>
                    <p class="font-black text-green-700">{{ money(financiero.pagado) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase font-bold">Saldo</p>
                    <p class="font-black" :class="financiero.saldo > 0 ? 'text-[#EE2857]' : 'text-emerald-700'">
                        {{ financiero.saldo > 0 ? money(financiero.saldo) : 'Liquidado' }}
                    </p>
                </div>
            </section>

            <section>
                <h2 class="text-sm font-bold uppercase text-gray-500 mb-3">{{ $t('seguimiento.cotizaciones') }}</h2>
                <p v-if="orden?.cerrada" class="text-sm text-gray-600 bg-white rounded-xl border border-gray-200 p-4 mb-4">{{ $t('seguimiento.cerradaReferencia') }}</p>

                <p v-if="!quotes?.length" class="text-sm text-gray-400 bg-white rounded-xl border border-dashed border-gray-300 p-6 text-center">
                    {{ $t('seguimiento.sinCotizaciones') }}
                </p>

                <TarjetaCotizacion v-for="q in cotizacionesVisibles" :key="q.id"
                    :q="q" :money="money" :decision="estado(q.id)"
                    :es-actual="q.id === current_quote_id"
                    :puede-decidir="q.bloque === 'visible'"
                    :texto-estado="textoCotizacion(q)" :clase-estado="claseCotizacion(q)"
                    @aceptar="enviar(q, 'aceptar')" @rechazar="enviar(q, 'rechazar')" />

                <div v-if="cotizacionesHistoricas.length" class="mt-2">
                    <button type="button" @click="historialAbierto = !historialAbierto" :aria-expanded="historialAbierto"
                        class="flex w-full min-h-[48px] items-center justify-between rounded-xl border border-gray-300 bg-white px-4 font-bold text-gray-700 hover:bg-gray-50">
                        <span>Historial de cotizaciones ({{ cotizacionesHistoricas.length }})</span>
                        <span aria-hidden="true">{{ historialAbierto ? '−' : '+' }}</span>
                    </button>
                    <div v-if="historialAbierto" class="mt-3">
                        <TarjetaCotizacion v-for="q in cotizacionesHistoricas" :key="q.id"
                            :q="q" :money="money" :decision="estado(q.id)"
                            :puede-decidir="false"
                            :texto-estado="textoCotizacion(q)" :clase-estado="claseCotizacion(q)" />
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-sm font-bold uppercase text-gray-500 mb-1">Necesitas algo más?</h2>
                <p class="text-sm text-gray-600 mb-4">Escríbenos o solicita una cita para tu vehículo.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener"
                        class="min-h-[48px] flex items-center justify-center rounded-lg bg-green-600 text-white font-bold px-4 py-3 hover:bg-green-700">Contactar por WhatsApp</a>
                    <a v-if="settings?.phone" :href="'tel:' + settings.phone"
                        class="min-h-[48px] flex items-center justify-center rounded-lg border border-[#10213E] text-[#10213E] font-bold px-4 py-3 hover:bg-gray-100">Llamar al taller</a>
                    <button v-if="!citaActiva" type="button" @click="abrirCita"
                        class="min-h-[48px] rounded-lg bg-[#10213E] text-white font-bold px-4 py-3 hover:bg-[#1c3660] sm:col-span-2">Solicitar cita</button>
                    <a v-else href="#solicitud-cita"
                        class="min-h-[48px] flex items-center justify-center rounded-lg border border-[#10213E] text-[#10213E] font-bold px-4 py-3 hover:bg-gray-100 sm:col-span-2">Ver solicitud</a>
                </div>
            </section>

            <section v-if="cita" id="solicitud-cita" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 text-sm">
                <h2 class="text-sm font-bold uppercase text-gray-500 mb-2">Solicitud de cita</h2>
                <p class="font-bold text-[#10213E]">{{ estadoCitaTexto[cita.estado] ?? cita.estado }}</p>
                <template v-if="cita.estado === 'confirmada'">
                    <p class="mt-1">Tu cita está confirmada para el {{ fechaLegible(cita.confirmada_fecha) }}<span v-if="cita.confirmada_hora"> a las {{ cita.confirmada_hora }}</span>.</p>
                    <p v-if="cita.nota_taller" class="mt-1 text-gray-600">{{ cita.nota_taller }}</p>
                </template>
                <template v-else-if="cita.estado === 'pendiente'">
                    <p class="mt-1 text-gray-600">Ya tienes una solicitud de cita pendiente. El taller confirmará la fecha y horario.</p>
                    <p class="mt-1 text-gray-500">Fecha solicitada: {{ fechaLegible(cita.fecha) }}</p>
                </template>
            </section>

            <section v-if="recordatorios?.length" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-sm font-bold uppercase text-gray-500 mb-3">Próximo servicio</h2>
                <ul class="space-y-4">
                    <li v-for="(r, i) in recordatorios" :key="i" class="text-sm">
                        <p class="font-bold text-[#10213E]">{{ r.titulo }}</p>
                        <p class="mt-1 text-gray-700">
                            <span v-if="r.fecha">{{ fechaLegible(r.fecha) }}</span>
                            <span v-if="r.fecha && r.kilometraje"> o </span>
                            <span v-if="r.kilometraje">{{ Number(r.kilometraje).toLocaleString('es-MX') }} km</span>
                        </p>
                        <p v-if="r.fecha && r.kilometraje" class="text-xs text-gray-500">Lo que ocurra primero.</p>
                        <p v-if="r.nota" class="mt-1 text-gray-600">{{ r.nota }}</p>
                    </li>
                </ul>
                <button v-if="!citaActiva" type="button" @click="abrirCita"
                    class="mt-4 min-h-[44px] px-4 py-2 rounded-lg bg-[#10213E] text-white font-bold hover:bg-[#1c3660]">Solicitar cita</button>
                <a v-else href="#solicitud-cita" class="mt-4 inline-flex min-h-[44px] items-center px-4 py-2 rounded-lg border border-[#10213E] text-[#10213E] font-bold hover:bg-gray-100">Ver solicitud</a>
            </section>

            <div v-if="modalCita.abierto" class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-4" @click.self="cerrarCita">
                <form @submit.prevent="enviarCita" class="bg-white rounded-xl w-full max-w-md p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-bold text-[#10213E]">Solicitar cita</h3>
                    <label class="block text-sm font-semibold text-gray-700">Motivo
                        <select v-model="modalCita.motivo" required class="mt-1 w-full min-h-[44px] rounded-lg border-gray-300">
                            <option v-for="m in motivosCita" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Fecha preferida
                        <input v-model="modalCita.fecha" type="date" required :min="fechaMinima" class="mt-1 w-full min-h-[44px] rounded-lg border-gray-300">
                    </label>
                    <fieldset class="text-sm font-semibold text-gray-700">
                        <legend class="mb-1">Horario preferido (opcional)</legend>
                        <div class="flex gap-2">
                            <label class="flex-1 min-h-[44px] flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-3">
                                <input type="radio" v-model="modalCita.horario" value="manana"> Mañana
                            </label>
                            <label class="flex-1 min-h-[44px] flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-3">
                                <input type="radio" v-model="modalCita.horario" value="tarde"> Tarde
                            </label>
                        </div>
                    </fieldset>
                    <label class="block text-sm font-semibold text-gray-700">Comentario (opcional)
                        <textarea v-model="modalCita.comentario" rows="3" maxlength="500" class="mt-1 w-full rounded-lg border-gray-300"></textarea>
                    </label>
                    <p v-if="modalCita.error" class="text-sm text-red-700">{{ modalCita.error }}</p>
                    <p class="text-xs text-gray-500">Tu cita no queda reservada. El taller confirmará la fecha y horario.</p>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="cerrarCita" class="min-h-[44px] px-4 rounded-lg border border-gray-300">Cancelar</button>
                        <button type="submit" :disabled="modalCita.enviando" class="min-h-[44px] px-4 rounded-lg bg-[#10213E] text-white font-bold disabled:opacity-50">Enviar solicitud</button>
                    </div>
                </form>
            </div>

            <footer class="text-center text-xs text-gray-400 pb-8">{{ $t('seguimiento.pie') }}</footer>
        </main>
    </div>
</template>
