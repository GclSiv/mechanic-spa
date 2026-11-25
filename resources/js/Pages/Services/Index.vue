<script setup>
// Catálogo de servicios con comisión fija (solo admin/owner). La vista solo navega y representa.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    services: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const errores = computed(() => page.props.errors ?? {});

const money = (n) => '$' + Number(n ?? 0).toFixed(2);

const confirmDel = ref({ show: false, id: null });
function pedirBaja(id) { confirmDel.value = { show: true, id }; }
function confirmarBaja() {
    router.delete(route('services.destroy', confirmDel.value.id), { preserveScroll: true });
    confirmDel.value = { show: false, id: null };
}

const enlaceBtn = 'ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2';
const enlaceBtnPeligro = 'ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-danger-text hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <Head :title="t('serviciosUi.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('serviciosUi.titulo')" :description="t('serviciosUi.descripcion')">
            <Link
                :href="route('services.create')"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('serviciosUi.nuevo') }}</Link>
        </PageHeader>

        <p v-if="flash.success" role="status" class="mt-6 rounded-lg border border-line bg-surface-2 px-4 py-3 text-ds-body text-ink">{{ flash.success }}</p>
        <p v-if="errores.service" role="alert" class="mt-6 rounded-lg border border-danger bg-surface px-4 py-3 text-ds-body text-danger-text">{{ errores.service }}</p>

        <EmptyState v-if="services.length === 0" class="mt-6" :title="t('serviciosUi.sinRegistros')" />

        <div v-else class="mt-6 hidden overflow-x-auto rounded-xl border border-line bg-surface md:block">
            <table class="w-full min-w-[640px] text-left text-ds-body">
                <thead class="border-b border-line bg-surface-2 text-ds-label font-semibold text-ink-secondary">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ t('serviciosUi.nombre') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ t('serviciosUi.comision') }}</th>
                        <th scope="col" class="px-4 py-3">{{ t('serviciosUi.estado') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ t('serviciosUi.acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="s in services" :key="s.id" class="hover:bg-surface-2">
                        <td class="px-4 py-3 font-semibold text-navy">{{ s.name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-ink">{{ money(s.commission_amount) }}</td>
                        <td class="px-4 py-3 text-ink-secondary">{{ s.is_active ? t('serviciosUi.activo') : t('serviciosUi.inactivo') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <Link :href="route('services.edit', s.id)" :class="enlaceBtn">{{ t('acciones.editar') }}</Link>
                                <button type="button" :class="enlaceBtnPeligro" :disabled="s.en_uso" :title="s.en_uso ? t('serviciosUi.enUsoAviso') : ''" @click="pedirBaja(s.id)">{{ t('acciones.eliminar') }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="services.length > 0" class="mt-6 grid gap-3 md:hidden">
            <li v-for="s in services" :key="s.id" class="rounded-xl border border-line bg-surface p-4">
                <p class="text-ds-section-title font-semibold text-navy">{{ s.name }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-ds-caption">
                    <div>
                        <dt class="text-ink-muted">{{ t('serviciosUi.comision') }}</dt>
                        <dd class="tabular-nums text-ink">{{ money(s.commission_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">{{ t('serviciosUi.estado') }}</dt>
                        <dd class="text-ink">{{ s.is_active ? t('serviciosUi.activo') : t('serviciosUi.inactivo') }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex gap-2">
                    <Link :href="route('services.edit', s.id)" :class="enlaceBtn">{{ t('acciones.editar') }}</Link>
                    <button type="button" :class="enlaceBtnPeligro" :disabled="s.en_uso" @click="pedirBaja(s.id)">{{ t('acciones.eliminar') }}</button>
                </div>
            </li>
        </ul>

        <ConfirmModal
            :show="confirmDel.show"
            :title="t('serviciosUi.eliminarTitulo')"
            :message="t('serviciosUi.eliminarMsg')"
            :confirm-text="t('acciones.eliminar')"
            @confirm="confirmarBaja"
            @cancel="confirmDel.show = false"
        />
    </AppShellLayout>
</template>
