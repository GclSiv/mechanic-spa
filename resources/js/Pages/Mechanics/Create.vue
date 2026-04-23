<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    genders: Array,
    mechanicTypes: Array,
});

const form = useForm({
    name:             '',
    email:            '',
    role:             'mechanic',   // default: no falla la validación
    gender_id:        '',
    mechanic_type_id: '',
    phone:            '',
});

function submit() {
    form.post(route('mechanics.store'));
}
</script>

<template>
    <AuthenticatedLayout title="Nuevo Mecánico">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                ➕ Nuevo Mecánico
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] px-6 py-4">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">Datos del técnico</p>
                    </div>
                    <div class="p-6 space-y-5">

                        <!-- Nota contraseña temporal -->
                        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                            <span class="text-amber-500 text-lg leading-none">🔑</span>
                            <p class="text-xs text-amber-800">
                                <strong>Contraseña temporal para nuevos mecánicos:</strong>
                                <span class="font-mono bg-amber-100 px-1.5 py-0.5 rounded ml-1">password123</span>
                                — El mecánico deberá cambiarla en su primer inicio de sesión.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre completo *</label>
                            <input v-model="form.name" type="text" placeholder="Ej. Carlos Ramírez"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Correo electrónico * <span class="normal-case text-gray-400 font-normal">(será su usuario de acceso)</span></label>
                            <input v-model="form.email" type="email" placeholder="Ej. carlos@taller.com"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>


                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Rol del Sistema *</label>
                            <select v-model="form.role"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                <option value="mechanic">Mecánico</option>
                                <option value="admin">Administrador</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Los administradores tienen acceso completo al sistema.</p>
                            <p v-if="form.errors.role" class="text-red-500 text-xs mt-1">{{ form.errors.role }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Especialidad *</label>
                                <select v-model="form.mechanic_type_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                    <option value="" disabled>-- Seleccionar --</option>
                                    <option v-for="t in mechanicTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <p v-if="form.errors.mechanic_type_id" class="text-red-500 text-xs mt-1">{{ form.errors.mechanic_type_id }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Género *</label>
                                <select v-model="form.gender_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                                    <option value="" disabled>-- Seleccionar --</option>
                                    <option v-for="g in genders" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                                <p v-if="form.errors.gender_id" class="text-red-500 text-xs mt-1">{{ form.errors.gender_id }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Teléfono</label>
                            <input v-model="form.phone" type="text" placeholder="Ej. 7601234567"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                            <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-gray-100">
                            <a :href="route('mechanics.index')"
                                class="flex-1 text-center text-gray-500 font-bold py-2.5 rounded-lg hover:bg-gray-100 transition text-sm uppercase">
                                Cancelar
                            </a>
                            <button @click="submit" :disabled="form.processing"
                                class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2.5 rounded-lg transition text-sm uppercase shadow-md">
                                {{ form.processing ? 'Creando cuenta...' : 'Registrar Mecánico' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
