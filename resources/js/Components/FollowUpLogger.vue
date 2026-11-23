<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    orden:     { type: Object, required: true },
    mechanics: { type: Array,  required: true },
});

// Asignación de mecánico: solo admin/dueño y solo en órdenes abiertas (la ruta es role:admin).
const mechanicForm = useForm({ mechanic_id: props.orden.mechanic_id ?? '' });

function assignMechanic() {
    mechanicForm.patch(route('repair-orders.mechanic.assign', props.orden.id), {
        preserveScroll: true,
    });
}

const currentMechanicName = computed(() => {
    const m = props.mechanics.find(m => m.id === props.orden.mechanic_id);
    return m ? m.name : null;
});
</script>

<template>
    <div v-if="$page.props.auth.isAdmin && !orden.esta_cerrada" class="bg-white rounded-xl shadow-sm border-l-4 border-[#10213E] p-6">
        <h3 class="text-sm font-black text-[#10213E] uppercase tracking-widest border-b pb-2 mb-4">
            🔧 {{ t('followup.mecanico') }}
        </h3>
        <div class="flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">{{ t('followup.tecnico') }}</label>
                <select v-model="mechanicForm.mechanic_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                    <option value="" disabled>{{ t('followup.seleccionarMec') }}</option>
                    <option v-for="m in mechanics" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <p v-if="mechanicForm.errors.mechanic_id" class="text-red-500 text-xs mt-1">
                    {{ mechanicForm.errors.mechanic_id }}
                </p>
            </div>
            <button @click="assignMechanic"
                :disabled="mechanicForm.processing || !mechanicForm.mechanic_id"
                class="bg-[#10213E] hover:bg-blue-900 disabled:opacity-40 text-white font-black text-xs uppercase px-5 py-2.5 rounded-lg transition shadow-sm">
                {{ mechanicForm.processing ? t('followup.guardando') : t('followup.asignar') }}
            </button>
        </div>
        <p v-if="currentMechanicName" class="mt-2 text-xs text-gray-500">
            {{ t('followup.asignadoA') }} <span class="font-bold text-[#10213E]">{{ currentMechanicName }}</span>
        </p>
    </div>
</template>
