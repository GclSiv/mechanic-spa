<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ETIQUETAS_ESTADO, ETIQUETAS_CONDICION, CLASES_ESTADO, dinero } from '@/Components/Tools/etiquetas.js';

const props = defineProps({ tool: Object });

const filas = [
    ['Código interno', () => props.tool.internal_code],
    ['Categoría', () => props.tool.category?.name ?? '—'],
    ['Ubicación', () => props.tool.location?.name ?? '—'],
    ['Marca', () => props.tool.brand ?? '—'],
    ['Modelo', () => props.tool.model ?? '—'],
    ['Número de serie', () => props.tool.serial_number ?? '—'],
    ['Fecha de compra', () => props.tool.purchase_date ? String(props.tool.purchase_date).slice(0, 10) : '—'],
    ['Costo de compra', () => dinero(props.tool.purchase_cost)],
    ['Condición', () => ETIQUETAS_CONDICION[props.tool.condition] ?? '—'],
];
</script>

<template>
    <AuthenticatedLayout :title="tool.name">
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">🔧 {{ tool.name }}</h2>
                <div class="flex gap-3">
                    <a :href="route('tools.edit', tool.id)" class="bg-[#10213E] hover:bg-blue-900 text-white font-bold py-2 px-4 rounded-lg text-sm uppercase">Editar</a>
                    <a :href="route('tools.index')" class="text-gray-500 hover:text-[#10213E] font-bold text-sm border border-gray-200 rounded-lg px-3 py-2">Volver</a>
                </div>
            </div>
        </template>
        <div class="py-12 min-h-screen">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <span :class="['text-xs font-bold px-2.5 py-1 rounded-full', CLASES_ESTADO[tool.status]]">
                        {{ ETIQUETAS_ESTADO[tool.status] ?? tool.status }}
                    </span>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 mt-6">
                        <div v-for="[etiqueta, valor] in filas" :key="etiqueta">
                            <dt class="text-xs font-bold text-gray-500 uppercase">{{ etiqueta }}</dt>
                            <dd class="text-sm text-gray-900 mt-1">{{ valor() }}</dd>
                        </div>
                    </dl>
                    <div v-if="tool.notes" class="mt-6 border-t border-gray-100 pt-4">
                        <dt class="text-xs font-bold text-gray-500 uppercase">Notas</dt>
                        <dd class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ tool.notes }}</dd>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
