<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

const page = usePage();
const showingNavigationDropdown = ref(false);

const primaryColor = computed(() => page.props.settings?.primary_color ?? '#10213E');
const secondaryColor = computed(() => page.props.settings?.secondary_color ?? '#EE2857');
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

        <!-- Flash error banner -->
        <div v-if="$page.props.flash?.error"
            class="bg-red-50 border-b border-red-200 px-4 py-3 text-center text-sm font-medium text-red-700">
            ⚠️ {{ $page.props.flash.error }}
        </div>

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
