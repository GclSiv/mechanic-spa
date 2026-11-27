<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    q: { type: Object, required: true },
    money: { type: Function, required: true },
    decision: { type: Object, required: true },
    esActual: { type: Boolean, default: false },
    puedeDecidir: { type: Boolean, default: false },
    textoEstado: { type: String, default: '' },
    claseEstado: { type: String, default: '' },
});

defineEmits(['aceptar', 'rechazar']);

// Compacta por defecto: los conceptos se renderizan solo al pedir "Ver detalle".
const detalleAbierto = ref(false);
const numeroConceptos = computed(() => props.q.items?.length ?? 0);
const etiquetaTipo = computed(() => (props.q.kind === 'adicional' ? 'Trabajo adicional' : 'Cotización'));
</script>

<template>
    <article class="mb-4 rounded-xl border bg-white p-4 shadow-sm"
        :class="esActual ? 'border-[#10213E] ring-1 ring-[#10213E]' : 'border-gray-200'">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="font-bold break-all">{{ etiquetaTipo }} {{ q.folio }}</p>
                <p v-if="q.valid_until" class="text-xs text-gray-500">
                    {{ $t('seguimiento.vigente') }}: {{ q.valid_until }}
                    <span v-if="q.expired" class="font-bold text-red-600"> · {{ $t('seguimiento.vencida') }}</span>
                </p>
            </div>
            <span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold" :class="claseEstado">{{ textoEstado }}</span>
        </div>

        <p class="mt-1 text-xs text-gray-500">{{ numeroConceptos }} {{ numeroConceptos === 1 ? 'concepto' : 'conceptos' }}</p>

        <dl class="mt-3 space-y-1.5 text-sm">
            <div class="flex justify-between gap-3"><dt>Refacciones</dt><dd>{{ money(q.parts_subtotal) }}</dd></div>
            <div class="flex justify-between gap-3"><dt>Mano de obra</dt><dd>{{ money(q.labor_subtotal) }}</dd></div>
            <div class="flex justify-between gap-3"><dt>IVA ({{ Number(q.tax_rate) }}%)</dt><dd>{{ money(q.tax) }}</dd></div>
            <div class="flex justify-between gap-3 border-t pt-2 text-base font-bold"><dt>Total</dt><dd>{{ money(q.total) }}</dd></div>
        </dl>

        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button type="button" :aria-expanded="detalleAbierto" @click="detalleAbierto = !detalleAbierto"
                class="min-h-[44px] rounded-lg border border-gray-300 px-4 font-bold text-gray-700 hover:bg-gray-50">
                {{ detalleAbierto ? 'Ocultar detalle' : 'Ver detalle' }}
            </button>
            <a :href="q.pdf_url"
                class="flex min-h-[44px] items-center justify-center rounded-lg bg-[#10213E] px-4 font-bold text-white hover:bg-[#1c3660]">
                {{ $t('seguimiento.descargarPdf') }}
            </a>
        </div>

        <div v-if="detalleAbierto" class="mt-4 border-t pt-3">
            <ul class="divide-y divide-gray-100 text-sm">
                <li v-for="(it, i) in q.items" :key="i" class="flex justify-between gap-3 py-2">
                    <span class="min-w-0">
                        <span class="block text-[10px] font-bold uppercase"
                            :class="it.type === 'labor' ? 'text-blue-600' : 'text-amber-600'">
                            {{ it.type === 'labor' ? $t('seguimiento.manoObra') : $t('seguimiento.refaccion') }}
                        </span>
                        {{ it.description }} <span class="text-gray-500">× {{ Number(it.quantity) }}</span>
                    </span>
                    <span class="whitespace-nowrap">{{ money(it.subtotal) }}</span>
                </li>
            </ul>
            <p v-if="q.notes" class="mt-3 rounded bg-gray-50 p-3 text-sm text-gray-600">{{ q.notes }}</p>
        </div>

        <div v-if="q.status === 'aprobada'" class="mt-4 rounded bg-green-50 p-3 text-sm text-green-800">
            Aceptada<span v-if="q.accepted_name"> por {{ q.accepted_name }}</span><span v-if="q.accepted_at"> el {{ q.accepted_at }}</span>.
        </div>
        <div v-if="q.status === 'rechazada'" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-800">
            Rechazada<span v-if="q.rejection_reason">: {{ q.rejection_reason }}</span>.
        </div>

        <div v-if="puedeDecidir && q.can_accept" class="mt-5 space-y-3 border-t pt-4">
            <template v-if="!decision.rechazando">
                <p class="text-sm font-bold text-gray-800">¿Aceptas esta cotización?</p>
                <input v-model="decision.nombre" type="text" maxlength="120" placeholder="Escribe tu nombre completo"
                    class="min-h-[44px] w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                <label class="flex items-start gap-2 text-xs text-gray-600">
                    <input type="checkbox" v-model="decision.acepto" class="mt-0.5" />
                    Acepto el total y las condiciones de esta cotización.
                </label>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button"
                        :disabled="!decision.acepto || decision.nombre.trim().length < 5 || decision.enviando"
                        @click="$emit('aceptar')"
                        class="min-h-[44px] rounded-lg bg-green-700 px-4 text-sm font-bold text-white disabled:opacity-40">
                        Aceptar cotización
                    </button>
                    <button type="button" @click="decision.rechazando = true"
                        class="min-h-[44px] rounded-lg border border-gray-300 px-4 text-sm font-bold text-gray-700">
                        No acepto
                    </button>
                </div>
            </template>
            <template v-else>
                <textarea v-model="decision.motivo" maxlength="255" placeholder="¿Por qué no la aceptas? (opcional)"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" :disabled="decision.enviando" @click="$emit('rechazar')"
                        class="min-h-[44px] rounded-lg bg-red-600 px-4 text-sm font-bold text-white disabled:opacity-40">
                        Confirmar rechazo
                    </button>
                    <button type="button" @click="decision.rechazando = false"
                        class="min-h-[44px] rounded-lg px-4 text-sm text-gray-600">
                        Volver
                    </button>
                </div>
            </template>
            <p v-if="decision.error" class="text-sm font-medium text-red-600">{{ decision.error }}</p>
        </div>
    </article>
</template>
