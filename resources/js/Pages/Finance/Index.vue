<script setup>
// Cuentas y finanzas (solo admin). El backend (FinanceController + EstadoFinancieroAction) calcula
// autorizado, pagado y saldo. Esta vista solo formatea y muestra lo que llega por props.
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    cuentasPorCobrar: { type: Array, default: () => [] },
    recentOrders:     { type: Array, default: () => [] },
    resumen:          { type: Object, required: true },
});

// Mismo formato monetario que el resto del expediente: MXN con separador de miles y dos decimales.
const dinero = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n ?? 0));
</script>

<template>
    <Head :title="t('finance.titulo')" />

    <AppShellLayout>
        <PageHeader :title="t('finance.titulo')" />

        <div class="mt-6 space-y-6">
            <!-- Resumen: ingresos del mes y deuda exigible (saldo autorizado pendiente) -->
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2" :aria-label="t('finance.titulo')">
                <div class="rounded-xl border border-line bg-surface p-5">
                    <p class="text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">{{ t('finance.ingresosMes') }}</p>
                    <p class="mt-2 text-ds-metric tabular-nums text-navy">{{ dinero(resumen.ingresosMes) }}</p>
                    <p class="mt-1 text-ds-caption text-ink-muted">{{ t('finance.pagosRegistrados') }}</p>
                </div>
                <div class="rounded-xl border bg-surface p-5"
                     :class="resumen.totalAdeudado > 0 ? 'border-danger-text' : 'border-line'">
                    <p class="text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">{{ t('finance.totalAdeudado') }}</p>
                    <p class="mt-2 text-ds-metric tabular-nums"
                       :class="resumen.totalAdeudado > 0 ? 'text-danger-text' : 'text-success-text'">
                        {{ dinero(resumen.totalAdeudado) }}
                    </p>
                    <p class="mt-1 text-ds-caption text-ink-muted">{{ resumen.totalDeudores }} {{ t('finance.clientesAdeudan') }}</p>
                </div>
            </section>

            <!-- Cuentas por cobrar: órdenes con saldo autorizado > 0 -->
            <section class="space-y-3" :aria-labelledby="'cuentas-titulo'">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="cuentas-titulo" class="text-ds-section-title text-navy">{{ t('finance.cuentasCobrar') }}</h2>
                    <BaseBadge v-if="cuentasPorCobrar.length > 0" variant="danger">
                        {{ cuentasPorCobrar.length }} {{ t('finance.pendientes') }}
                    </BaseBadge>
                </div>

                <EmptyState v-if="cuentasPorCobrar.length === 0" :title="t('finance.sinDeudas')" />

                <div v-else class="overflow-x-auto rounded-xl border border-line bg-surface">
                    <table class="w-full min-w-[720px] text-left text-ds-body">
                        <thead class="border-b border-line bg-surface-2 text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3">{{ t('finance.folio') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.cliente') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.vehiculo') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.estado') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ t('finance.total') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ t('finance.pagado') }}</th>
                                <th scope="col" class="px-4 py-3 text-right">{{ t('finance.saldo') }}</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">{{ t('finance.accion') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="c in cuentasPorCobrar" :key="c.id" class="hover:bg-surface-2">
                                <td class="px-4 py-3 font-semibold text-navy">{{ c.folio }}</td>
                                <td class="px-4 py-3 text-ink">{{ c.cliente }}</td>
                                <td class="px-4 py-3 text-ink-muted">{{ c.vehiculo }}</td>
                                <td class="px-4 py-3 text-ink-secondary">{{ c.status_name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-ink-secondary">{{ dinero(c.total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-ink-secondary">{{ dinero(c.pagado) }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-danger-text">{{ dinero(c.saldo) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('repair-orders.show', c.id)"
                                          class="rounded-lg bg-navy px-3 py-1.5 text-ds-caption font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-ds-focus">
                                        {{ t('finance.verOrden') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Órdenes recientes: el estado financiero se deriva; sin autorización no se muestra como pagado -->
            <section class="space-y-3" :aria-labelledby="'recientes-titulo'">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="recientes-titulo" class="text-ds-section-title text-navy">{{ t('finance.ordenesRecientes') }}</h2>
                    <Link :href="route('repair-orders.index')" class="text-ds-caption font-semibold text-navy underline-offset-2 hover:underline">
                        {{ t('finance.verTablero') }}
                    </Link>
                </div>

                <EmptyState v-if="recentOrders.length === 0" :title="t('finance.sinOrdenes')" />

                <div v-else class="overflow-x-auto rounded-xl border border-line bg-surface">
                    <table class="w-full min-w-[720px] text-left text-ds-body">
                        <thead class="border-b border-line bg-surface-2 text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3">{{ t('finance.folio') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.cliente') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.vehiculo') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.mecanico') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.estado') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('finance.saldo') }}</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">{{ t('finance.accion') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="ord in recentOrders" :key="ord.id" class="hover:bg-surface-2">
                                <td class="px-4 py-3 font-semibold text-navy">{{ ord.folio }}</td>
                                <td class="px-4 py-3 text-ink">{{ ord.cliente }}</td>
                                <td class="px-4 py-3 text-ink-muted">{{ ord.vehiculo }}</td>
                                <td class="px-4 py-3" :class="ord.mecanico ? 'text-ink-secondary' : 'text-warning-text'">
                                    {{ ord.mecanico ?? t('finance.sinAsignar') }}
                                </td>
                                <td class="px-4 py-3 text-ink-secondary">{{ ord.status_name }}</td>
                                <td class="px-4 py-3">
                                    <BaseBadge v-if="!ord.autorizado" variant="neutral">{{ t('payments.sinAutorizado') }}</BaseBadge>
                                    <BaseBadge v-else-if="ord.liquidado" variant="success">{{ t('expediente.liquidado') }}</BaseBadge>
                                    <BaseBadge v-else variant="danger">{{ dinero(ord.saldo) }}</BaseBadge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('repair-orders.show', ord.id)"
                                          class="rounded-lg bg-navy px-3 py-1.5 text-ds-caption font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-ds-focus">
                                        {{ t('finance.verOrden') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppShellLayout>
</template>
