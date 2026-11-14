<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    orden:     Object,
    breakdown: Object,
});
</script>

<template>
    <div class="w-full max-w-sm ml-auto bg-white p-5 rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto"><table class="w-full text-sm text-gray-700">
            <tbody>
                <tr>
                    <td class="py-1.5 font-bold text-gray-500 text-right pr-4">{{ t('ordertotals.subtotalPartes') }}</td>
                    <td class="py-1.5 text-right w-24 font-medium">${{ Number(breakdown?.parts_subtotal || 0).toFixed(2) }}</td>
                </tr>
                <tr>
                    <td class="py-1.5 font-bold text-gray-500 text-right pr-4">{{ t('ordertotals.subtotalLabor') }}</td>
                    <td class="py-1.5 text-right w-24 font-medium">${{ Number(breakdown?.labor_subtotal || 0).toFixed(2) }}</td>
                </tr>
                <tr class="border-t border-gray-100">
                    <td class="py-2 font-bold text-gray-800 text-right pr-4 uppercase">{{ t('ordertotals.subtotalGeneral') }}</td>
                    <td class="py-2 text-right font-bold text-gray-800 w-24">${{ Number(breakdown?.subtotal || 0).toFixed(2) }}</td>
                </tr>
                <tr>
                    <td class="py-1.5 font-black text-right pr-4 text-[#EE2857]">
                        {{ breakdown?.tax_rate == 8.75 ? 'Tax' : 'IVA' }} ({{ breakdown?.tax_rate }}%):
                    </td>
                    <td class="py-1.5 text-right font-bold w-24 text-[#EE2857]">${{ Number(breakdown?.tax || 0).toFixed(2) }}</td>
                </tr>
                <tr class="border-t-2 border-[#10213E] text-lg">
                    <td class="py-3 font-black text-right pr-4 text-[#10213E]">{{ t('ordertotals.total') }}</td>
                    <td class="py-3 text-right font-black text-[#10213E] w-24">${{ Number(breakdown?.total || 0).toFixed(2) }}</td>
                </tr>
            </tbody>
        </table></div>
        <p v-if="breakdown?.tax_rate == 8.75" class="text-[10px] text-gray-400 text-right mt-2 italic leading-tight">
            {{ t('ordertotals.notaUsa') }}
        </p>
        <p v-else class="text-[10px] text-gray-400 text-right mt-2 italic leading-tight">
            * Tax ({{ breakdown?.tax_rate }}%) {{ t('ordertotals.notaMx') }}
        </p>
    </div>
</template>
