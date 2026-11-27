<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ConfirmModal from '@/Components/ConfirmModal.vue';

// Solo se renderiza para owner/admin (RepairOrders/Show recibe comunicacion solo para ellos).
const props = defineProps({
    trackingUrl: { type: String, required: true },
    orderId: { type: Number, required: true },
});

const copiado = ref(false);
const confirmando = ref(false);
const regenerando = ref(false);

async function copiar() {
    try {
        await navigator.clipboard.writeText(props.trackingUrl);
        copiado.value = true;
        setTimeout(() => { copiado.value = false; }, 2000);
    } catch (e) {
        copiado.value = false;
    }
}

function regenerar() {
    regenerando.value = true;
    router.post(route('repair-orders.tracking.regenerate', props.orderId), {}, {
        preserveScroll: true,
        onFinish: () => {
            regenerando.value = false;
            confirmando.value = false;
        },
    });
}
</script>

<template>
    <section class="rounded-xl border border-line bg-surface p-4">
        <h2 class="mb-2 text-ds-label font-semibold uppercase tracking-wider text-ink-muted">Enlace de seguimiento</h2>
        <p class="mb-3 break-all text-sm text-ink">{{ trackingUrl }}</p>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="copiar"
                class="min-h-[44px] rounded-lg border border-line px-4 text-ink">
                {{ copiado ? 'Copiado' : 'Copiar enlace' }}
            </button>
            <a :href="trackingUrl" target="_blank" rel="noopener"
                class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-ink">Abrir</a>
            <button type="button" :disabled="regenerando" @click="confirmando = true"
                class="min-h-[44px] rounded-lg border border-danger px-4 text-danger-text disabled:opacity-50">
                Regenerar enlace de seguimiento
            </button>
        </div>
        <ConfirmModal
            :show="confirmando"
            title="Regenerar enlace de seguimiento"
            message="El enlace anterior dejará de funcionar inmediatamente."
            confirm-text="Regenerar enlace"
            cancel-text="Cancelar"
            :danger="true"
            @confirm="regenerar"
            @cancel="confirmando = false"
        />
    </section>
</template>
