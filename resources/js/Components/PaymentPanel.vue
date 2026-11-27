<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';

const props = defineProps({
    orden:  { type: Object, required: true },
    estado: { type: Object, required: true },
});

const { t, locale } = useI18n();

// Todas las cifras vienen del servidor (EstadoFinancieroAction). Aquí solo se formatean y se muestran.
const cerrada = computed(() => props.orden?.esta_cerrada === true);
const pagos = computed(() => props.orden?.payments ?? []);
const cotizaciones = computed(() => props.estado?.cotizaciones ?? []);
const autorizado = computed(() => Number(props.estado?.total_autorizado ?? 0));
const saldo = computed(() => Number(props.estado?.saldo_autorizado ?? 0));
// Sin trabajo autorizado no hay saldo exigible: no se muestra "Liquidado" como si estuviera pagado.
const saldoTexto = computed(() => {
    if (autorizado.value <= 0) return t('payments.sinAutorizado');
    return saldo.value > 0 ? dinero(saldo.value) : t('expediente.liquidado');
});
const saldoClase = computed(() => {
    if (autorizado.value <= 0) return 'text-ink-muted';
    return saldo.value > 0 ? 'text-danger-text' : 'text-success-text';
});

const localeFecha = () => (locale.value === 'en' ? 'en-US' : 'es-MX');
const dinero = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n ?? 0));
const fecha = (iso) => (iso
    ? new Date(iso).toLocaleDateString(localeFecha(), { day: '2-digit', month: 'short', year: 'numeric' })
    : '—');

// Métodos reales del backend (Cash/Card/Transfer). Las claves en español son legado y se muestran igual.
const etiquetaMetodo = (m) => ({
    Cash: t('payments.efectivo'), Efectivo: t('payments.efectivo'),
    Card: t('payments.tarjeta'), Tarjeta: t('payments.tarjeta'),
    Transfer: t('payments.transferencia'), Transferencia: t('payments.transferencia'),
}[m] ?? m);

const varianteMetodo = (m) => ({
    Cash: 'success', Efectivo: 'success',
    Card: 'info', Tarjeta: 'info',
    Transfer: 'neutral', Transferencia: 'neutral',
}[m] ?? 'neutral');

const varianteCotizacion = (s) => ({ enviada: 'info', aprobada: 'success', rechazada: 'danger' }[s] ?? 'neutral');
const tipoCotizacion = (kind) => (kind === 'adicional' ? t('payments.tipo.adicional') : t('payments.tipo.principal'));

// Solo cotizaciones aprobadas con saldo de referencia > 0 admiten pagos asociados nuevos (backend revalida).
const cotizacionesAplicables = computed(() =>
    cotizaciones.value.filter((c) => c.status === 'aprobada' && Number(c.saldo) > 0));
const cotizacionElegida = computed(() =>
    cotizacionesAplicables.value.find((c) => String(c.id) === String(form.quote_id)) ?? null);
const maximoRegistrable = computed(() => (cotizacionElegida.value
    ? Math.min(saldo.value, Number(cotizacionElegida.value.saldo))
    : saldo.value));

// Pago general (quote_id null) es un pago válido: se muestra con su propia etiqueta, sin categoría persistente.
const asociado = (p) => {
    if (p.quote_id === null || p.quote_id === undefined) return t('payments.pagoGeneral');
    const c = cotizaciones.value.find((q) => q.id === p.quote_id);
    return c ? `${c.folio} · ${tipoCotizacion(c.kind)}` : t('payments.cotizacionAsociada');
};
const etiquetaCotizacion = (c) => `${c.folio} · ${t('payments.saldoRef')} ${dinero(c.saldo)}`;

// Espejo de una regla del backend para no ofrecer un formulario inútil; el servidor sigue siendo la autoridad.
const liquidada = computed(() => autorizado.value > 0 && saldo.value <= 0);

const form = useForm({
    amount: '',
    payment_method: 'Cash',
    notes: '',
    quote_id: '',
});

const puedeRegistrar = computed(() => !form.processing && !!form.amount && !liquidada.value);

function submit() {
    form.post(route('payments.store', props.orden.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const confirmacion = ref({ visible: false, id: null });
function pedirEliminar(id) {
    confirmacion.value = { visible: true, id };
}
function eliminarConfirmado() {
    router.delete(route('payments.destroy', { order: props.orden.id, payment: confirmacion.value.id }), {
        preserveScroll: true,
    });
    confirmacion.value = { visible: false, id: null };
}
</script>

<template>
    <section class="space-y-6 rounded-xl border border-line bg-surface p-5" aria-labelledby="pagos-titulo">
        <div>
            <h2 id="pagos-titulo" class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">
                {{ t('payments.titulo') }}
            </h2>
            <p v-if="cerrada" class="mt-2 text-ds-caption text-ink-secondary">{{ t('payments.cerradaLectura') }}</p>
        </div>

        <!-- Resumen financiero: fuente única del servidor -->
        <div class="space-y-3">
            <h3 class="text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">{{ t('expediente.financiero') }}</h3>
            <dl class="grid grid-cols-1 gap-4 text-ds-body sm:grid-cols-3">
                <div>
                    <dt class="text-ds-caption text-ink-muted">{{ t('expediente.autorizado') }}</dt>
                    <dd class="font-semibold tabular-nums text-ink">{{ dinero(estado.total_autorizado) }}</dd>
                </div>
                <div>
                    <dt class="text-ds-caption text-ink-muted">{{ t('expediente.pagado') }}</dt>
                    <dd class="font-semibold tabular-nums text-ink">{{ dinero(estado.total_pagado) }}</dd>
                </div>
                <div>
                    <dt class="text-ds-caption text-ink-muted">{{ t('expediente.saldo') }}</dt>
                    <dd class="font-semibold tabular-nums" :class="saldoClase">{{ saldoTexto }}</dd>
                </div>
            </dl>
            <p v-if="Number(estado.pendiente_autorizacion) > 0"
               class="rounded-lg bg-warning-soft px-3 py-2 text-ds-caption text-warning-text">
                {{ t('payments.pendienteNota', { monto: dinero(estado.pendiente_autorizacion) }) }}
            </p>
            <p v-if="Number(estado.anticipo_previo) > 0"
               class="rounded-lg bg-surface-2 px-3 py-2 text-ds-caption text-ink-secondary">
                {{ t('payments.anticipoPrevio', { monto: dinero(estado.anticipo_previo) }) }}
            </p>
            <p v-if="Number(estado.sobrepago) > 0"
               class="rounded-lg bg-danger-soft px-3 py-2 text-ds-caption text-danger-text">
                {{ t('payments.excedente', { monto: dinero(estado.sobrepago) }) }}
            </p>
        </div>

        <!-- Cotizaciones de referencia (solo lectura) -->
        <div v-if="cotizaciones.length" class="space-y-3">
            <h3 class="text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">{{ t('payments.cotizacionesRef') }}</h3>
            <ul class="divide-y divide-line rounded-lg border border-line">
                <li v-for="c in cotizaciones" :key="c.id"
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-ink">
                            {{ c.folio }} <span class="font-normal text-ink-muted">· {{ tipoCotizacion(c.kind) }}</span>
                        </p>
                        <div class="mt-1">
                            <BaseBadge :variant="varianteCotizacion(c.status)">{{ t('payments.estadoCotizacion.' + c.status) }}</BaseBadge>
                        </div>
                    </div>
                    <dl class="grid grid-cols-3 gap-x-6 text-right text-ds-caption tabular-nums">
                        <div>
                            <dt class="text-ink-muted">{{ t('expediente.autorizado') }}</dt>
                            <dd class="font-semibold text-ink">{{ dinero(c.total) }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-muted">{{ t('payments.pagosAsociados') }}</dt>
                            <dd class="font-semibold text-ink">{{ dinero(c.pagado) }}</dd>
                        </div>
                        <div v-if="c.status === 'aprobada'">
                            <dt class="text-ink-muted">{{ t('payments.saldoRef') }}</dt>
                            <dd class="font-semibold" :class="Number(c.saldo) > 0 ? 'text-danger-text' : 'text-success-text'">
                                {{ Number(c.saldo) > 0 ? dinero(c.saldo) : t('expediente.liquidado') }}
                            </dd>
                        </div>
                    </dl>
                </li>
            </ul>
        </div>

        <!-- Historial de pagos -->
        <div class="space-y-3">
            <h3 class="text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">{{ t('payments.historialPagos') }}</h3>
            <ul v-if="pagos.length" class="divide-y divide-line rounded-lg border border-line">
                <li v-for="p in pagos" :key="p.id" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <BaseBadge :variant="varianteMetodo(p.payment_method)">{{ etiquetaMetodo(p.payment_method) }}</BaseBadge>
                        <div class="min-w-0">
                            <p class="font-semibold tabular-nums text-ink">{{ dinero(p.amount) }}</p>
                            <p class="text-ds-caption text-ink-muted">{{ asociado(p) }} · {{ fecha(p.created_at) }}</p>
                            <p v-if="p.notes" class="break-words text-ds-caption text-ink-secondary">{{ p.notes }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-ds-caption font-semibold">
                        <a :href="route('payments.ticket', { order: orden.id, payment: p.id })" target="_blank" rel="noopener"
                           class="text-ink underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ds-focus">
                            {{ t('payments.ticket') }}
                        </a>
                        <button v-if="!cerrada" type="button" @click="pedirEliminar(p.id)"
                                class="text-danger-text underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ds-focus">
                            {{ t('payments.eliminar') }}
                        </button>
                    </div>
                </li>
            </ul>
            <p v-else class="rounded-lg bg-surface-2 px-4 py-6 text-center text-ds-body text-ink-muted">
                {{ t('payments.sinPagos') }}
            </p>
        </div>

        <!-- Registrar pago: solo en órdenes abiertas -->
        <form v-if="!cerrada" class="space-y-4 border-t border-line pt-5" novalidate @submit.prevent="submit">
            <h3 class="text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">{{ t('payments.registrarPago') }}</h3>

            <p v-if="liquidada" class="rounded-lg bg-success-soft px-3 py-2 text-ds-caption text-success-text">
                {{ t('payments.liquidadoNota') }}
            </p>

            <div class="space-y-1">
                <label for="pago-cotizacion" class="block text-ds-caption font-semibold text-ink-secondary">{{ t('payments.asociarA') }}</label>
                <select id="pago-cotizacion" v-model="form.quote_id"
                        class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-ds-focus">
                    <option value="">{{ t('payments.pagoGeneral') }}</option>
                    <option v-for="c in cotizacionesAplicables" :key="c.id" :value="c.id">{{ etiquetaCotizacion(c) }}</option>
                </select>
                <p v-if="form.errors.quote_id" class="text-ds-caption text-danger-text">{{ form.errors.quote_id }}</p>
                <p v-if="autorizado > 0 || cotizacionElegida" class="text-ds-caption text-ink-muted">
                    {{ t('payments.maximo') }}: <span class="font-semibold text-ink">{{ dinero(maximoRegistrable) }}</span>
                    <span v-if="cotizacionElegida"> {{ t('payments.limiteCotizacion') }}</span>
                </p>
                <p v-else class="text-ds-caption text-ink-muted">{{ t('payments.sinAutorizacion') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="space-y-1">
                    <label for="pago-monto" class="block text-ds-caption font-semibold text-ink-secondary">{{ t('payments.monto') }}</label>
                    <input id="pago-monto" v-model="form.amount" type="number" step="0.01" min="0.01" placeholder="0.00"
                           :disabled="liquidada"
                           class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-ds-focus disabled:opacity-50" />
                    <p v-if="form.errors.amount" class="text-ds-caption text-danger-text">{{ form.errors.amount }}</p>
                </div>
                <div class="space-y-1">
                    <label for="pago-metodo" class="block text-ds-caption font-semibold text-ink-secondary">{{ t('payments.metodo') }}</label>
                    <select id="pago-metodo" v-model="form.payment_method"
                            class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-ds-focus">
                        <option value="Cash">{{ t('payments.efectivo') }}</option>
                        <option value="Card">{{ t('payments.tarjeta') }}</option>
                        <option value="Transfer">{{ t('payments.transferencia') }}</option>
                    </select>
                    <p v-if="form.errors.payment_method" class="text-ds-caption text-danger-text">{{ form.errors.payment_method }}</p>
                </div>
                <div class="space-y-1">
                    <label for="pago-notas" class="block text-ds-caption font-semibold text-ink-secondary">{{ t('payments.notas') }}</label>
                    <input id="pago-notas" v-model="form.notes" type="text" maxlength="500" :placeholder="t('payments.notasPh')"
                           class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-ds-focus" />
                    <p v-if="form.errors.notes" class="text-ds-caption text-danger-text">{{ form.errors.notes }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" :disabled="!puedeRegistrar"
                        class="rounded-lg bg-navy px-5 py-2.5 text-ds-body font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-ds-focus disabled:opacity-40">
                    {{ form.processing ? t('payments.registrando') : t('payments.registrarBtn') }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmModal :show="confirmacion.visible"
        :title="t('payments.eliminarPago')"
        :message="t('payments.eliminarPagoMsg')"
        :confirm-text="t('payments.confirmarEliminar')"
        @confirm="eliminarConfirmado"
        @cancel="confirmacion.visible = false" />
</template>
