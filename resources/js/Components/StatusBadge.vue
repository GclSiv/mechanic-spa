<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    orden: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const isOpen = ref(false);
const isLoading = ref(false);

// color_class viene de la BD (ej: "bg-yellow-100 text-yellow-800")
// Fallback por si algún registro no tiene color_class asignado
const fallbackColor = 'bg-gray-100 text-gray-700';

const currentStatus = () =>
    props.statuses.find(s => s.id === props.orden.status_id) ?? null;

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
        {
            preserveScroll: true,
            onFinish: () => { isLoading.value = false; },
        }
    );
}
</script>

<template>
    <div class="relative inline-block text-left">

        <!-- Badge / Trigger -->
        <button
            @click="isOpen = !isOpen"
            :disabled="isLoading"
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider border border-transparent
                   hover:opacity-80 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            :class="currentStatus()?.color_class || fallbackColor"
        >
            <span v-if="isLoading" class="animate-spin">⟳</span>
            <span v-else>●</span>
            {{ currentStatus()?.name ?? 'Sin Estado' }}
            <span class="ml-1 opacity-60">▾</span>
        </button>

        <!-- Dropdown -->
        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                v-click-outside="() => isOpen = false"
                class="absolute left-0 mt-2 w-52 rounded-xl shadow-xl bg-white ring-1 ring-black/10 z-50 overflow-hidden"
            >
                <div class="py-1">
                    <p class="px-4 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        Cambiar Estado
                    </p>
                    <button
                        v-for="status in statuses"
                        :key="status.id"
                        @click="selectStatus(status)"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50 transition-colors"
                        :class="status.id === orden.status_id ? 'font-black' : 'font-medium text-gray-700'"
                    >
                        <span
                            class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0"
                            :class="status.color_class || fallbackColor"
                        ></span>
                        {{ status.name }}
                        <span v-if="status.id === orden.status_id" class="ml-auto text-[#10213E]">✓</span>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
