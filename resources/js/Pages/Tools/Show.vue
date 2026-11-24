<script setup>
// Ficha de herramienta (solo admin/owner). Presenta datos y evidencia; el backend valida y autoriza cada archivo.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { CLAVE_ESTADO, CLAVE_CONDICION, VARIANTE_ESTADO } from '@/Components/Tools/presentacion.js';
import { dinero } from '@/Components/Tools/etiquetas.js';

const { t } = useI18n();

const props = defineProps({
    tool: { type: Object, required: true },
    evidencia: { type: Array, default: () => [] },
});

const esBaja = computed(() => props.tool.status === 'baja');
const fechaCompra = computed(() => props.tool.purchase_date ? String(props.tool.purchase_date).slice(0, 10) : t('herramientasUi.sinDato'));
const ACEPTADOS = 'image/jpeg,image/png,image/webp,video/mp4,video/webm';

const formEvidencia = useForm({ evidencia: null });
function agregarEvidencia() {
    formEvidencia.post(route('tools.media.store', props.tool.id), {
        preserveScroll: true,
        onSuccess: () => formEvidencia.reset(),
    });
}

const confirmEvidencia = ref({ show: false, id: null });
function pedirEliminarEvidencia(id) { confirmEvidencia.value = { show: true, id }; }
function eliminarEvidencia() {
    router.delete(route('tools.media.destroy', [props.tool.id, confirmEvidencia.value.id]), { preserveScroll: true });
    confirmEvidencia.value = { show: false, id: null };
}

const tamano = (bytes) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<template>
    <Head :title="tool.name" />

    <AppShellLayout>
        <PageHeader :title="tool.name" :description="tool.internal_code">
            <Link
                :href="route('tools.edit', tool.id)"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('herramientasUi.editar') }}</Link>
            <Link
                :href="route('tools.index')"
                class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
            >{{ t('herramientasUi.volver') }}</Link>
        </PageHeader>

        <p v-if="esBaja" class="mt-6 rounded-lg border border-line bg-surface-2 px-4 py-3 text-ds-body text-ink-secondary" role="status">
            {{ t('herramientasUi.bajaAviso') }}
        </p>

        <div class="mt-6 grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5" aria-labelledby="ficha-identificacion">
                <h2 id="ficha-identificacion" class="text-ds-section-title font-semibold text-navy">{{ t('herramientasUi.seccionIdentificacion') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoNombre') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink">{{ tool.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoCodigo') }}</dt>
                        <dd class="mt-1 font-mono text-ds-body uppercase text-ink">{{ tool.internal_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoMarca') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink">{{ tool.brand || t('herramientasUi.sinDato') }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoModelo') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink">{{ tool.model || t('herramientasUi.sinDato') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoSerie') }}</dt>
                        <dd class="mt-1 font-mono text-ds-body text-ink">{{ tool.serial_number || t('herramientasUi.sinDato') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5" aria-labelledby="ficha-ubicacion">
                <h2 id="ficha-ubicacion" class="text-ds-section-title font-semibold text-navy">{{ t('herramientasUi.seccionUbicacion') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoCategoria') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink">{{ tool.category?.name ?? t('herramientasUi.sinCategoria') }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoUbicacion') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink">{{ tool.location?.name ?? t('herramientasUi.sinUbicacion') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5" aria-labelledby="ficha-estado">
                <h2 id="ficha-estado" class="text-ds-section-title font-semibold text-navy">{{ t('herramientasUi.seccionEstado') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoEstado') }}</dt>
                        <dd class="mt-1">
                            <BaseBadge :variant="VARIANTE_ESTADO[tool.status] ?? 'neutral'">{{ t(`herramientasUi.${CLAVE_ESTADO[tool.status] ?? 'estado_disponible'}`) }}</BaseBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoCondicion') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink">
                            {{ tool.condition ? t(`herramientasUi.${CLAVE_CONDICION[tool.condition]}`) : t('herramientasUi.sinEspecificar') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="min-w-0 space-y-4 rounded-xl border border-line bg-surface p-5" aria-labelledby="ficha-administrativos">
                <h2 id="ficha-administrativos" class="text-ds-section-title font-semibold text-navy">{{ t('herramientasUi.seccionAdministrativos') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoFechaCompra') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink tabular-nums">{{ fechaCompra }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoCosto') }}</dt>
                        <dd class="mt-1 text-ds-body text-ink tabular-nums">{{ dinero(tool.purchase_cost) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-ds-label font-semibold uppercase text-ink-muted">{{ t('herramientasUi.campoNotas') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-ds-body text-ink">{{ tool.notes || t('herramientasUi.sinDato') }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="mt-6 min-w-0 space-y-5 rounded-xl border border-line bg-surface p-5" aria-labelledby="ficha-evidencia">
            <h2 id="ficha-evidencia" class="text-ds-section-title font-semibold text-navy">{{ t('herramientasUi.seccionEvidencia') }}</h2>

            <p v-if="evidencia.length === 0" class="text-ds-body text-ink-muted">{{ t('herramientasUi.evidenciaVacia') }}</p>

            <ul v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="e in evidencia" :key="e.id" class="min-w-0 overflow-hidden rounded-lg border border-line bg-surface-2">
                    <img
                        v-if="e.media_type === 'image'"
                        :src="e.url"
                        :alt="e.original_name"
                        loading="lazy"
                        class="aspect-video w-full bg-surface object-contain"
                    />
                    <video
                        v-else
                        :src="e.url"
                        controls
                        preload="metadata"
                        playsinline
                        class="aspect-video w-full bg-black"
                    >{{ t('herramientasUi.videoNoSoportado') }}</video>
                    <div class="flex items-center justify-between gap-3 p-3">
                        <div class="min-w-0">
                            <p class="truncate text-ds-caption font-semibold text-ink">{{ e.original_name }}</p>
                            <p class="text-ds-caption text-ink-muted tabular-nums">
                                {{ e.media_type === 'image' ? t('herramientasUi.evidenciaFoto') : t('herramientasUi.evidenciaVideo') }} · {{ tamano(e.size) }}
                            </p>
                        </div>
                        <button type="button" class="ds-focus shrink-0 rounded text-ds-control font-semibold text-danger-text hover:underline" @click="pedirEliminarEvidencia(e.id)">
                            {{ t('herramientasUi.evidenciaEliminar') }}
                        </button>
                    </div>
                </li>
            </ul>

            <form class="space-y-3 border-t border-line pt-5" novalidate @submit.prevent="agregarEvidencia">
                <label for="evidencia-archivo" class="block text-ds-label font-semibold text-ink-secondary">{{ t('herramientasUi.evidenciaAgregar') }}</label>
                <p class="text-ds-caption text-ink-muted">{{ t('herramientasUi.evidenciaAyuda') }}</p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        id="evidencia-archivo"
                        type="file"
                        :accept="ACEPTADOS"
                        class="ds-focus min-w-0 flex-1 text-ds-body text-ink file:mr-3 file:rounded-lg file:border file:border-line file:bg-surface file:px-3 file:py-2 file:font-semibold file:text-navy"
                        :aria-invalid="!!formEvidencia.errors.evidencia"
                        @change="formEvidencia.evidencia = $event.target.files[0] ?? null"
                    />
                    <button
                        type="submit"
                        :disabled="!formEvidencia.evidencia || formEvidencia.processing"
                        class="ds-focus h-control shrink-0 rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-50"
                    >{{ t('herramientasUi.evidenciaAgregar') }}</button>
                </div>
                <p v-if="formEvidencia.errors.evidencia" class="text-ds-caption text-danger-text" role="alert">{{ formEvidencia.errors.evidencia }}</p>
            </form>
        </section>

        <ConfirmModal
            :show="confirmEvidencia.show"
            :title="t('herramientasUi.evidenciaEliminarTitulo')"
            :message="t('herramientasUi.evidenciaEliminarMensaje')"
            :confirm-text="t('herramientasUi.evidenciaEliminarConfirmar')"
            :cancel-text="t('herramientasUi.cancelar')"
            @confirm="eliminarEvidencia"
            @cancel="confirmEvidencia = { show: false, id: null }"
        />
    </AppShellLayout>
</template>
