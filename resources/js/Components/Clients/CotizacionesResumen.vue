<script setup>
// Resumen de SOLO LECTURA de las cotizaciones de un servicio dentro del expediente.
// No envía, aprueba ni rechaza: esas acciones viven en la orden. No calcula totales.
defineProps({
    cotizaciones: { type: Array, default: () => [] },
});

const claseEstado = (s) => ({
    borrador:    'bg-gray-100 text-gray-700',
    enviada:     'bg-amber-100 text-amber-800',
    aprobada:    'bg-green-100 text-green-800',
    rechazada:   'bg-red-100 text-red-800',
    reemplazada: 'bg-gray-200 text-gray-500',
}[s] ?? 'bg-gray-100 text-gray-700');

const mxn = (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v ?? 0));
</script>

<template>
    <ul class="space-y-2">
        <li v-for="c in cotizaciones" :key="c.id"
            class="bg-white rounded-lg border px-3 py-2 text-sm flex flex-wrap items-center gap-x-3 gap-y-1"
            :class="c.kind === 'principal' ? 'border-[#10213E]/30' : 'border-gray-100'">
            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
                  :class="c.kind === 'principal' ? 'bg-[#10213E] text-white' : 'bg-gray-100 text-gray-600'">
                {{ c.kind === 'principal' ? 'Principal' : 'Adicional' }}
            </span>
            <span class="font-black text-[#10213E]">{{ c.folio || 'Sin folio' }}</span>
            <span class="text-xs text-gray-400">v{{ c.version }}</span>
            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full" :class="claseEstado(c.status)">
                {{ $t('quotesPanel.estado.' + c.status) }}
            </span>
            <span class="ml-auto font-bold tabular-nums text-[#10213E]">{{ mxn(c.total) }}</span>
            <span v-if="c.status === 'rechazada'" class="w-full text-xs text-gray-500">No suma al autorizado.</span>
            <span v-if="c.fecha" class="w-full sm:w-auto text-xs text-gray-400">{{ c.fecha }}</span>
        </li>
    </ul>
</template>
