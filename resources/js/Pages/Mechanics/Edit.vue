<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    mechanic: Object,
    genders: Array,
    mechanicTypes: Array,
});

const form = useForm({
    name:             props.mechanic.name,
    email:            props.mechanic.email ?? '',
    gender_id:        props.mechanic.gender_id,
    mechanic_type_id: props.mechanic.mechanic_type_id,
    phone:            props.mechanic.phone ?? '',
    commission_rate:  props.mechanic.commission_rate ?? 0,
    password:         '',
    password_confirmation: '',
});

function submit() {
    form.put(route('mechanics.update', props.mechanic.id));
}
</script>

<template>
    <AuthenticatedLayout :title="$t('mechanics_form.editarTitulo')">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                ✏️ {{ $t('mechanics_form.editarTitulo') }}
            </h2>
        </template>

        <div class="py-12 min-h-screen transition-colors">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] px-6 py-4">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">
                            {{ t('mechanics_form.editarTitulo') }}: {{ mechanic.name }}
                        </p>
                    </div>
                    <div class="p-6 space-y-5">

                        <!-- Info cuenta vinculada -->
                        <div v-if="mechanic.user_id" class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                            <span class="text-blue-500">👤</span>
                            <p class="text-xs text-blue-800">
                                {{ t('mechanics_form.linkedAccount') }}
                            </p>
                        </div>
                        <div v-else class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                            <span class="text-amber-500">⚠️</span>
                            <p class="text-xs text-amber-800">
                                {{ t('mechanics_form.noLinkedAccount') }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('mechanics_form.nombre') }}</label>
                            <input v-model="form.name" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('mechanics_form.email') }}</label>
                            <input v-model="form.email" type="email"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>

                        <!-- Contraseña inicial: solo si el mecánico aún no tiene cuenta. Nunca se cambia desde aquí. -->
                        <div v-if="!mechanic.user_id" class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('mechanics_form.passwordInicial') }}</label>
                                <input v-model="form.password" type="password" autocomplete="new-password"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                                <p class="text-xs text-gray-400 mt-1">{{ t('mechanics_form.passwordAyuda') }}</p>
                                <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('mechanics_form.passwordConfirmar') }}</label>
                                <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('mechanics_form.especialidad') }}</label>
                                <select v-model="form.mechanic_type_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                    <option value="" disabled>{{ t('mechanics_form.seleccione') }}</option>
                                    <option v-for="t in mechanicTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <p v-if="form.errors.mechanic_type_id" class="text-red-500 text-xs mt-1">{{ form.errors.mechanic_type_id }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('mechanics_form.genero') }}</label>
                                <select v-model="form.gender_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                    <option value="" disabled>{{ t('mechanics_form.seleccione') }}</option>
                                    <option v-for="g in genders" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                                <p v-if="form.errors.gender_id" class="text-red-500 text-xs mt-1">{{ form.errors.gender_id }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">{{ t('mechanics_form.telefono') }}</label>
                            <input v-model="form.phone" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Comisión (% sobre mano de obra, sin IVA)</label>
                            <input v-model="form.commission_rate" type="number" min="0" max="100" step="0.01"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.commission_rate" class="text-red-500 text-xs mt-1">{{ form.errors.commission_rate }}</p>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <a :href="route('mechanics.index')"
                                class="flex-1 text-center text-gray-500 font-bold py-2.5 rounded-lg hover:bg-gray-100 transition text-sm uppercase">
                                {{ t('acciones.cancelar') }}
                            </a>
                            <button @click="submit" :disabled="form.processing"
                                class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2.5 rounded-lg transition text-sm uppercase shadow-md">
                                {{ form.processing ? $t('mechanics_form.guardando') : $t('mechanics_form.actualizar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
