<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, useForm } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    orden: { type: Object, required: true },
    // Lo calcula la vista (admin o orden abierta); el backend vuelve a validarlo.
    puedeBorrar: { type: Boolean, default: false },
});

const { t, locale } = useI18n();

// Mismos tipos que admite la validación `image` del backend (SVG excluido).
const TIPOS = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
const MAX_BYTES = 5 * 1024 * 1024;
const ESTADOS_CON_FOTOS = ['diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

const fotos = computed(() => props.orden?.avance_photos ?? props.orden?.avancePhotos ?? []);
const puedeSubir = computed(() => ESTADOS_CON_FOTOS.includes(props.orden?.status?.slug));

const inputRef = ref(null);
const archivoError = ref('');
const form = useForm({ photo: null, note: '' });

const urlFoto = (f) => route('repair-orders.avance.photo', [props.orden.id, f.id]);
const fechaFoto = (f) => new Date(f.created_at).toLocaleDateString(locale.value);

function alElegir(e) {
    archivoError.value = '';
    form.clearErrors('photo');
    const archivo = e.target.files?.[0] ?? null;
    form.photo = null;
    if (!archivo) return;
    if (!TIPOS.includes(archivo.type)) {
        archivoError.value = t('expediente.evidencia.errorTipo');
        e.target.value = '';
        return;
    }
    if (archivo.size > MAX_BYTES) {
        archivoError.value = t('expediente.evidencia.errorTamano');
        e.target.value = '';
        return;
    }
    form.photo = archivo;
}

function enviar() {
    if (!form.photo || form.processing) return;
    form.post(route('repair-orders.avance.store', props.orden.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (inputRef.value) inputRef.value.value = '';
        },
    });
}

const confirmacion = ref({ visible: false, foto: null });
const errorBorrado = ref('');

function pedirBorrar(f) {
    errorBorrado.value = '';
    confirmacion.value = { visible: true, foto: f };
}

function borrarConfirmado() {
    const foto = confirmacion.value.foto;
    confirmacion.value = { visible: false, foto: null };
    if (!foto) return;
    router.delete(route('repair-orders.avance.destroy', [props.orden.id, foto.id]), {
        preserveScroll: true,
        onError: () => { errorBorrado.value = t('expediente.evidencia.errorEliminar'); },
    });
}
</script>

<template>
    <section class="rounded-xl border border-line bg-surface p-5 md:p-6">
        <header class="mb-4">
            <h3 class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('expediente.evidencia.titulo') }}</h3>
            <p class="mt-1 text-ds-caption text-ink-secondary">{{ $t('expediente.evidencia.intro') }}</p>
        </header>

        <form v-if="puedeSubir" class="mb-6 grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-start" @submit.prevent="enviar">
            <div>
                <label for="avance-foto" class="mb-1 block text-ds-caption font-semibold text-ink">{{ $t('expediente.evidencia.archivo') }}</label>
                <input
                    id="avance-foto"
                    ref="inputRef"
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/bmp,image/webp"
                    aria-describedby="avance-foto-ayuda"
                    :aria-invalid="Boolean(archivoError || form.errors.photo)"
                    class="ds-focus block w-full text-ds-body text-ink file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:font-semibold file:text-ink"
                    @change="alElegir"
                />
                <p id="avance-foto-ayuda" class="mt-1 text-ds-caption text-ink-muted">{{ $t('expediente.evidencia.archivoAyuda') }}</p>
                <p v-if="archivoError || form.errors.photo" role="alert" class="mt-1 text-ds-caption text-danger-text">{{ archivoError || form.errors.photo }}</p>
            </div>

            <div>
                <label for="avance-nota" class="mb-1 block text-ds-caption font-semibold text-ink">{{ $t('expediente.evidencia.nota') }}</label>
                <input
                    id="avance-nota"
                    v-model="form.note"
                    type="text"
                    maxlength="255"
                    :placeholder="$t('expediente.evidencia.notaPlaceholder')"
                    :aria-invalid="Boolean(form.errors.note)"
                    class="ds-focus w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink"
                />
                <p v-if="form.errors.note" role="alert" class="mt-1 text-ds-caption text-danger-text">{{ form.errors.note }}</p>
            </div>

            <button
                type="submit"
                :disabled="!form.photo || form.processing"
                class="ds-focus rounded-lg bg-navy px-4 py-2 text-ds-control font-semibold text-white disabled:opacity-40"
            >
                {{ form.processing ? $t('expediente.evidencia.subiendo') : $t('expediente.evidencia.subir') }}
            </button>
        </form>
        <p v-else class="mb-6 rounded-lg bg-surface-2 px-4 py-3 text-ds-caption text-ink-secondary">
            {{ $t('expediente.evidencia.noSubir') }}
        </p>

        <p v-if="errorBorrado" role="alert" class="mb-4 text-ds-caption text-danger-text">{{ errorBorrado }}</p>

        <ul v-if="fotos.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
            <li v-for="f in fotos" :key="f.id" class="flex flex-col overflow-hidden rounded-lg border border-line bg-surface">
                <a :href="urlFoto(f)" target="_blank" rel="noopener" class="ds-focus block bg-surface-2">
                    <img :src="urlFoto(f)" :alt="$t('expediente.evidencia.alt')" loading="lazy" class="h-36 w-full object-cover" />
                </a>
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <p class="text-ds-caption text-ink-muted tabular-nums">{{ fechaFoto(f) }}</p>
                    <p v-if="f.note" class="break-words text-ds-body text-ink">{{ f.note }}</p>
                    <button
                        v-if="puedeBorrar"
                        type="button"
                        class="ds-focus mt-auto self-start text-ds-control font-semibold text-danger-text underline-offset-2 hover:underline"
                        @click="pedirBorrar(f)"
                    >{{ $t('expediente.evidencia.eliminar') }}</button>
                </div>
            </li>
        </ul>
        <p v-else class="text-ds-body text-ink-muted">{{ $t('expediente.evidencia.vacio') }}</p>

        <ConfirmModal
            :show="confirmacion.visible"
            :title="$t('expediente.evidencia.eliminarTitulo')"
            :message="$t('expediente.evidencia.eliminarMsg')"
            :confirm-text="$t('expediente.evidencia.confirmarEliminar')"
            @confirm="borrarConfirmado"
            @cancel="confirmacion = { visible: false, foto: null }"
        />
    </section>
</template>
