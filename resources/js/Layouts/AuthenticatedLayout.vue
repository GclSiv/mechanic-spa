<script setup>
import { computed, ref } from 'vue';
// 🛑 AGREGA 'usePage' y 'Link' a esta importación:
import { Link, usePage } from '@inertiajs/vue3';

// Dentro de <script setup> en AuthenticatedLayout.vue
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';


import Dropdown from '@/Components/Dropdown.vue';
// ... el resto de tus importaciones

const page = usePage(); // <--- Ahora sí, esta línea funcionará perfectamente
const showingNavigationDropdown = ref(false);

// 🛰️ Tus colores dinámicos de JK Automotive
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
                        </div>
                    </div>

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
                                    <DropdownLink :href="route('profile.edit')"> Mi Perfil </DropdownLink>
                                    <DropdownLink :href="route('settings.edit')" class="font-bold text-jk-blue"> ⚙️
                                        Configuración </DropdownLink>
                                    <div class="border-t border-gray-100" />
                                    <DropdownLink :href="route('logout')" method="post" as="button"> Cerrar Sesión
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-white shadow-sm" v-if="$slots.header">
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
/* Estilos base fijos */
.bg-jk-blue,
.bg-jk-red {
    transition: all 0.3s ease;
}
</style>