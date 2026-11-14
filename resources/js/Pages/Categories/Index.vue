<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({ categories: Array });

// ── Modal crear / editar ──────────────────────────────────────────────
const showModal  = ref(false);
const editTarget = ref(null);   // null = crear, object = editar

const form = useForm({ name: '' });

function openCreate() {
    editTarget.value = null;
    form.reset();
    showModal.value = true;
}

function openEdit(cat) {
    editTarget.value = cat;
    form.name = cat.name;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    form.reset();
    editTarget.value = null;
}

function submit() {
    if (editTarget.value) {
        form.put(route('categories.update', editTarget.value.id), {
            onSuccess: closeModal,
        });
    } else {
        form.post(route('categories.store'), {
            onSuccess: closeModal,
        });
    }
}

// ── Eliminar ──────────────────────────────────────────────────────────
const confirmDel = ref({ show: false, category: null });
function askDelete(cat) { confirmDel.value = { show: true, category: cat }; }
function doDel() {
    useForm({}).delete(route('categories.destroy', confirmDel.value.category.id), {
        preserveScroll: true,
    });
    confirmDel.value = { show: false, category: null };
}
</script>

<template>
    <AuthenticatedLayout :title="$t('categories.titulo')">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-xl text-[#10213E] uppercase tracking-wider">
                    🏷️ {{ $t('categories.titulo') }}
                </h2>
                <div class="flex items-center gap-3">
                    <!-- Volver a Inventario -->
                    <Link :href="route('parts.index')"
                        class="text-sm text-gray-500 hover:text-[#10213E] font-bold transition">
                        ← {{ $t('inventario.titulo') }}
                    </Link>
                    <!-- Nueva categoría -->
                    <button @click="openCreate"
                        class="bg-[#10213E] hover:bg-blue-900 text-white font-bold py-2 px-4 rounded-lg shadow-md transition text-sm uppercase">
                        + {{ $t('categories.nueva') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 min-h-screen">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-[#10213E] px-6 py-3 flex justify-between items-center">
                        <p class="text-white text-xs font-bold uppercase tracking-widest">
                            {{ categories.length }} {{ $t('categories.registradas') }}
                        </p>
                    </div>

                    <div class="overflow-x-auto"><table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b">
                            <tr>
                                <th class="px-5 py-3">{{ $t('categories.nombre') }}</th>
                                <th class="px-5 py-3">Slug</th>
                                <th class="px-5 py-3 text-center">{{ $t('categories.refacciones') }}</th>
                                <th class="px-5 py-3 text-center">{{ $t('categories.acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="cat in categories" :key="cat.id"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4">
                                    <span class="inline-block bg-blue-100 text-blue-700 text-xs font-black px-2.5 py-1 rounded-full">
                                        {{ cat.name }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 font-mono text-gray-400 text-xs">{{ cat.slug }}</td>
                                <td class="px-5 py-4 text-center">
                                    <span class="font-black text-[#10213E]">{{ cat.parts_count }}</span>
                                    <span class="text-gray-400 text-xs ml-1">{{ $t('categories.partes') }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-center gap-2">
                                        <button @click="openEdit(cat)"
                                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                            ✏️ {{ $t('acciones.editar') }}
                                        </button>
                                        <button @click="askDelete(cat)"
                                            class="bg-red-50 hover:bg-red-100 text-[#EE2857] font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                            ✕
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="categories.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-gray-300 italic">
                                    {{ $t('categories.sinRegistros') }}
                                </td>
                            </tr>
                        </tbody>
                    </table></div>
                </div>

            </div>
        </div>

        <!-- Modal Crear / Editar -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                    @click.self="closeModal">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                        <!-- Header -->
                        <div class="bg-[#10213E] px-6 py-4 flex justify-between items-center">
                            <h3 class="text-white font-black text-sm uppercase tracking-wider">
                                {{ editTarget ? $t('categories.editar') : $t('categories.nueva') }}
                            </h3>
                            <button @click="closeModal" class="text-gray-400 hover:text-white transition text-xl">✕</button>
                        </div>
                        <!-- Body -->
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">
                                    {{ $t('categories.nombre') }} *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="$t('categories.nombrePlaceholder')"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10213E]"
                                    @keyup.enter="submit"
                                />
                                <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $t('categories.slugAuto') }}</p>
                            </div>
                        </div>
                        <!-- Footer -->
                        <div class="flex gap-3 px-6 pb-6">
                            <button @click="closeModal"
                                class="flex-1 border border-gray-200 text-gray-500 font-bold py-2.5 rounded-xl hover:bg-gray-50 transition text-sm">
                                {{ $t('acciones.cancelar') }}
                            </button>
                            <button @click="submit" :disabled="form.processing"
                                class="flex-1 bg-[#10213E] hover:bg-blue-900 disabled:opacity-50 text-white font-black py-2.5 rounded-xl transition text-sm">
                                {{ form.processing ? '...' : $t('acciones.guardar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Confirmar eliminar -->
        <ConfirmModal
            :show="confirmDel.show"
            :title="$t('categories.eliminarTitulo')"
            :message="$t('categories.eliminarMsg')"
            :confirm-text="$t('acciones.eliminar')"
            @confirm="doDel"
            @cancel="confirmDel.show = false"
        />
    </AuthenticatedLayout>
</template>
