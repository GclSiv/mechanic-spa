<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    orden:     { type: Object, required: true },
    mechanics: { type: Array,  required: true },
});

// ── Asignación de mecánico ────────────────────────────────────────────────
const mechanicForm = useForm({ mechanic_id: props.orden.mechanic_id ?? '' });

function assignMechanic() {
    mechanicForm.patch(route('repair-orders.mechanic.assign', props.orden.id), {
        preserveScroll: true,
    });
}

// ── Bitácora / Follow-up ──────────────────────────────────────────────────
const today = new Date().toISOString().slice(0, 16); // datetime-local default

const followForm = useForm({
    mechanic_id: props.orden.mechanic_id ?? '',
    description: '',
    date: today,
});

function submitFollowUp() {
    followForm.post(route('repair-orders.follow-ups.store', props.orden.id), {
        preserveScroll: true,
        onSuccess: () => {
            followForm.description = '';
            followForm.date = today;
        },
    });
}

const currentMechanicName = computed(() => {
    const m = props.mechanics.find(m => m.id === props.orden.mechanic_id);
    return m ? m.name : null;
});
</script>

<template>
    <div class="space-y-6">

        <!-- ── ASIGNACIÓN DE MECÁNICO ───────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#10213E] p-6">
            <h3 class="text-sm font-black text-[#10213E] uppercase tracking-widest border-b pb-2 mb-4">
                🔧 Mecánico Asignado
            </h3>

            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Técnico responsable</label>
                    <select
                        v-model="mechanicForm.mechanic_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[#10213E]"
                    >
                        <option value="" disabled>-- Seleccionar mecánico --</option>
                        <option v-for="m in mechanics" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <p v-if="mechanicForm.errors.mechanic_id" class="text-red-500 text-xs mt-1">
                        {{ mechanicForm.errors.mechanic_id }}
                    </p>
                </div>
                <button
                    @click="assignMechanic"
                    :disabled="mechanicForm.processing || !mechanicForm.mechanic_id"
                    class="bg-[#10213E] hover:bg-blue-900 disabled:opacity-40 text-white font-black text-xs uppercase px-5 py-2.5 rounded-lg transition-all shadow-sm"
                >
                    {{ mechanicForm.processing ? 'Guardando…' : 'Asignar' }}
                </button>
            </div>

            <p v-if="currentMechanicName" class="mt-2 text-xs text-gray-500">
                Actualmente asignado: <span class="font-bold text-[#10213E]">{{ currentMechanicName }}</span>
            </p>
        </div>

        <!-- ── BITÁCORA DE SEGUIMIENTO ──────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#EE2857] p-6">
            <h3 class="text-sm font-black text-[#10213E] uppercase tracking-widest border-b pb-2 mb-4">
                📋 Bitácora de Seguimiento
            </h3>

            <!-- Formulario nueva nota -->
            <div class="space-y-3 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Mecánico</label>
                        <select
                            v-model="followForm.mechanic_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]"
                        >
                            <option value="" disabled>-- Seleccionar --</option>
                            <option v-for="m in mechanics" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <p v-if="followForm.errors.mechanic_id" class="text-red-500 text-xs mt-1">
                            {{ followForm.errors.mechanic_id }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Fecha y hora</label>
                        <input
                            v-model="followForm.date"
                            type="datetime-local"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]"
                        />
                        <p v-if="followForm.errors.date" class="text-red-500 text-xs mt-1">
                            {{ followForm.errors.date }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nota de avance</label>
                    <textarea
                        v-model="followForm.description"
                        rows="3"
                        placeholder="Ej: Se realizó diagnóstico eléctrico. Se detectó falla en sensor MAF…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#10213E]"
                    ></textarea>
                    <p v-if="followForm.errors.description" class="text-red-500 text-xs mt-1">
                        {{ followForm.errors.description }}
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="submitFollowUp"
                        :disabled="followForm.processing || !followForm.description || !followForm.mechanic_id"
                        class="bg-[#EE2857] hover:bg-red-700 disabled:opacity-40 text-white font-black text-xs uppercase px-6 py-2.5 rounded-lg transition-all shadow-sm"
                    >
                        {{ followForm.processing ? 'Guardando…' : '+ Agregar Nota' }}
                    </button>
                </div>
            </div>

            <!-- Timeline de notas existentes -->
            <div v-if="orden.follow_ups && orden.follow_ups.length > 0" class="space-y-3">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Historial</p>
                <div
                    v-for="note in orden.follow_ups"
                    :key="note.id"
                    class="relative pl-5 border-l-2 border-gray-200 hover:border-[#10213E] transition-colors"
                >
                    <div class="absolute -left-[5px] top-2 w-2 h-2 rounded-full bg-[#10213E]"></div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-xs font-black text-[#10213E]">
                                {{ note.mechanic?.name ?? '—' }}
                            </span>
                            <span class="text-[10px] text-gray-400">
                                {{ new Date(note.date).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-700 leading-snug">{{ note.description }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-6 text-gray-400 text-sm italic">
                Sin notas aún. Agrega la primera entrada a la bitácora.
            </div>
        </div>

    </div>
</template>
