<script setup>
import { useForm } from '@inertiajs/vue3';
import { ETIQUETAS_ESTADO, ETIQUETAS_CONDICION } from '@/Components/Tools/etiquetas.js';

const props = defineProps({
    tool:        { type: Object, default: null },
    categories:  { type: Array, default: () => [] },
    locations:   { type: Array, default: () => [] },
    estados:     { type: Array, default: () => [] },
    condiciones: { type: Array, default: () => [] },
});

const form = useForm({
    category_id:   props.tool?.category_id ?? '',
    location_id:   props.tool?.location_id ?? '',
    name:          props.tool?.name ?? '',
    brand:         props.tool?.brand ?? '',
    model:         props.tool?.model ?? '',
    serial_number: props.tool?.serial_number ?? '',
    internal_code: props.tool?.internal_code ?? '',
    purchase_date: props.tool?.purchase_date ? String(props.tool.purchase_date).slice(0, 10) : '',
    purchase_cost: props.tool?.purchase_cost ?? '',
    status:        props.tool?.status ?? 'disponible',
    condition:     props.tool?.condition ?? '',
    notes:         props.tool?.notes ?? '',
});

function submit() {
    if (props.tool) {
        form.put(route('tools.update', props.tool.id));
    } else {
        form.post(route('tools.store'));
    }
}

const campo = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]';
const etiqueta = 'block text-xs font-bold text-gray-500 uppercase mb-1';
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <section class="bg-white rounded-xl shadow-sm p-6 space-y-5">
            <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest border-b border-gray-100 pb-2">Identificación</h3>

            <div>
                <label :class="etiqueta">Nombre</label>
                <input v-model="form.name" type="text" :class="campo" placeholder="Ej. Multímetro digital" />
                <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label :class="etiqueta">Código interno</label>
                    <input v-model="form.internal_code" type="text" :class="campo + ' font-mono uppercase'" placeholder="HER-0001" />
                    <p class="text-gray-400 text-xs mt-1">Letras mayúsculas, números y guiones.</p>
                    <p v-if="form.errors.internal_code" class="text-red-500 text-xs mt-1">{{ form.errors.internal_code }}</p>
                </div>
                <div>
                    <label :class="etiqueta">Número de serie</label>
                    <input v-model="form.serial_number" type="text" :class="campo + ' font-mono'" />
                    <p v-if="form.errors.serial_number" class="text-red-500 text-xs mt-1">{{ form.errors.serial_number }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label :class="etiqueta">Marca</label>
                    <input v-model="form.brand" type="text" :class="campo" />
                    <p v-if="form.errors.brand" class="text-red-500 text-xs mt-1">{{ form.errors.brand }}</p>
                </div>
                <div>
                    <label :class="etiqueta">Modelo</label>
                    <input v-model="form.model" type="text" :class="campo" />
                    <p v-if="form.errors.model" class="text-red-500 text-xs mt-1">{{ form.errors.model }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label :class="etiqueta">Categoría</label>
                    <select v-model="form.category_id" :class="campo">
                        <option value="">Sin categoría</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</p>
                </div>
                <div>
                    <label :class="etiqueta">Ubicación</label>
                    <select v-model="form.location_id" :class="campo">
                        <option value="">Sin ubicación</option>
                        <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                    <p v-if="form.errors.location_id" class="text-red-500 text-xs mt-1">{{ form.errors.location_id }}</p>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm p-6 space-y-5">
            <h3 class="text-xs font-black text-[#10213E] uppercase tracking-widest border-b border-gray-100 pb-2">Compra y estado</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label :class="etiqueta">Fecha de compra</label>
                    <input v-model="form.purchase_date" type="date" :class="campo" />
                    <p v-if="form.errors.purchase_date" class="text-red-500 text-xs mt-1">{{ form.errors.purchase_date }}</p>
                </div>
                <div>
                    <label :class="etiqueta">Costo de compra (MXN)</label>
                    <input v-model="form.purchase_cost" type="number" step="0.01" min="0" :class="campo" />
                    <p v-if="form.errors.purchase_cost" class="text-red-500 text-xs mt-1">{{ form.errors.purchase_cost }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label :class="etiqueta">Estado</label>
                    <select v-model="form.status" :class="campo">
                        <option v-for="e in estados" :key="e" :value="e">{{ ETIQUETAS_ESTADO[e] ?? e }}</option>
                    </select>
                    <p v-if="form.errors.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</p>
                </div>
                <div>
                    <label :class="etiqueta">Condición</label>
                    <select v-model="form.condition" :class="campo">
                        <option value="">Sin especificar</option>
                        <option v-for="c in condiciones" :key="c" :value="c">{{ ETIQUETAS_CONDICION[c] ?? c }}</option>
                    </select>
                    <p v-if="form.errors.condition" class="text-red-500 text-xs mt-1">{{ form.errors.condition }}</p>
                </div>
            </div>

            <div>
                <label :class="etiqueta">Notas</label>
                <textarea v-model="form.notes" rows="3" :class="campo"></textarea>
                <p v-if="form.errors.notes" class="text-red-500 text-xs mt-1">{{ form.errors.notes }}</p>
            </div>
        </section>

        <div class="flex gap-3 pt-2">
            <a :href="route('tools.index')" class="flex-1 text-center text-gray-500 font-bold py-2.5 rounded-lg hover:bg-gray-100 transition text-sm uppercase">Cancelar</a>
            <button type="submit" :disabled="form.processing"
                class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2.5 rounded-lg transition text-sm uppercase shadow-md">
                {{ form.processing ? 'Guardando…' : 'Guardar herramienta' }}
            </button>
        </div>
    </form>
</template>
