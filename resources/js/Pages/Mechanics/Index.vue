<script setup>
// Personal (solo admin/owner). La vista solo navega y representa: no calcula comisiones
// y nunca recibe contraseñas ni hashes. La especialidad llega como relación `mechanicType`.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    mechanics: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const errores = computed(() => page.props.errors ?? {});

const confirmDel = ref({ show: false, id: null });
function pedirBaja(id) { confirmDel.value = { show: true, id }; }
function confirmarBaja() {
    router.delete(route('mechanics.destroy', confirmDel.value.id), { preserveScroll: true });
    confirmDel.value = { show: false, id: null };
}

const enlaceBtn = 'ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2';
const enlaceBtnPeligro = 'ds-focus inline-flex h-control items-center justify-center rounded-lg border border-line bg-surface px-3 text-ds-control font-semibold text-danger-text hover:bg-surface-2';
</script>

<template>
    <Head :title="t('personal.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('personal.titulo')" :description="t('personal.total', { n: props.mechanics.length })">
            <Link
                :href="route('mechanics.create')"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('personal.nuevo') }}</Link>
        </PageHeader>

        <p v-if="flash.success" role="status" class="mt-6 rounded-lg border border-line bg-surface-2 px-4 py-3 text-ds-body text-ink">
            {{ flash.success }}
        </p>
        <p v-if="errores.mechanic" role="alert" class="mt-6 rounded-lg border border-danger bg-surface px-4 py-3 text-ds-body text-danger-text">
            {{ errores.mechanic }}
        </p>

        <EmptyState v-if="props.mechanics.length === 0" class="mt-6" :title="t('personal.sinRegistros')" />

        <!-- Escritorio y tablet ancha: tabla -->
        <div v-else class="mt-6 hidden overflow-x-auto rounded-xl border border-line bg-surface md:block">
            <table class="w-full min-w-[640px] text-left text-ds-body">
                <thead class="border-b border-line bg-surface-2 text-ds-label font-semibold text-ink-secondary">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ t('personal.nombre') }}</th>
                        <th scope="col" class="px-4 py-3">{{ t('personal.email') }}</th>
                        <th scope="col" class="px-4 py-3">{{ t('personal.especialidad') }}</th>
                        <th scope="col" class="px-4 py-3">{{ t('personal.genero') }}</th>
                        <th scope="col" class="px-4 py-3">{{ t('personal.telefono') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ t('personal.acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="m in props.mechanics" :key="m.id" class="hover:bg-surface-2">
                        <td class="px-4 py-3 font-semibold text-navy">{{ m.name }}</td>
                        <td class="px-4 py-3 text-ink-secondary">
                            <span v-if="m.email">{{ m.email }}</span>
                            <span v-else class="text-ink-muted">{{ t('personal.sinCuenta') }}</span>
                        </td>
                        <td class="px-4 py-3 text-ink">{{ m.mechanic_type?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink">{{ m.gender?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink-secondary">{{ m.phone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <Link :href="route('mechanics.edit', m.id)" :class="enlaceBtn">{{ t('acciones.editar') }}</Link>
                                <button type="button" :class="enlaceBtnPeligro" @click="pedirBaja(m.id)">{{ t('acciones.eliminar') }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Móvil: tarjetas -->
        <ul v-if="props.mechanics.length > 0" class="mt-6 grid gap-3 md:hidden">
            <li v-for="m in props.mechanics" :key="m.id" class="rounded-xl border border-line bg-surface p-4">
                <p class="text-ds-section-title font-semibold text-navy">{{ m.name }}</p>
                <p class="mt-1 text-ds-caption text-ink-muted">
                    <span v-if="m.email">{{ m.email }}</span>
                    <span v-else>{{ t('personal.sinCuenta') }}</span>
                </p>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-ds-caption">
                    <div>
                        <dt class="text-ink-muted">{{ t('personal.especialidad') }}</dt>
                        <dd class="text-ink">{{ m.mechanic_type?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">{{ t('personal.genero') }}</dt>
                        <dd class="text-ink">{{ m.gender?.name ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex gap-2">
                    <Link :href="route('mechanics.edit', m.id)" :class="enlaceBtn">{{ t('acciones.editar') }}</Link>
                    <button type="button" :class="enlaceBtnPeligro" @click="pedirBaja(m.id)">{{ t('acciones.eliminar') }}</button>
                </div>
            </li>
        </ul>

        <ConfirmModal
            :show="confirmDel.show"
            :title="t('personal.eliminarTitulo')"
            :message="t('personal.eliminarMsg')"
            :confirm-text="t('acciones.eliminar')"
            @confirm="confirmarBaja"
            @cancel="confirmDel.show = false"
        />
    </AppShellLayout>
</template>
