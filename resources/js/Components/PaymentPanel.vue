<script setup>
import { computed, ref } from 'vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    orden:     { type: Object, required: true },
    breakdown: { type: Object, required: true },
});

const totalPagado = computed(() =>
    (props.orden.payments ?? []).reduce((s, p) => s + Number(p.amount), 0)
);

const saldoPendiente = computed(() =>
    Number(props.breakdown.total) - totalPagado.value
);

const form = useForm({
    amount: '',
    payment_method: 'Cash',
    notes: '',
    quote_id: '',
});

// Cotizaciones con su monto, anticipo recibido y saldo (solo las enviadas, aprobadas o rechazadas)
const ESTADOS_COTIZACION = { enviada: 'Enviada', aprobada: 'Aprobada', rechazada: 'Rechazada' };
const estadoCotizacion = (s) => ESTADOS_COTIZACION[s] ?? s;

const cotizaciones = computed(() => (props.orden.quotes ?? [])
    .filter(q => ['enviada', 'aprobada', 'rechazada'].includes(q.status))
    .map(q => {
        const total = Number(q.total);
        const pagado = (props.orden.payments ?? [])
            .filter(p => Number(p.quote_id) === q.id)
            .reduce((s, p) => s + Number(p.amount), 0);
        return { ...q, total, pagado, saldo: total - pagado };
    })
);

const pagoSinCotizacion = computed(() =>
    (props.orden.payments ?? [])
        .filter(p => !p.quote_id)
        .reduce((s, p) => s + Number(p.amount), 0)
);

const etiquetaCotizacion = (c) => `${c.folio} · ${c.kind === 'adicional' ? 'Adicional' : 'Principal'} · $${c.total.toFixed(2)}`;

function submit() {
    form.post(route('payments.store', props.orden.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const confirmPay = ref({ show: false, id: null });
function eliminar(id) { confirmPay.value = { show: true, id }; }
function doEliminar() {
    router.delete(route('payments.destroy', { order: props.orden.id, payment: confirmPay.value.id }), { preserveScroll: true });
    confirmPay.value = { show: false, id: null };
}

const metodoBadge = {
    'Cash':          'bg-green-100 text-green-700',
    'Card':          'bg-blue-100 text-blue-700',
    'Transfer':      'bg-purple-100 text-purple-700',
    // Legacy keys
    'Efectivo':      'bg-green-100 text-green-700',
    'Tarjeta':       'bg-blue-100 text-blue-700',
    'Transferencia': 'bg-purple-100 text-purple-700',
};

// Traduce el valor almacenado en BD al idioma actual
function metodLabel(method) {
    const map = {
        'Cash':          t('payments.efectivo'),
        'Card':          t('payments.tarjeta'),
        'Transfer':      t('payments.transferencia'),
        'Efectivo':      t('payments.efectivo'),
        'Tarjeta':       t('payments.tarjeta'),
        'Transferencia': t('payments.transferencia'),
    };
    return map[method] ?? method;
}
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#EE2857] p-6">
        <h3 class="text-sm font-black text-[#10213E] uppercase tracking-widest border-b pb-2 mb-5">
            💳 {{ t('payments.titulo') }}
        </h3>

        <!-- Financial summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <p class="text-xs text-gray-400 uppercase font-bold mb-1">{{ t('payments.totalOrden') }}</p>
                <p class="text-lg font-black text-[#10213E]">${{ Number(breakdown.total).toFixed(2) }}</p>
            </div>
            <div class="bg-green-50 rounded-lg p-3 text-center">
                <p class="text-xs text-gray-400 uppercase font-bold mb-1">{{ t('payments.totalPagado') }}</p>
                <p class="text-lg font-black text-green-700">${{ totalPagado.toFixed(2) }}</p>
            </div>
            <div class="rounded-lg p-3 text-center" :class="saldoPendiente <= 0 ? 'bg-emerald-100' : 'bg-red-50'">
                <p class="text-xs text-gray-400 uppercase font-bold mb-1">{{ t('payments.saldoPendiente') }}</p>
                <p class="text-lg font-black" :class="saldoPendiente <= 0 ? 'text-emerald-700' : 'text-[#EE2857]'">
                    {{ saldoPendiente <= 0 ? t('payments.liquidado') : '$' + saldoPendiente.toFixed(2) }}
                </p>
            </div>
        </div>

        <!-- Cotizaciones con monto y anticipo -->
        <div v-if="cotizaciones.length" class="mb-5">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Cotizaciones</p>
            <div v-for="c in cotizaciones" :key="c.id"
                 class="flex flex-wrap justify-between items-center gap-3 p-3 rounded-lg bg-gray-50 mb-2">
                <div>
                    <p class="font-bold text-[#10213E]">{{ c.folio }} — {{ c.kind === 'adicional' ? 'Trabajo adicional' : 'Principal' }}</p>
                    <p class="text-xs text-gray-400 uppercase">{{ estadoCotizacion(c.status) }}</p>
                </div>
                <div class="text-right text-sm space-y-0.5">
                    <p>Monto: <span class="font-black text-[#10213E]">${{ c.total.toFixed(2) }}</span></p>
                    <p>Anticipo: <span class="font-bold text-green-700">${{ c.pagado.toFixed(2) }}</span></p>
                    <p>Saldo:
                        <span class="font-bold" :class="c.saldo <= 0 ? 'text-emerald-700' : 'text-[#EE2857]'">
                            {{ c.saldo <= 0 ? 'Liquidada' : '$' + c.saldo.toFixed(2) }}
                        </span>
                    </p>
                </div>
            </div>
            <p v-if="pagoSinCotizacion > 0" class="text-xs text-gray-500">
                Anticipo general sin asignar a una cotización: ${{ pagoSinCotizacion.toFixed(2) }}
            </p>
        </div>

        <!-- Payment history -->
        <div v-if="orden.payments && orden.payments.length > 0" class="mb-5 space-y-2">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">{{ t('payments.historialPagos') }}</p>
            <div v-for="p in orden.payments" :key="p.id"
                class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2.5">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold px-2 py-1 rounded" :class="metodoBadge[p.payment_method] ?? 'bg-gray-100 text-gray-600'">
                        {{ metodLabel(p.payment_method) }}
                    </span>
                    <div>
                        <p class="text-sm font-black text-[#10213E]">${{ Number(p.amount).toFixed(2) }}</p>
                        <p v-if="p.notes" class="text-xs text-gray-500">{{ p.notes }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">
                        {{ new Date(p.created_at).toLocaleDateString('en-US') }}
                    </span>
                    <a :href="route('payments.ticket', { order: orden.id, payment: p.id })" target="_blank"
                        class="text-xs font-bold text-[#10213E] hover:underline">🧾 Ticket</a>
                    <button @click="eliminar(p.id)"
                        class="text-gray-300 hover:text-[#EE2857] transition font-bold text-sm">✕</button>
                </div>
            </div>
        </div>

        <div v-else class="mb-5 text-center py-4 text-gray-400 text-sm italic bg-gray-50 rounded-lg">
            {{ t('payments.sinPagos') }}
        </div>

        <!-- New payment form -->
        <div class="border-t border-gray-100 pt-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">{{ t('payments.registrarPago') }}</p>
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Aplicar a</label>
                <select v-model="form.quote_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#EE2857] focus:outline-none">
                    <option value="">Anticipo general (sin cotización)</option>
                    <option v-for="c in cotizaciones" :key="c.id" :value="c.id">{{ etiquetaCotizacion(c) }}</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('payments.monto') }}</label>
                    <input v-model="form.amount" type="number" step="0.01" min="0.01"
                        placeholder="0.00"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                    <p v-if="form.errors.amount" class="text-red-500 text-xs mt-1">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('payments.metodo') }}</label>
                    <select v-model="form.payment_method"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                        <option value="Cash">{{ t('payments.efectivo') }}</option>
                        <option value="Card">{{ t('payments.tarjeta') }}</option>
                        <option value="Transfer">{{ t('payments.transferencia') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('payments.notas') }}</label>
                    <input v-model="form.notes" type="text" :placeholder="t('payments.notasPh')"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                </div>
            </div>
            <div class="flex justify-end mt-3">
                <button @click="submit" :disabled="form.processing || !form.amount"
                    class="bg-[#EE2857] hover:bg-red-700 disabled:opacity-40 text-white font-black text-xs uppercase px-6 py-2.5 rounded-lg transition shadow-sm">
                    {{ form.processing ? t('payments.registrando') : t('payments.registrarBtn') }}
                </button>
            </div>
        </div>
    </div>

    <ConfirmModal :show="confirmPay.show"
        :title="t('payments.eliminarPago')"
        :message="t('payments.eliminarPagoMsg')"
        confirm-text="OK"
        @confirm="doEliminar"
        @cancel="confirmPay.show = false" />
</template>
