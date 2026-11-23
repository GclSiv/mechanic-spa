<script setup>
// Estado de la orden en el Expediente. Muestra la etapa con BaseBadge y labels de estadoUi.
// Solo ofrece estados operativos por rol; las órdenes cerradas quedan en modo lectura.
// El servidor (RepairOrderController::updateStatus) sigue siendo la autoridad.
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import { opcionesDeEstado, orderStatusVariant, ESTADOS_CERRADOS } from '@/Support/orderStatus.js';

const { t, te } = useI18n();

const props = defineProps({
    orden: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const isAdmin = computed(() => usePage().props.auth?.isAdmin === true);
const opciones = computed(() => opcionesDeEstado(props.statuses, isAdmin.value));
const slugActual = computed(() => props.orden?.status?.slug ?? null);
const cerrada = computed(() => ESTADOS_CERRADOS.includes(slugActual.value));
const puedeCambiar = computed(() => !cerrada.value && opciones.value.length > 0);
const variante = computed(() => orderStatusVariant(slugActual.value));
const etiqueta = (slug) => (slug && te(`estadoUi.${slug}`) ? t(`estadoUi.${slug}`) : t('badges.sinEstado'));

const isOpen = ref(false);
const isLoading = ref(false);
const dropdownRef = ref(null);

function selectStatus(status) {
    if (status.id === props.orden.status_id) {
        isOpen.value = false;
        return;
    }
    isLoading.value = true;
    isOpen.value = false;
    router.patch(
        route('repair-orders.status.update', props.orden.id),
        { status_id: status.id },
        { preserveScroll: true, onFinish: () => { isLoading.value = false; } }
    );
}

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside));
</script>

<template>
    <div ref="dropdownRef" class="relative inline-block text-left">
        <BaseBadge v-if="!puedeCambiar" :variant="variante">{{ etiqueta(slugActual) }}</BaseBadge>

        <button
            v-else
            type="button"
            :disabled="isLoading"
            :aria-expanded="isOpen ? 'true' : 'false'"
            class="ds-focus inline-flex items-center gap-1 rounded-full disabled:opacity-50"
            @click="isOpen = !isOpen"
        >
            <BaseBadge :variant="variante">
                {{ isLoading ? '…' : etiqueta(slugActual) }} <span aria-hidden="true">▾</span>
            </BaseBadge>
        </button>

        <div
            v-if="isOpen"
            class="absolute left-0 z-50 mt-2 w-56 overflow-hidden rounded-xl bg-surface shadow-xl ring-1 ring-black/10"
        >
            <p class="border-b border-line px-4 py-2 text-ds-caption font-semibold uppercase tracking-wider text-ink-muted">
                {{ $t('taller.cambiarEstado') }}
            </p>
            <div class="py-1">
                <button
                    v-for="status in opciones"
                    :key="status.id"
                    type="button"
                    class="ds-focus flex w-full items-center gap-3 px-4 py-2.5 text-left text-ds-control hover:bg-surface-2"
                    :class="status.id === orden.status_id ? 'font-semibold text-navy' : 'text-ink-secondary'"
                    @click="selectStatus(status)"
                >
                    {{ etiqueta(status.slug) }}
                    <span v-if="status.id === orden.status_id" class="ml-auto text-navy" aria-hidden="true">✓</span>
                </button>
            </div>
        </div>
    </div>
</template>
