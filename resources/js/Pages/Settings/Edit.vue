<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const props = defineProps({ settings: Object });

const form = useForm({
    company_name: props.settings.company_name,
    razon_social: props.settings.razon_social ?? '',
    rfc: props.settings.rfc ?? '',
    email: props.settings.email ?? '',
    address:      props.settings.address,
    phone:        props.settings.phone,
    license_number: props.settings.license_number,
    clauses:      props.settings.clauses,
    logo:         null,
    primary_color:   props.settings.primary_color,
    secondary_color: props.settings.secondary_color,
    warranty_service_enabled: !!props.settings.warranty_service_enabled,
    warranty_service_days:    props.settings.warranty_service_days ?? 30,
    warranty_service_km:      props.settings.warranty_service_km ?? null,
    warranty_parts_enabled:   !!props.settings.warranty_parts_enabled,
    warranty_parts_days:      props.settings.warranty_parts_days ?? 90,
    warranty_notes:           props.settings.warranty_notes ?? '',
});

const submit = () => {
    form.post(route('settings.update', { _method: 'put' }));
};
</script>

<template>
    <AuthenticatedLayout :title="t('settings.titulo')">
        <div class="py-8 bg-gray-100 min-h-screen">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

                <form @submit.prevent="submit" class="bg-white shadow-2xl rounded-3xl overflow-hidden border border-gray-200">

                    <!-- Header -->
                    <div class="p-6 bg-jk-blue text-white shadow-md">
                        <h2 class="text-xl font-black uppercase tracking-widest flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ t('settings.titulo') }}
                        </h2>
                    </div>

                    <!-- Body -->
                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8 items-start">

                        <!-- LEFT: Contact Info -->
                        <div class="space-y-6">
                            <h3 class="text-md font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">
                                {{ t('settings.infoContacto') }}
                            </h3>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-black text-gray-500 uppercase mb-1">{{ t('settings.nombreComercial') }}</label>
                                    <input v-model="form.company_name" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Razón social</label>
                                        <input v-model="form.razon_social" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">RFC</label>
                                        <input v-model="form.rfc" type="text" maxlength="13" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50 uppercase">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Correo</label>
                                        <input v-model="form.email" type="email" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-gray-500 uppercase mb-1">{{ t('settings.direccion') }}</label>
                                    <input v-model="form.address" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">{{ t('settings.telefono') }}</label>
                                        <input v-model="form.phone" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">{{ t('settings.licencia') }}</label>
                                        <input v-model="form.license_number" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: Visual Identity -->
                        <div class="space-y-6">
                            <h3 class="text-md font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">
                                {{ t('settings.identidadVisual') }}
                            </h3>

                            <!-- Logo upload -->
                            <div class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl p-6 bg-gray-50 relative group">
                                <label class="absolute top-2 left-4 text-[10px] font-bold text-gray-400 uppercase">{{ t('settings.logotipo') }}</label>
                                <div class="mb-4">
                                    <img v-if="settings.logo_path" :src="'/storage/' + settings.logo_path" class="h-20 object-contain drop-shadow-sm" />
                                    <div v-else class="h-20 w-32 bg-gray-200 rounded-lg flex items-center justify-center text-[10px] text-gray-400 uppercase">
                                        {{ t('settings.sinLogo') }}
                                    </div>
                                </div>
                                <input type="file" @input="form.logo = $event.target.files[0]"
                                    class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-jk-blue file:text-white hover:file:bg-blue-900 cursor-pointer" />
                            </div>

                            <!-- Color pickers -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-3 rounded-2xl border border-gray-100">
                                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">{{ t('settings.botones') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="form.primary_color" type="color" class="w-8 h-8 rounded-lg cursor-pointer border-none bg-transparent">
                                        <input v-model="form.primary_color" type="text" class="text-[10px] font-mono w-full border-none bg-transparent p-0 focus:ring-0">
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-2xl border border-gray-100">
                                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">{{ t('settings.acentos') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="form.secondary_color" type="color" class="w-8 h-8 rounded-lg cursor-pointer border-none bg-transparent">
                                        <input v-model="form.secondary_color" type="text" class="text-[10px] font-mono w-full border-none bg-transparent p-0 focus:ring-0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FULL WIDTH: Legal Clauses -->
                        <div class="md:col-span-2 space-y-2">
                            <h3 class="text-md font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">
                                {{ t('settings.clausulas') }}
                            </h3>
                            <textarea v-model="form.clauses" rows="4"
                                class="w-full border-gray-200 rounded-2xl shadow-sm font-mono text-sm focus:ring-jk-blue bg-gray-50 mt-2">
                            </textarea>
                        </div>

                        <!-- GARANTÍA: se imprime en la hoja de recepción -->
                        <div class="md:col-span-2 space-y-4">
                            <h3 class="text-md font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">
                                Garantía (aparece en la hoja de recepción)
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="rounded-2xl border border-gray-200 p-4 space-y-3">
                                    <label class="flex items-center gap-2 font-bold text-sm text-gray-700">
                                        <input type="checkbox" v-model="form.warranty_service_enabled" class="rounded border-gray-300">
                                        Garantía de servicio (mano de obra)
                                    </label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <span class="text-xs text-gray-500 uppercase font-bold">Días</span>
                                            <input v-model="form.warranty_service_days" type="number" min="0" max="3650"
                                                :disabled="!form.warranty_service_enabled"
                                                class="w-full border-gray-200 rounded-xl bg-gray-50 disabled:opacity-40">
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500 uppercase font-bold">Kilómetros (opcional)</span>
                                            <input v-model="form.warranty_service_km" type="number" min="0" max="1000000"
                                                :disabled="!form.warranty_service_enabled"
                                                class="w-full border-gray-200 rounded-xl bg-gray-50 disabled:opacity-40">
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-gray-200 p-4 space-y-3">
                                    <label class="flex items-center gap-2 font-bold text-sm text-gray-700">
                                        <input type="checkbox" v-model="form.warranty_parts_enabled" class="rounded border-gray-300">
                                        Garantía de refacciones
                                    </label>
                                    <div>
                                        <span class="text-xs text-gray-500 uppercase font-bold">Días desde la entrega</span>
                                        <input v-model="form.warranty_parts_days" type="number" min="0" max="3650"
                                            :disabled="!form.warranty_parts_enabled"
                                            class="w-full border-gray-200 rounded-xl bg-gray-50 disabled:opacity-40">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <span class="text-xs text-gray-500 uppercase font-bold">Condiciones adicionales (opcional)</span>
                                <textarea v-model="form.warranty_notes" rows="2" maxlength="1000"
                                    class="w-full border-gray-200 rounded-2xl shadow-sm text-sm focus:ring-jk-blue bg-gray-50 mt-1">
                                </textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-end items-center gap-6">
                        <transition name="fade">
                            <span v-if="form.recentlySuccessful" class="text-green-600 font-bold text-sm">
                                ✅ {{ t('settings.guardadoOk') }}
                            </span>
                        </transition>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-[#10213E] hover:bg-black text-white px-12 py-4 rounded-2xl font-black uppercase tracking-[0.2em] text-xs shadow-xl transition-all active:scale-95 disabled:opacity-50 flex items-center gap-3"
                            :style="{ backgroundColor: form.primary_color }"
                        >
                            <span v-if="form.processing" class="animate-spin text-lg">🌀</span>
                            {{ form.processing ? t('settings.sincronizando') : t('settings.guardarCambios') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
