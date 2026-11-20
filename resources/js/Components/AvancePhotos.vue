<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    orden: Object,
});

const fotos = computed(() => props.orden?.avance_photos ?? props.orden?.avancePhotos ?? []);
const estadosConFotos = ['diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];
const puedeSubir = computed(() => estadosConFotos.includes(props.orden?.status?.slug));

const inputRef = ref(null);
const form = useForm({ photo: null, note: '' });

const urlFoto = (f) => route('repair-orders.avance.photo', [props.orden.id, f.id]);
const fechaFoto = (f) => new Date(f.created_at).toLocaleDateString('es-MX');

function enviar() {
    form.post(route('repair-orders.avance.store', props.orden.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (inputRef.value) inputRef.value.value = '';
        },
    });
}

function borrar(f) {
    if (!confirm('¿Eliminar esta foto del avance?')) return;
    router.delete(route('repair-orders.avance.destroy', [props.orden.id, f.id]), { preserveScroll: true });
}
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-black text-[#10213E] uppercase tracking-widest border-b pb-2 mb-4">📷 Fotos del avance</h3>
        <p class="text-xs text-gray-500 mb-4">Las fotos se muestran al cliente en su enlace de seguimiento.</p>

        <form v-if="puedeSubir" @submit.prevent="enviar" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
            <input ref="inputRef" type="file" accept="image/*"
                @change="form.photo = $event.target.files[0] ?? null"
                class="text-sm" />
            <input v-model="form.note" type="text" maxlength="255"
                placeholder="Nota (ej. bomba de aceite instalada)"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            <button type="submit" :disabled="!form.photo || form.processing"
                class="bg-[#EE2857] hover:bg-red-700 disabled:opacity-40 text-white rounded-lg font-bold text-xs uppercase px-4 py-2">
                {{ form.processing ? 'Subiendo...' : 'Subir foto' }}
            </button>
            <p v-if="form.errors.photo" class="text-red-600 text-xs md:col-span-3">{{ form.errors.photo }}</p>
        </form>
        <p v-else class="text-xs text-amber-700 mb-4">
            Las fotos se pueden subir cuando el vehículo está en diagnóstico, espera de piezas, reparación o listo.
        </p>

        <div v-if="fotos.length" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div v-for="f in fotos" :key="f.id" class="relative border border-gray-200 rounded-lg overflow-hidden">
                <a :href="urlFoto(f)" target="_blank">
                    <img :src="urlFoto(f)" alt="Foto del avance" class="w-full h-32 object-cover" />
                </a>
                <div class="p-2 text-xs text-gray-600">
                    <p class="font-bold">{{ fechaFoto(f) }}</p>
                    <p v-if="f.note">{{ f.note }}</p>
                </div>
                <button type="button" @click="borrar(f)"
                    class="absolute top-1 right-1 bg-white/90 text-red-600 rounded px-2 py-0.5 text-xs font-bold">✕</button>
            </div>
        </div>
        <p v-else class="text-sm text-gray-400 italic">Aún no hay fotos del avance.</p>
    </div>
</template>
