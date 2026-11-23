<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseBadge from '@/Components/UI/BaseBadge.vue';

const props = defineProps({
    orden: Object,
});

const { t, locale } = useI18n();
const page = usePage();

// Estado real de la orden y de sus cotizaciones: lo decide el backend. Aquí solo se refleja.
const cerrada = computed(() => props.orden?.esta_cerrada === true);
const quotes = computed(() => props.orden?.quotes ?? []);
const porVersion = (a, b) => b.version - a.version;
const principales = computed(() => quotes.value.filter((q) => q.kind === 'principal').sort(porVersion));
const adicionales = computed(() => quotes.value.filter((q) => q.kind === 'adicional').sort(porVersion));
const hayPrincipalAprobada = computed(() => principales.value.some((q) => q.status === 'aprobada'));
const enviadaPrincipal = computed(() => principales.value.some((q) => q.status === 'enviada'));
const enviadaAdicional = computed(() => adicionales.value.some((q) => q.status === 'enviada'));
// Adicional: solo tiene sentido si hay conceptos no autorizados (flag derivado en backend).
const sinConceptosNuevos = computed(() => !(props.orden?.items ?? []).some((i) => !i.autorizado));

const grupos = computed(() => [
    { clave: 'principal', titulo: t('quotesPanel.secPrincipal'), vacio: t('quotesPanel.sinPrincipal'), lista: principales.value },
    { clave: 'adicional', titulo: t('quotesPanel.secAdicionales'), vacio: t('quotesPanel.sinAdicionales'), lista: adicionales.value },
]);

// Errores del servidor de cotizaciones: se muestran junto a las acciones.
const erroresServidor = computed(() => {
    const e = page.props.errors ?? {};
    return ['status', 'items', 'quote'].map((k) => e[k]).filter((m) => typeof m === 'string' && m.length > 0);
});

const variante = { borrador: 'neutral', enviada: 'info', aprobada: 'success', rechazada: 'danger', reemplazada: 'neutral' };

const localeFecha = () => (locale.value === 'en' ? 'en-US' : 'es-MX');
const money = (v) => '$' + Number(v ?? 0).toLocaleString(localeFecha(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fecha = (v) => (v ? new Date(v).toLocaleDateString(localeFecha(), { day: '2-digit', month: 'short', year: 'numeric' }) : null);

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
    if (!confirm(t('quotesPanel.eliminarConfirm', { folio: quote.folio }))) return;
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
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-xl border border-line bg-surface p-4 md:p-5">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('quotesPanel.titulo') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="copyLink"
                        class="ds-focus rounded-lg border border-line px-4 py-2 text-ds-caption font-semibold text-ink transition hover:border-navy">
                        {{ copied ? $t('quotesPanel.copiado') : $t('quotesPanel.copiarEnlace') }}
                    </button>
                    <template v-if="!cerrada">
                        <button v-if="!hayPrincipalAprobada" type="button" @click="generar('principal')" :disabled="enviadaPrincipal"
                            class="ds-focus rounded-lg bg-navy px-4 py-2 text-ds-caption font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">
                            {{ $t('quotesPanel.nueva') }}
                        </button>
                        <button type="button" @click="generar('adicional')" :disabled="enviadaAdicional || sinConceptosNuevos"
                            class="ds-focus rounded-lg border border-line px-4 py-2 text-ds-caption font-semibold text-ink transition hover:border-navy disabled:cursor-not-allowed disabled:opacity-40">
                            {{ $t('quotesPanel.btnAdicional') }}
                        </button>
                    </template>
                </div>
            </header>

            <p v-if="cerrada" class="mt-3 text-ds-caption text-ink-muted">{{ $t('quotesPanel.cerrada') }}</p>
            <p v-if="!cerrada && (enviadaPrincipal || enviadaAdicional)" class="mt-3 text-ds-caption text-ink-muted">{{ $t('quotesPanel.esperandoRespuesta') }}</p>
            <p v-if="!cerrada && sinConceptosNuevos" class="mt-3 text-ds-caption text-ink-muted">{{ $t('quotesPanel.sinConceptosNuevos') }}</p>
            <p v-if="!cerrada && !clientePhone" class="mt-3 text-ds-caption text-amber-700">{{ $t('quotesPanel.sinTelefono') }}</p>

            <div v-if="erroresServidor.length" class="mt-3 space-y-1">
                <p v-for="(msg, i) in erroresServidor" :key="i" class="text-ds-caption font-semibold text-red-600">{{ msg }}</p>
            </div>

            <div v-if="mostrarManual" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3">
                <p class="mb-2 text-ds-caption font-semibold text-amber-800">{{ $t('quotesPanel.navegadorNoCopia') }}</p>
                <input id="enlace-seguimiento" type="text" readonly :value="trackingUrl" @focus="$event.target.select()"
                    class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 font-mono text-ds-caption" />
            </div>
        </section>

        <section v-for="g in grupos" :key="g.clave" class="space-y-3">
            <h3 class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ g.titulo }}</h3>
            <p v-if="!g.lista.length" class="rounded-xl border border-dashed border-line p-4 text-center text-ds-body text-ink-muted">{{ g.vacio }}</p>

            <article v-for="q in g.lista" :key="q.id"
                class="rounded-xl border border-line bg-surface p-4"
                :class="q.status === 'reemplazada' ? 'opacity-70' : ''">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="break-words font-semibold text-ink">{{ q.folio }}</p>
                        <p class="text-ds-caption text-ink-muted">
                            {{ $t('quotesPanel.tipo.' + q.kind) }} · {{ $t('quotesPanel.version') }} {{ q.version }}
                        </p>
                    </div>
                    <BaseBadge :variant="variante[q.status] ?? 'neutral'">{{ $t('quotesPanel.estado.' + q.status) }}</BaseBadge>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-ds-caption sm:grid-cols-4">
                    <div>
                        <dt class="text-ink-muted">{{ $t('quotesPanel.total') }}</dt>
                        <dd class="font-semibold tabular-nums text-ink">{{ money(q.total) }}</dd>
                    </div>
                    <div v-if="q.created_at">
                        <dt class="text-ink-muted">{{ $t('quotesPanel.creada') }}</dt>
                        <dd class="text-ink">{{ fecha(q.created_at) }}</dd>
                    </div>
                    <div v-if="q.sent_at">
                        <dt class="text-ink-muted">{{ $t('quotesPanel.enviadaEl') }}</dt>
                        <dd class="text-ink">{{ fecha(q.sent_at) }}</dd>
                    </div>
                    <div v-if="q.valid_until && (q.status === 'borrador' || q.status === 'enviada')">
                        <dt class="text-ink-muted">{{ $t('quotesPanel.vigente') }}</dt>
                        <dd class="text-ink">{{ fecha(q.valid_until) }}</dd>
                    </div>
                    <div v-if="q.approved_at">
                        <dt class="text-ink-muted">{{ $t('quotesPanel.aprobadaEl') }}</dt>
                        <dd class="text-ink">{{ fecha(q.approved_at) }}</dd>
                    </div>
                </dl>

                <p v-if="q.status === 'aprobada'" class="mt-2 text-ds-caption text-ink">
                    {{ $t('quotesPanel.aprobadaPor', { nombre: q.approved_by_name, metodo: $t('quotesPanel.metodo.' + q.approval_method) }) }}
                </p>
                <p v-if="q.status === 'rechazada' && q.rejection_reason" class="mt-2 text-ds-caption text-red-700">{{ q.rejection_reason }}</p>

                <div class="mt-4 flex flex-wrap gap-2 text-ds-caption font-semibold">
                    <template v-if="!cerrada">
                        <button v-if="q.status === 'borrador'" type="button" @click="eliminarBorrador(q)"
                            class="ds-focus rounded-lg border border-line px-3 py-1.5 text-red-700 transition hover:border-red-300">{{ $t('quotesPanel.eliminar') }}</button>
                        <button v-if="q.status === 'borrador'" type="button" @click="marcarEnviada(q)"
                            class="ds-focus rounded-lg bg-navy px-3 py-1.5 text-white transition hover:opacity-90">{{ $t('quotesPanel.marcarEnviada') }}</button>
                        <a v-if="q.status === 'borrador' || q.status === 'enviada'" :href="whatsappLink(q)" target="_blank" rel="noopener"
                            class="ds-focus rounded-lg border border-line px-3 py-1.5 text-ink transition hover:border-navy">{{ $t('quotesPanel.enviarWhatsapp') }}</a>
                        <button v-if="q.status === 'enviada'" type="button" @click="abrirAprobar(q)"
                            class="ds-focus rounded-lg bg-navy px-3 py-1.5 text-white transition hover:opacity-90">{{ $t('quotesPanel.registrarAprobacion') }}</button>
                        <button v-if="q.status === 'enviada'" type="button" @click="rechazar(q)"
                            class="ds-focus rounded-lg border border-line px-3 py-1.5 text-red-700 transition hover:border-red-300">{{ $t('quotesPanel.registrarRechazo') }}</button>
                    </template>
                    <a :href="route('repair-orders.quotes.pdf', { order: orden.id, quote: q.id })" target="_blank" rel="noopener"
                        class="ds-focus rounded-lg border border-line px-3 py-1.5 text-ink transition hover:border-navy">{{ $t('quotesPanel.descargarPdf') }}</a>
                </div>

                <form v-if="approvingId === q.id" @submit.prevent="aprobar(q)"
                    class="mt-4 grid grid-cols-1 gap-3 rounded-lg bg-surface-2 p-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('quotesPanel.metodoLabel') }}</label>
                        <select v-model="approveForm.approval_method" class="w-full rounded-md border-line text-ds-body">
                            <option value="presencial">{{ $t('quotesPanel.metodo.presencial') }}</option>
                            <option value="telefono">{{ $t('quotesPanel.metodo.telefono') }}</option>
                            <option value="whatsapp">{{ $t('quotesPanel.metodo.whatsapp') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('quotesPanel.quienLabel') }}</label>
                        <input v-model="approveForm.approved_by_name" type="text" maxlength="120"
                            class="w-full rounded-md border-line text-ds-body" :placeholder="$t('quotesPanel.quienPh')" />
                        <p v-if="approveForm.errors.approved_by_name" class="mt-1 text-ds-caption text-red-600">{{ approveForm.errors.approved_by_name }}</p>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" :disabled="approveForm.processing"
                            class="ds-focus rounded-lg bg-navy px-4 py-2 text-ds-caption font-semibold text-white transition hover:opacity-90 disabled:opacity-40">{{ $t('quotesPanel.guardar') }}</button>
                        <button type="button" @click="approvingId = null"
                            class="ds-focus rounded-lg px-4 py-2 text-ds-caption font-semibold text-ink-muted transition hover:text-ink">{{ $t('quotesPanel.cancelar') }}</button>
                    </div>
                </form>
            </article>
        </section>
    </div>
</template>
