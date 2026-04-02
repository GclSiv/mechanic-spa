<script setup>
defineProps({
    breakdown: Object
});
</script>

<template>
    <div v-if="breakdown" class="w-full max-w-sm text-sm text-gray-700 space-y-2">

        <div class="flex justify-between">
            <span>Refacciones:</span>
            <span>${{ Number(breakdown.parts_total).toFixed(2) }}</span>
        </div>

        <div class="flex justify-between">
            <span>Mano de obra:</span>
            <span>${{ Number(breakdown.labor_total).toFixed(2) }}</span>
        </div>

        <div class="flex justify-between">
            <span>IVA ({{ breakdown.tax_rate }}%):</span>
            <span>${{ Number(breakdown.tax_amount).toFixed(2) }}</span>
        </div>

        <div class="border-t pt-2 flex justify-between font-bold text-lg">
            <span>Total:</span>
            <span>${{ Number(breakdown.grand_total).toFixed(2) }}</span>
        </div>

    </div>
    <div v-else class="text-gray-400 text-sm italic">Calculando totales...</div>
</template>