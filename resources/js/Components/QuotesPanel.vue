<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    orden: Object,
});

const { t } = useI18n();

const quotes = computed(() => props.orden?.quotes ?? []);
// Solo una cotización enviada y sin respuesta bloquea otra del mismo tipo
const enviadaPrincipal = computed(() => quotes.value.some((q) => q.status === 'enviada' && (q.kind ?? 'principal') === 'principal'));
const enviadaAdicional = computed(() => quotes.value.some((q) => q.status === 'enviada' && q.kind === 'adicional'));

const money = (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0));

const trackingUrl = computed(() => `${window.location.origin}/seguimiento/${props.orden?.tracking_token}`);
const copied = ref(false);

const mostrarManual = ref(false);

// Copia con el método que funcione en la página: el moderno (https/localhost)
// o, si no está disponible (http, red local), uno de respaldo con selección de texto.
function copiarRespaldo(texto) {
    const area = document.createElement('textarea');
    area.value = texto;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(area);
    return ok;
}

const copyLink = async () => {
    let ok = false;
    if (window.isSecureContext && navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(trackingUrl.value);
            ok = true;
        } catch (e) {
            ok = false;
        }
    }
    if (!ok) ok = copiarRespaldo(trackingUrl.value);

    if (ok) {
        mostrarManual.value = false;
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } else {
        // Último recurso: el enlace queda seleccionado en un campo visible para copiarlo a mano
        mostrarManual.value = true;
        setTimeout(() => document.getElementById('enlace-seguimiento')?.select(), 50);
    }
};

// Formato WhatsApp de México: 52 + 10 dígitos
const normalizarTelefono = (tel) => {
    const d = (tel ?? '').replace(/\D/g, '');
    if (!d) return null;
    if (d.length === 10) return '52' + d;
    if (d.length === 13 && d.startsWith('521')) return '52' + d.slice(3);
    return d;
};

const clientePhone = computed(() => normalizarTelefono(props.orden?.recepcion?.client?.phone));

const whatsappLink = (quote) => {
    const text = encodeURIComponent(`${t('quotesPanel.waMensaje', { folio: quote.folio })} ${trackingUrl.value}`);
    const tel = clientePhone.value;
    return tel ? `https://wa.me/${tel}?text=${text}` : `https://wa.me/?text=${text}`;
};

const generar = (kind = 'principal') => {
    router.post(route('repair-orders.quotes.store', props.orden.id), { kind }, { preserveScroll: true });
};

const eliminarBorrador = (quote) => {
    if (!confirm('¿Eliminar el borrador ' + quote.folio + '?')) return;
    router.delete(route('repair-orders.quotes.destroy', [props.orden.id, quote.id]), { preserveScroll: true });
};

const marcarEnviada = (quote) => {
    router.patch(route('repair-orders.quotes.send', [props.orden.id, quote.id]), {}, { preserveScroll: true });
};

// Aprobación registrada por el asesor
const approvingId = ref(null);
const approveForm = useForm({ approval_method: 'presencial', approved_by_name: '' });

const abrirAprobar = (quote) => {
    approvingId.value = quote.id;
    approveForm.clearErrors();
};

const aprobar = (quote) => {
    approveForm.patch(route('repair-orders.quotes.approve', [props.orden.id, quote.id]), {
        preserveScroll: true,
        onSuccess: () => { approvingId.value = null; approveForm.reset('approved_by_name'); },
    });
};

const rechazar = (quote) => {
    if (!confirm(t('quotesPanel.rechazarConfirm'))) return;
    router.patch(route('repair-orders.quotes.reject', [props.orden.id, quote.id]), {}, { preserveScroll: true });
};

const statusClass = (s) => ({
    borrador:    'bg-gray-100 text-gray-700',
    enviada:     'bg-amber-100 text-amber-800',
    aprobada:    'bg-green-100 text-green-800',
    rechazada:   'bg-red-100 text-red-800',
    reemplazada: 'bg-gray-200 text-gray-500',
}[s] ?? 'bg-gray-100 text-gray-700');
</script>

<template>
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-xl">
        <div v-if="mostrarManual" class="px-6 py-3 bg-amber-50 border-b border-amber-200">
            <p class="text-xs text-amber-800 font-bold mb-2">Tu navegador no permitió copiar automáticamente. Selecciona el enlace y cópialo con Ctrl + C:</p>
            <input id="enlace-seguimiento" type="text" readonly :value="trackingUrl" @focus="$event.target.select()"
                class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm font-mono bg-white" />
        </div>
        <div class="bg-[#10213E] px-6 py-4 flex flex-wrap justify-between items-center gap-3">
            <h3 class="text-white font-black text-sm uppercase tracking-wider">{{ $t('quotesPanel.titulo') }}</h3>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="copyLink"
                    class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg font-bold text-xs uppercase">
                    {{ copied ? $t('quotesPanel.copiado') : $t('quotesPanel.copiarEnlace') }}
                </button>
                <button v-if="orden.status?.slug !== 'entregado'" type="button" @click="generar('principal')"
                    :disabled="enviadaPrincipal"
                    class="bg-[#EE2857] hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-4 py-2 rounded-lg font-bold text-xs uppercase">
                    {{ $t('quotesPanel.nueva') }}
                </button>
                <button v-if="orden.status?.slug !== 'entregado'" type="button" @click="generar('adicional')"
                    :disabled="enviadaAdicional"
                    class="bg-white/10 hover:bg-white/20 disabled:opacity-40 disabled:cursor-not-allowed text-white px-4 py-2 rounded-lg font-bold text-xs uppercase">
                    Trabajo adicional
                </button>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <p v-if="enviadaPrincipal || enviadaAdicional" class="text-xs text-gray-500">Hay una cotización enviada esperando respuesta del cliente. Espera su respuesta antes de crear otra del mismo tipo.</p>
            <p v-if="!clientePhone" class="text-xs text-amber-700">El cliente no tiene teléfono registrado: WhatsApp abrirá sin destinatario. Agrégalo en la recepción.</p>
            <p v-if="!quotes.length" class="text-sm text-gray-400 italic">{{ $t('quotesPanel.vacio') }}</p>

            <article v-for="q in quotes" :key="q.id" class="border rounded-lg p-4"
                :class="q.status === 'aprobada' ? 'border-green-400' : 'border-gray-200'">
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <div>
                        <p class="font-bold text-[#10213E]">{{ q.folio }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $t('quotesPanel.version') }} {{ q.version }} · {{ money(q.total) }}
                            <span v-if="q.valid_until"> · {{ $t('quotesPanel.vigente') }}: {{ String(q.valid_until).slice(0, 10) }}</span>
                        </p>
                        <p v-if="q.status === 'aprobada'" class="text-xs text-green-700 mt-1">
                            {{ $t('quotesPanel.aprobadaPor', { nombre: q.approved_by_name, metodo: $t('quotesPanel.metodo.' + q.approval_method) }) }}
                        </p>
                        <p v-if="q.status === 'rechazada' && q.rejection_reason" class="text-xs text-red-700 mt-1">
                            {{ q.rejection_reason }}
                        </p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="statusClass(q.status)">
                        {{ $t('quotesPanel.estado.' + q.status) }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                    <button v-if="q.status === 'borrador'" type="button" @click="eliminarBorrador(q)"
                        class="px-3 py-1.5 rounded border border-red-300 text-red-700">Eliminar</button>
                    <button v-if="q.status === 'borrador'" type="button" @click="marcarEnviada(q)"
                        class="px-3 py-1.5 rounded bg-[#10213E] text-white">{{ $t('quotesPanel.marcarEnviada') }}</button>
                    <a v-if="q.status === 'borrador' || q.status === 'enviada'" :href="whatsappLink(q)" target="_blank"
                        class="px-3 py-1.5 rounded bg-green-600 text-white">{{ $t('quotesPanel.enviarWhatsapp') }}</a>
                    <button v-if="q.status === 'enviada'" type="button" @click="abrirAprobar(q)"
                        class="px-3 py-1.5 rounded bg-green-700 text-white">{{ $t('quotesPanel.registrarAprobacion') }}</button>
                    <button v-if="q.status === 'enviada'" type="button" @click="rechazar(q)"
                        class="px-3 py-1.5 rounded bg-red-100 text-red-700">{{ $t('quotesPanel.registrarRechazo') }}</button>
                    <a :href="route('repair-orders.quotes.pdf', { order: orden.id, quote: q.id })" target="_blank"
                        class="px-3 py-1.5 rounded border border-gray-300 text-gray-700">{{ $t('quotesPanel.pdf') }}</a>
                </div>

                <form v-if="approvingId === q.id" @submit.prevent="aprobar(q)"
                    class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 bg-gray-50 p-3 rounded">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('quotesPanel.metodoLabel') }}</label>
                        <select v-model="approveForm.approval_method" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="presencial">{{ $t('quotesPanel.metodo.presencial') }}</option>
                            <option value="telefono">{{ $t('quotesPanel.metodo.telefono') }}</option>
                            <option value="whatsapp">{{ $t('quotesPanel.metodo.whatsapp') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ $t('quotesPanel.quienLabel') }}</label>
                        <input v-model="approveForm.approved_by_name" type="text" maxlength="120"
                            class="w-full rounded-md border-gray-300 text-sm" :placeholder="$t('quotesPanel.quienPh')" />
                        <p v-if="approveForm.errors.approved_by_name" class="text-xs text-red-600 mt-1">{{ approveForm.errors.approved_by_name }}</p>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" :disabled="approveForm.processing"
                            class="px-4 py-2 rounded bg-green-700 text-white text-xs font-bold uppercase">{{ $t('quotesPanel.guardar') }}</button>
                        <button type="button" @click="approvingId = null"
                            class="px-4 py-2 rounded text-gray-500 text-xs font-bold uppercase">{{ $t('quotesPanel.cancelar') }}</button>
                    </div>
                </form>
            </article>
        </div>
    </div>
</template>
