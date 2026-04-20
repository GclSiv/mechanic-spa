<script setup>
defineProps({
    show:    { type: Boolean, default: false },
    title:   { type: String,  default: '¿Estás seguro?' },
    message: { type: String,  default: 'Esta acción no se puede deshacer.' },
    confirmText: { type: String, default: 'Sí, eliminar' },
    cancelText:  { type: String, default: 'Cancelar' },
    danger:  { type: Boolean, default: true },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="emit('cancel')">

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="show"
                        class="bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden border border-gray-100">

                        <!-- Header con color según tipo -->
                        <div class="px-6 pt-6 pb-4 text-center">
                            <!-- Ícono -->
                            <div class="w-14 h-14 mx-auto mb-4 rounded-full flex items-center justify-center text-2xl"
                                :class="danger ? 'bg-red-100' : 'bg-blue-100'">
                                {{ danger ? '🗑️' : '❓' }}
                            </div>

                            <h3 class="text-lg font-black text-[#10213E] mb-2">{{ title }}</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">{{ message }}</p>
                        </div>

                        <!-- Divisor -->
                        <div class="border-t border-gray-100 mx-4"></div>

                        <!-- Botones -->
                        <div class="flex gap-3 p-4">
                            <button @click="emit('cancel')"
                                class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-600 text-sm font-bold hover:bg-gray-50 transition">
                                {{ cancelText }}
                            </button>
                            <button @click="emit('confirm')"
                                class="flex-1 px-4 py-2.5 rounded-xl text-white text-sm font-black transition shadow-sm"
                                :class="danger
                                    ? 'bg-[#EE2857] hover:bg-red-700'
                                    : 'bg-[#10213E] hover:bg-blue-900'">
                                {{ confirmText }}
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
