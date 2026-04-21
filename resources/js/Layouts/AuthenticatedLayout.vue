<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

const page = usePage();
const showingNavigationDropdown = ref(false);

const primaryColor = computed(() => page.props.settings?.primary_color ?? '#10213E');
const secondaryColor = computed(() => page.props.settings?.secondary_color ?? '#EE2857');

// ── Toast global ──────────────────────────────────────────────────────
const toast = ref(null);
let toastTimer = null;

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success || flash?.error) {
            clearTimeout(toastTimer);
            toast.value = {
                type: flash.success ? 'success' : 'error',
                message: flash.success || flash.error,
            };
            toastTimer = setTimeout(() => { toast.value = null; }, 4000);
        }
    },
    { deep: true, immediate: true }
);
</script>

<template>
    <div class="min-h-screen bg-gray-100">

        <component :is="'style'">
            :root {
            --primary-color: {{ primaryColor }};
            --secondary-color: {{ secondaryColor }};
            }
            .bg-jk-blue { background-color: {{ primaryColor }} !important; color: white !important; }
            .text-jk-blue { color: {{ primaryColor }} !important; }
            .bg-jk-red { background-color: {{ secondaryColor }} !important; color: white !important; }
            .text-jk-red { color: {{ secondaryColor }} !important; }
        </component>

        <nav class="border-b border-gray-100 bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">

                    <!-- Logo + Links escritorio -->
                    <div class="flex">
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('dashboard')">
                                <img src="/images/logo-taller.svg" class="h-10 w-auto" alt="Logo" />
                            </Link>
                        </div>
                        <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex text-xs">
                            <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                DASHBOARD
                            </NavLink>
                            <NavLink :href="route('repair-orders.index')" :active="route().current('repair-orders.*')">
                                TALLER
                            </NavLink>
                            <NavLink :href="route('recepcion.index')" :active="route().current('recepcion.*')">
                                RECEPCIONES
                            </NavLink>
                            <NavLink
                                v-if="$page.props.auth.role === 'admin'"
                                :href="route('mechanics.index')"
                                :active="route().current('mechanics.*')"
                            >
                                PERSONAL
                            </NavLink>
                            <NavLink
                                v-if="$page.props.auth.role === 'admin'"
                                :href="route('parts.index')"
                                :active="route().current('parts.*')"
                            >
                                INVENTARIO
                            </NavLink>
                        </div>
                    </div>

                    <!-- Dropdown usuario escritorio -->
                    <div class="hidden sm:ms-6 sm:flex sm:items-center">
                        <div class="relative ms-3">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button type="button"
                                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-bold text-gray-500 hover:text-gray-700 focus:outline-none transition">
                                            {{ $page.props.auth.user.name }}
                                            <svg class="ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </span>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">Mi Perfil</DropdownLink>
                                    <DropdownLink :href="route('settings.edit')" class="font-bold text-jk-blue">
                                        ⚙️ Configuración
                                    </DropdownLink>
                                    <div class="border-t border-gray-100" />
                                    <DropdownLink :href="route('logout')" method="post" as="button">
                                        Cerrar Sesión
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Hamburguesa móvil -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none transition">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Menú móvil desplegable -->
            <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                <div class="space-y-1 pb-3 pt-2">
                    <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                        Dashboard
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('repair-orders.index')" :active="route().current('repair-orders.*')">
                        Taller
                    </ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('recepcion.index')" :active="route().current('recepcion.*')">
                        Recepciones
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        v-if="$page.props.auth.role === 'admin'"
                        :href="route('mechanics.index')"
                        :active="route().current('mechanics.*')"
                    >
                        Personal
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        v-if="$page.props.auth.role === 'admin'"
                        :href="route('parts.index')"
                        :active="route().current('parts.*')"
                    >
                        Inventario
                    </ResponsiveNavLink>
                </div>
            </div>
        </nav>

        <!-- Flash error banner legacy (reemplazado por toast global) -->

        <!-- Toast global (success + error) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-4"
            >
                <div v-if="toast"
                    class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[200] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-bold max-w-md w-full mx-4"
                    :class="toast.type === 'success'
                        ? 'bg-[#10213E] text-white'
                        : 'bg-[#EE2857] text-white'">
                    <span class="text-lg leading-none shrink-0">
                        {{ toast.type === 'success' ? '✅' : '⚠️' }}
                    </span>
                    <span class="flex-1 text-xs leading-snug">{{ toast.message }}</span>
                    <button @click="toast = null"
                        class="text-white/60 hover:text-white transition text-base leading-none shrink-0">✕</button>
                </div>
            </Transition>
        </Teleport>

        <!-- Header de página -->
        <header v-if="$slots.header" class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main>
            <slot />
        </main>

    </div>
</template>

<style>
.bg-jk-blue,
.bg-jk-red {
    transition: all 0.3s ease;
}
</style>
