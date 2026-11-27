<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    orden: { type: Object, required: true },
    mechanics: { type: Array, default: () => [] },
});

const { t, locale } = useI18n();
const page = usePage();
const esAdmin = computed(() => page.props.auth.isAdmin);
const nombreAutor = computed(() => page.props.auth.user?.name ?? '');

// Nueva nota: solo texto, fecha y autor. Las fotos viven en la pestaña Evidencia.
// Las notas antiguas con evidence_photos siguen mostrándolas como histórico de solo lectura.
const notas = computed(() => props.orden.follow_ups ?? []);
const cerrada = computed(() => props.orden.esta_cerrada === true);

function ahoraLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

const form = useForm({
    mechanic_id: props.orden.mechanic_id ?? '',
    description: '',
    date: ahoraLocal(),
});

function limpiarFormulario() {
    form.description = '';
    form.date = ahoraLocal();
}

function enviar() {
    if (form.processing) return;
    if (!form.description.trim()) {
        form.setError('description', t('historialExp.textoError'));
        return;
    }
    form.transform(data => {
        // El mecánico escribe siempre con su propia identidad: el backend lo fuerza igual.
        if (esAdmin.value) return data;
        const { mechanic_id, ...sinAutor } = data;
        return sinAutor;
    }).post(route('repair-orders.follow-ups.store', props.orden.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: limpiarFormulario,
    });
}

const errorBorrado = ref('');
const confirmacion = ref({ visible: false, id: null });

function pedirBorrar(id) {
    errorBorrado.value = '';
    confirmacion.value = { visible: true, id };
}

function borrarConfirmado() {
    const id = confirmacion.value.id;
    confirmacion.value = { visible: false, id: null };
    if (!id) return;
    router.delete(route('repair-orders.follow-ups.destroy', { order: props.orden.id, followUp: id }), {
        preserveScroll: true,
        onError: () => { errorBorrado.value = t('historialExp.errorEliminar'); },
    });
}

const fecha = (valor) => new Date(String(valor).replace(' ', 'T')).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' });
const urlEvidencia = (nota, i) => route('repair-orders.follow-ups.foto', [props.orden.id, nota.id, i]);

</script>

<template>
    <section class="rounded-xl border border-line bg-surface p-5 md:p-6">
        <header class="mb-4">
            <h3 class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">{{ $t('historialExp.titulo') }}</h3>
            <p class="mt-1 text-ds-caption text-ink-secondary">{{ $t('historialExp.intro') }}</p>
        </header>

        <form v-if="!cerrada" class="mb-6 space-y-4 border-b border-line pb-6" @submit.prevent="enviar">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label v-if="esAdmin" for="bitacora-mecanico" class="mb-1 block text-ds-caption font-semibold text-ink">{{ $t('historialExp.autor') }}</label>
                    <select
                        v-if="esAdmin"
                        id="bitacora-mecanico"
                        v-model="form.mechanic_id"
                        :aria-invalid="Boolean(form.errors.mechanic_id)"
                        class="ds-focus w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink"
                    >
                        <option value="" disabled>{{ $t('followup.seleccionar') }}</option>
                        <option v-for="m in mechanics" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <template v-else>
                        <p class="mb-1 text-ds-caption font-semibold text-ink">{{ $t('historialExp.autor') }}</p>
                        <p class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-ds-body font-semibold text-ink">{{ nombreAutor }}</p>
                    </template>
                    <p v-if="form.errors.mechanic_id" role="alert" class="mt-1 text-ds-caption text-danger-text">{{ form.errors.mechanic_id }}</p>
                </div>

                <div>
                    <label for="bitacora-fecha" class="mb-1 block text-ds-caption font-semibold text-ink">{{ $t('historialExp.fecha') }}</label>
                    <input
                        id="bitacora-fecha"
                        v-model="form.date"
                        type="datetime-local"
                        :aria-invalid="Boolean(form.errors.date)"
                        class="ds-focus w-full rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink"
                    />
                    <p v-if="form.errors.date" role="alert" class="mt-1 text-ds-caption text-danger-text">{{ form.errors.date }}</p>
                </div>
            </div>

            <div>
                <label for="bitacora-texto" class="mb-1 block text-ds-caption font-semibold text-ink">{{ $t('historialExp.texto') }}</label>
                <textarea
                    id="bitacora-texto"
                    v-model="form.description"
                    rows="3"
                    maxlength="2000"
                    :placeholder="$t('historialExp.textoPh')"
                    :aria-invalid="Boolean(form.errors.description)"
                    class="ds-focus w-full resize-y rounded-lg border border-line bg-surface px-3 py-2 text-ds-body text-ink"
                    @input="form.clearErrors('description')"
                ></textarea>
                <p v-if="form.errors.description" role="alert" class="mt-1 text-ds-caption text-danger-text">{{ form.errors.description }}</p>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing || (esAdmin && !form.mechanic_id)"
                    class="ds-focus rounded-lg bg-navy px-4 py-2 text-ds-control font-semibold text-white disabled:opacity-40"
                >{{ form.processing ? $t('historialExp.guardando') : $t('historialExp.agregar') }}</button>
            </div>
        </form>
        <p v-else class="mb-6 rounded-lg bg-surface-2 px-4 py-3 text-ds-caption text-ink-secondary">{{ $t('historialExp.cerradaAviso') }}</p>

        <p v-if="errorBorrado" role="alert" class="mb-4 text-ds-caption text-danger-text">{{ errorBorrado }}</p>

        <ol v-if="notas.length" class="space-y-4">
            <li v-for="n in notas" :key="n.id" class="relative border-l-2 border-line pl-5">
                <span aria-hidden="true" class="absolute -left-[5px] top-3 h-2 w-2 rounded-full bg-navy"></span>
                <article class="rounded-lg border border-line bg-surface-2 p-4">
                    <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <p class="break-words text-ds-control font-semibold text-ink">{{ n.mechanic?.name ?? $t('historialExp.sinAutor') }}</p>
                        <time :datetime="String(n.date).replace(' ', 'T')" class="text-ds-caption text-ink-muted tabular-nums">{{ fecha(n.date) }}</time>
                    </header>
                    <p class="mt-2 whitespace-pre-line break-words text-ds-body text-ink">{{ n.description }}</p>

                    <ul v-if="n.evidence_photos && n.evidence_photos.length" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
                        <li v-for="(photo, pi) in n.evidence_photos" :key="pi">
                            <a :href="urlEvidencia(n, pi)" target="_blank" rel="noopener" class="ds-focus block overflow-hidden rounded-lg border border-line">
                                <img :src="urlEvidencia(n, pi)" :alt="$t('historialExp.fotoAlt')" loading="lazy" class="aspect-square w-full object-cover" />
                            </a>
                        </li>
                    </ul>

                    <div v-if="n.puede_eliminar && !cerrada" class="mt-3">
                        <button
                            type="button"
                            class="ds-focus text-ds-control font-semibold text-danger-text underline-offset-2 hover:underline"
                            @click="pedirBorrar(n.id)"
                        >{{ $t('historialExp.eliminar') }}</button>
                    </div>
                </article>
            </li>
        </ol>
        <p v-else class="text-ds-body text-ink-muted">{{ cerrada ? $t('historialExp.vaciaCerrada') : $t('historialExp.vacia') }}</p>

        <ConfirmModal
            :show="confirmacion.visible"
            :title="$t('historialExp.eliminarTitulo')"
            :message="$t('historialExp.eliminarMsg')"
            :confirm-text="$t('historialExp.confirmarEliminar')"
            @confirm="borrarConfirmado"
            @cancel="confirmacion = { visible: false, id: null }"
        />
    </section>
</template>
