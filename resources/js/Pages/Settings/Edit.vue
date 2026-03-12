<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Object });

// Iniciamos el formulario con todos los datos que ya existen
const form = useForm({
    company_name: props.settings.company_name,
    address: props.settings.address,
    phone: props.settings.phone,
    license_number: props.settings.license_number,
    clauses: props.settings.clauses,
    logo: null, 
    primary_color: props.settings.primary_color,
    secondary_color: props.settings.secondary_color,
});

const submit = () => {
    // Usamos post con _method: 'put' para que PHP procese bien el archivo
    form.post(route('settings.update', { _method: 'put' }));
};
</script>

<template>
    <AuthenticatedLayout title="Configuración">
        <div class="py-8 bg-gray-100 min-h-screen">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                
                <form @submit.prevent="submit" class="bg-white shadow-2xl rounded-3xl overflow-hidden border border-gray-200">
                    <div class="p-6 bg-jk-blue text-white shadow-md">
                        <h2 class="text-xl font-black uppercase tracking-widest flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Centro de Control de Identidad
                        </h2>
                    </div>

                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                        
                        <div class="space-y-6">
                            <h3 class="text-md font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">Información de Contacto</h3>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-black text-gray-500 uppercase mb-1">Nombre Comercial</label>
                                    <input v-model="form.company_name" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-gray-500 uppercase mb-1">Dirección Física</label>
                                    <input v-model="form.address" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Teléfono</label>
                                        <input v-model="form.phone" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-gray-500 uppercase mb-1">Licencia #</label>
                                        <input v-model="form.license_number" type="text" class="w-full border-gray-200 rounded-xl focus:ring-jk-blue shadow-sm bg-gray-50">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <h3 class="text-md font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">Identidad Visual</h3>
                            
                            <div class="flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl p-6 bg-gray-50 relative group">
                                <label class="absolute top-2 left-4 text-[10px] font-bold text-gray-400 uppercase">Logotipo</label>
                                
                                <div class="mb-4">
                                    <img v-if="settings.logo_path" :src="'/storage/' + settings.logo_path" class="h-20 object-contain drop-shadow-sm" />
                                    <div v-else class="h-20 w-32 bg-gray-200 rounded-lg flex items-center justify-center text-[10px] text-gray-400 uppercase">Sin Logo</div>
                                </div>

                                <input type="file" @input="form.logo = $event.target.files[0]" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-jk-blue file:text-white hover:file:bg-blue-900 cursor-pointer"/>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-3 rounded-2xl border border-gray-100">
                                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Botones</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="form.primary_color" type="color" class="w-8 h-8 rounded-lg cursor-pointer border-none bg-transparent">
                                        <input v-model="form.primary_color" type="text" class="text-[10px] font-mono w-full border-none bg-transparent p-0 focus:ring-0">
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-2xl border border-gray-100">
                                    <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Acentos</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="form.secondary_color" type="color" class="w-8 h-8 rounded-lg cursor-pointer border-none bg-transparent">
                                        <input v-model="form.secondary_color" type="text" class="text-[10px] font-mono w-full border-none bg-transparent p-0 focus:ring-0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 space-y-2">
                            <h3 class="text-md font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">Cláusulas Legales</h3>
                            <textarea v-model="form.clauses" rows="4" class="w-full border-gray-200 rounded-2xl shadow-sm font-mono text-sm focus:ring-jk-blue bg-gray-50 mt-2"></textarea>
                        </div>
                    </div>

                    <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-end items-center gap-6">
                        <transition name="fade">
                            <span v-if="form.recentlySuccessful" class="text-green-600 font-bold text-sm">✅ Guardado correctamente</span>
                        </transition>

                        <button 
                            type="submit" 
                            :disabled="form.processing" 
                            class="bg-[#10213E] hover:bg-black text-white px-12 py-4 rounded-2xl font-black uppercase tracking-[0.2em] text-xs shadow-xl transition-all active:scale-95 disabled:opacity-50 flex items-center gap-3"
                            :style="{ backgroundColor: form.primary_color }"
                        >
                            <span v-if="form.processing" class="animate-spin text-lg">🌀</span>
                            {{ form.processing ? 'Sincronizando...' : 'Guardar Cambios' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>