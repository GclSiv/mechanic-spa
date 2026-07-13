<script setup>
import { ref, computed } from 'vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    orden:     { type: Object, required: true },
    mechanics: { type: Array,  required: true },
});

// ── Mechanic assignment ────────────────────────────────────────────────
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

// ── Follow-up log ──────────────────────────────────────────────────────
const today = new Date().toISOString().slice(0, 16);

const followForm = useForm({
    mechanic_id: props.orden.mechanic_id ?? '',
    description: '',
    date: today,
    photos: [],
});

const photoPreviews = ref([]);

function handlePhotoUpload(event) {
    const files = Array.from(event.target.files);
    files.forEach(file => {
        followForm.photos.push(file);
        photoPreviews.value.push(URL.createObjectURL(file));
    });
    event.target.value = '';
}

function removePhoto(index) {
    followForm.photos.splice(index, 1);
    URL.revokeObjectURL(photoPreviews.value[index]);
    photoPreviews.value.splice(index, 1);
}

function submitFollowUp() {
    followForm.post(route('repair-orders.follow-ups.store', props.orden.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            followForm.description = '';
            followForm.date = today;
            followForm.photos = [];
            photoPreviews.value.forEach(url => URL.revokeObjectURL(url));
            photoPreviews.value = [];
        },
    });
}

const confirmNote = ref({ show: false, id: null });
function deleteNote(id) { confirmNote.value = { show: true, id }; }
function doDeleteNote() {
    router.delete(route('repair-orders.follow-ups.destroy', {
        order:    props.orden.id,
        followUp: confirmNote.value.id,
    }), { preserveScroll: true });
    confirmNote.value = { show: false, id: null };
}

// ── Lightbox ───────────────────────────────────────────────────────────
const lightboxSrc = ref(null);
function openLightbox(path) { lightboxSrc.value = '/storage/' + path; }
</script>

<template>
    <div class="space-y-6">

        <!-- ── ASSIGNED MECHANIC ─────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#10213E] p-6">
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

        <!-- ── FOLLOW-UP LOG ─────────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#EE2857] p-6">
            <h3 class="text-sm font-black text-[#10213E] uppercase tracking-widest border-b pb-2 mb-4">
                📋 {{ t('followup.bitacora') }}
            </h3>

            <!-- New note form -->
            <div class="space-y-3 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">{{ t('followup.mecanicoBit') }}</label>
                        <select v-model="followForm.mechanic_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                            <option value="" disabled>{{ t('followup.seleccionar') }}</option>
                            <option v-for="m in mechanics" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <p v-if="followForm.errors.mechanic_id" class="text-red-500 text-xs mt-1">
                            {{ followForm.errors.mechanic_id }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">{{ t('followup.fechaHora') }}</label>
                        <input v-model="followForm.date" type="datetime-local"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]" />
                        <p v-if="followForm.errors.date" class="text-red-500 text-xs mt-1">
                            {{ followForm.errors.date }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">{{ t('followup.notaAvance') }}</label>
                    <textarea v-model="followForm.description" rows="3"
                        :placeholder="t('followup.notaPh')"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#10213E]">
                    </textarea>
                    <p v-if="followForm.errors.description" class="text-red-500 text-xs mt-1">
                        {{ followForm.errors.description }}
                    </p>
                </div>

                <!-- Photo upload -->
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">
                        📷 {{ t('followup.evidencia') }}
                        <span class="normal-case text-gray-400 font-normal ml-1">{{ t('followup.evidenciaOpt') }}</span>
                    </label>
                    <label class="flex flex-col items-center justify-center w-full border-2 border-dashed border-gray-300 rounded-lg py-4 cursor-pointer hover:border-[#10213E] hover:bg-gray-50 transition">
                        <span class="text-2xl mb-1">📁</span>
                        <span class="text-xs font-bold text-gray-500">{{ t('followup.clickFotos') }}</span>
                        <span class="text-xs text-gray-400">{{ t('followup.arrastraFotos') }}</span>
                        <input type="file" multiple accept="image/*" class="hidden" @change="handlePhotoUpload" />
                    </label>
                    <div v-if="photoPreviews.length > 0" class="mt-3 grid grid-cols-3 sm:grid-cols-5 gap-2">
                        <div v-for="(preview, i) in photoPreviews" :key="i"
                            class="relative group rounded-lg overflow-hidden aspect-square border border-gray-200">
                            <img :src="preview" class="w-full h-full object-cover" />
                            <button @click="removePhoto(i)"
                                class="absolute top-1 right-1 bg-black/60 hover:bg-red-600 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                ✕
                            </button>
                        </div>
                    </div>
                    <p v-if="followForm.errors.photos" class="text-red-500 text-xs mt-1">
                        {{ followForm.errors.photos }}
                    </p>
                </div>

                <div class="flex justify-end">
                    <button @click="submitFollowUp"
                        :disabled="followForm.processing || !followForm.description || !followForm.mechanic_id"
                        class="bg-[#EE2857] hover:bg-red-700 disabled:opacity-40 text-white font-black text-xs uppercase px-6 py-2.5 rounded-lg transition shadow-sm flex items-center gap-2">
                        <span v-if="followForm.processing" class="animate-spin inline-block">⟳</span>
                        {{ followForm.processing
                            ? (followForm.photos.length > 0 ? t('followup.subiendoFotos') : t('followup.guardando'))
                            : t('followup.agregarNota') }}
                    </button>
                </div>
            </div>

            <!-- Note history -->
            <div v-if="orden.follow_ups && orden.follow_ups.length > 0" class="space-y-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ t('followup.historial') }}</p>
                <div v-for="note in orden.follow_ups" :key="note.id"
                    class="relative pl-5 border-l-2 border-gray-200 hover:border-[#10213E] transition-colors">
                    <div class="absolute -left-[5px] top-2 w-2 h-2 rounded-full bg-[#10213E]"></div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-xs font-black text-[#10213E]">{{ note.mechanic?.name ?? '—' }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400">
                                    {{ new Date(note.date).toLocaleString('en-US', { dateStyle: 'short', timeStyle: 'short' }) }}
                                </span>
                                <button @click="deleteNote(note.id)"
                                    :title="t('followup.eliminarNota')"
                                    class="text-gray-300 hover:text-[#EE2857] transition text-xs font-bold leading-none">✕</button>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 leading-snug">{{ note.description }}</p>
                        <div v-if="note.evidence_photos && note.evidence_photos.length > 0"
                            class="mt-3 grid grid-cols-3 sm:grid-cols-5 gap-1.5">
                            <button v-for="(photo, pi) in note.evidence_photos" :key="pi"
                                @click="openLightbox(photo)"
                                class="aspect-square rounded-md overflow-hidden border border-gray-200 hover:opacity-80 transition hover:border-[#10213E] focus:outline-none">
                                <img :src="'/storage/' + photo" class="w-full h-full object-cover" :alt="'Evidence ' + (pi + 1)" />
                            </button>
                            <p class="col-span-full text-[10px] text-gray-400 mt-0.5">
                                {{ note.evidence_photos.length }}
                                {{ note.evidence_photos.length > 1 ? t('followup.fotosClick') : t('followup.fotoClick') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-6 text-gray-400 text-sm italic">
                {{ t('followup.sinNotas') }}
            </div>
        </div>
    </div>

    <!-- ── LIGHTBOX ───────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="lightboxSrc"
            class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center p-4"
            @click.self="lightboxSrc = null">
            <div class="relative max-w-4xl max-h-[90vh] w-full flex items-center justify-center">
                <img :src="lightboxSrc" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl" />
                <button @click="lightboxSrc = null"
                    class="absolute top-2 right-2 bg-black/60 hover:bg-red-600 text-white rounded-full w-9 h-9 flex items-center justify-center text-lg font-bold transition">✕</button>
                <a :href="lightboxSrc" target="_blank" download
                    class="absolute bottom-2 right-2 bg-white/20 hover:bg-white/40 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition">
                    {{ t('followup.descargar') }}
                </a>
            </div>
        </div>
    </Teleport>

    <ConfirmModal :show="confirmNote.show"
        :title="t('followup.eliminarNota')"
        :message="t('followup.eliminarNotaMsg')"
        confirm-text="OK"
        @confirm="doDeleteNote"
        @cancel="confirmNote.show = false" />
</template>
