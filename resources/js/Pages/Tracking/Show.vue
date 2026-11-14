<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    orden: Object,
    client_first_name: String,
    vehicle: Object,
    quotes: Array,
    current_quote_id: Number,
    settings: Object,
});

const { t } = useI18n();

const money = (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0));

const statusLabel = computed(() => {
    const slug = props.orden?.status?.slug;
    const key = `status.${slug}`;
    const translated = slug ? t(key) : '';
    return translated && translated !== key ? translated : (props.orden?.status?.name ?? '');
});

const quoteStatusClass = (status) => ({
    borrador:    'bg-gray-100 text-gray-700',
    enviada:     'bg-amber-100 text-amber-800',
    aprobada:    'bg-green-100 text-green-800',
    rechazada:   'bg-red-100 text-red-800',
    reemplazada: 'bg-gray-200 text-gray-500',
}[status] ?? 'bg-gray-100 text-gray-700');
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

            <section>
                <h2 class="text-sm font-bold uppercase text-gray-500 mb-3">{{ $t('seguimiento.cotizaciones') }}</h2>

                <p v-if="!quotes?.length" class="text-sm text-gray-400 bg-white rounded-xl border border-dashed border-gray-300 p-6 text-center">
                    {{ $t('seguimiento.sinCotizaciones') }}
                </p>

                <article v-for="q in quotes" :key="q.id"
                    class="bg-white rounded-xl shadow-sm border p-5 mb-4"
                    :class="q.id === current_quote_id ? 'border-[#10213E] ring-1 ring-[#10213E]' : 'border-gray-200'">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-bold">{{ $t('seguimiento.cotizacion') }} {{ q.folio }}</p>
                            <p class="text-xs text-gray-500" v-if="q.valid_until">
                                {{ $t('seguimiento.vigente') }}: {{ q.valid_until }}
                                <span v-if="q.expired" class="text-red-600 font-bold"> · {{ $t('seguimiento.vencida') }}</span>
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="quoteStatusClass(q.status)">
                            {{ $t('seguimiento.quoteStatus.' + q.status) }}
                        </span>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <div class="overflow-x-auto"><table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-400 uppercase border-b">
                                    <th class="py-2">{{ $t('seguimiento.concepto') }}</th>
                                    <th class="py-2 text-right">{{ $t('seguimiento.cant') }}</th>
                                    <th class="py-2 text-right">{{ $t('seguimiento.importe') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(it, i) in q.items" :key="i" class="border-b border-gray-100">
                                    <td class="py-2">
                                        <span class="text-[10px] font-bold uppercase mr-1"
                                            :class="it.type === 'labor' ? 'text-blue-600' : 'text-amber-600'">
                                            {{ it.type === 'labor' ? $t('seguimiento.manoObra') : $t('seguimiento.refaccion') }}
                                        </span>
                                        {{ it.description }}
                                    </td>
                                    <td class="py-2 text-right">{{ Number(it.quantity) }}</td>
                                    <td class="py-2 text-right">{{ money(it.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table></div>
                    </div>

                    <div class="mt-4 ml-auto w-full sm:w-1/2 text-sm space-y-1">
                        <div class="flex justify-between"><span>{{ $t('seguimiento.refacciones') }}</span><span>{{ money(q.parts_subtotal) }}</span></div>
                        <div class="flex justify-between"><span>{{ $t('seguimiento.manoObra') }}</span><span>{{ money(q.labor_subtotal) }}</span></div>
                        <div class="flex justify-between"><span>{{ $t('seguimiento.subtotal') }}</span><span>{{ money(q.subtotal) }}</span></div>
                        <div class="flex justify-between"><span>{{ $t('seguimiento.iva') }} ({{ Number(q.tax_rate) }}%)</span><span>{{ money(q.tax) }}</span></div>
                        <div class="flex justify-between font-bold text-base border-t pt-2"><span>{{ $t('seguimiento.total') }}</span><span>{{ money(q.total) }}</span></div>
                    </div>

                    <p v-if="q.notes" class="mt-4 text-sm text-gray-600 bg-gray-50 rounded p-3">{{ q.notes }}</p>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-gray-500">{{ $t('seguimiento.comoAprobar') }}</p>
                        <a :href="q.pdf_url" target="_blank"
                            class="px-4 py-2 rounded-lg bg-[#10213E] text-white text-sm font-bold hover:bg-[#1c3660]">
                            {{ $t('seguimiento.descargarPdf') }}
                        </a>
                    </div>
                </article>
            </section>

            <footer class="text-center text-xs text-gray-400 pb-8">{{ $t('seguimiento.pie') }}</footer>
        </main>
    </div>
</template>
