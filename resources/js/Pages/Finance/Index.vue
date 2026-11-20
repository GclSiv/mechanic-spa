<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    cuentasPorCobrar: Array,
    recentOrders:     Array,
    resumen:          Object,
});

function fmt(val) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val ?? 0);
}
</script>

<template>
    <AuthenticatedLayout title="Cuentas">
        <template #header>
            <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                💰 {{ $t('finance.titulo') }}
            </h2>
        </template>

        <div class="py-8 min-h-screen transition-colors">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- ── Resumen financiero ── -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-gradient-to-br from-[#10213E] to-blue-900 rounded-2xl p-5 text-white shadow-sm">
                        <p class="text-xs font-bold text-blue-200 uppercase tracking-widest mb-1">{{ $t('finance.ingresosMes') }}</p>
                        <p class="text-2xl font-black">{{ fmt(resumen.ingresosMes) }}</p>
                        <p class="text-xs text-blue-300 mt-1">{{ $t('finance.pagosRegistrados') }}</p>
                    </div>
                    <div class="rounded-2xl p-5 shadow-sm border"
                        :class="resumen.totalAdeudado > 0 ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'">
                        <p class="text-xs font-bold uppercase tracking-widest mb-1"
                            :class="resumen.totalAdeudado > 0 ? 'text-red-500' : 'text-green-600'">
                            {{ $t('finance.totalAdeudado') }}
                        </p>
                        <p class="text-2xl font-black"
                            :class="resumen.totalAdeudado > 0 ? 'text-red-700' : 'text-green-700'">
                            {{ fmt(resumen.totalAdeudado) }}
                        </p>
                        <p class="text-xs mt-1"
                            :class="resumen.totalAdeudado > 0 ? 'text-red-400' : 'text-green-500'">
                            {{ resumen.totalDeudores }} {{ $t('finance.clientesAdeudan') }}
                        </p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">{{ $t('finance.totalOrdenes') }}</p>
                        <p class="text-2xl font-black text-[#10213E]">{{ recentOrders.length }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $t('finance.ultimasOrdenes') }}</p>
                    </div>
                </div>

                <!-- ── Cuentas por Cobrar ── -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h3 class="font-black text-sm text-[#10213E] uppercase tracking-wider">
                            🔴 {{ $t('finance.cuentasCobrar') }}
                        </h3>
                        <span v-if="cuentasPorCobrar.length > 0"
                            class="bg-red-100 text-red-700 text-xs font-black px-2.5 py-1 rounded-full">
                            {{ cuentasPorCobrar.length }} {{ $t('finance.pendientes') }}
                        </span>
                    </div>

                    <div v-if="cuentasPorCobrar.length === 0"
                        class="px-6 py-10 text-center text-gray-400 text-sm italic">
                        ✅ {{ $t('finance.sinDeudas') }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <div class="overflow-x-auto"><table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                                <tr>
                                    <th class="px-5 py-3">{{ $t('finance.folio') }}</th>
                                    <th class="px-5 py-3">{{ $t('finance.cliente') }}</th>
                                    <th class="px-5 py-3">{{ $t('finance.vehiculo') }}</th>
                                    <th class="px-5 py-3 text-center">{{ $t('finance.estado') }}</th>
                                    <th class="px-5 py-3 text-right">{{ $t('finance.total') }}</th>
                                    <th class="px-5 py-3 text-right">{{ $t('finance.pagado') }}</th>
                                    <th class="px-5 py-3 text-right">{{ $t('finance.saldo') }}</th>
                                    <th class="px-5 py-3 text-center">{{ $t('finance.accion') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="c in cuentasPorCobrar" :key="c.id"
                                    class="hover:bg-red-50 transition-colors">
                                    <td class="px-5 py-3 font-black text-[#10213E] text-xs">{{ c.folio }}</td>
                                    <td class="px-5 py-3 font-medium text-gray-800">{{ c.cliente }}</td>
                                    <td class="px-5 py-3 text-gray-500 text-xs">{{ c.vehiculo }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
                                            :class="c.status_color">
                                            {{ c.status_name }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-600 text-xs">{{ fmt(c.total) }}</td>
                                    <td class="px-5 py-3 text-right text-green-600 text-xs font-bold">{{ fmt(c.pagado) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <span class="font-black text-[#EE2857]">{{ fmt(c.saldo) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <Link :href="route('repair-orders.show', c.id)"
                                            class="bg-[#10213E] hover:bg-blue-900 text-white text-[10px] font-black px-3 py-1.5 rounded-lg transition">
                                            {{ $t('finance.verOrden') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table></div>
                    </div>
                </div>

                <!-- ── Órdenes Recientes ── -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h3 class="font-black text-sm text-[#10213E] uppercase tracking-wider">
                            🔧 {{ $t('finance.ordenesRecientes') }}
                        </h3>
                        <Link :href="route('repair-orders.index')"
                            class="text-xs text-[#10213E] font-black hover:underline">
                            {{ $t('finance.verTablero') }} →
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="overflow-x-auto"><table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                                <tr>
                                    <th class="px-5 py-3">{{ $t('finance.folio') }}</th>
                                    <th class="px-5 py-3">{{ $t('finance.cliente') }}</th>
                                    <th class="px-5 py-3">{{ $t('finance.vehiculo') }}</th>
                                    <th class="px-5 py-3">{{ $t('finance.mecanico') }}</th>
                                    <th class="px-5 py-3 text-center">{{ $t('finance.estado') }}</th>
                                    <th class="px-5 py-3 text-center">{{ $t('finance.saldo') }}</th>
                                    <th class="px-5 py-3 text-center">{{ $t('finance.accion') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="ord in recentOrders" :key="ord.id"
                                    class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3 font-black text-[#10213E] text-xs">{{ ord.folio }}</td>
                                    <td class="px-5 py-3 font-medium text-gray-800 text-xs">{{ ord.cliente }}</td>
                                    <td class="px-5 py-3 text-gray-500 text-xs">{{ ord.vehiculo }}</td>
                                    <td class="px-5 py-3 text-xs"
                                        :class="ord.mecanico ? 'text-gray-600' : 'text-amber-500 font-bold'">
                                        {{ ord.mecanico ?? $t('finance.sinAsignar') }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
                                            :class="ord.status_color">
                                            {{ ord.status_name }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span v-if="ord.liquidado"
                                            class="bg-emerald-100 text-emerald-700 text-[10px] font-black px-2 py-0.5 rounded-full">
                                            ✅ {{ $t('finance.pagado') }}
                                        </span>
                                        <span v-else
                                            class="bg-red-100 text-red-700 text-[10px] font-black px-2 py-0.5 rounded-full">
                                            ⚠️ {{ fmt(ord.saldo) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <Link :href="route('repair-orders.show', ord.id)"
                                            class="bg-[#10213E] hover:bg-blue-900 text-white text-[10px] font-black px-3 py-1.5 rounded-lg transition">
                                            {{ $t('finance.verOrden') }}
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="recentOrders.length === 0">
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-400 italic">
                                        {{ $t('finance.sinOrdenes') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table></div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
