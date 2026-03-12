<script setup>
import { Head, Link } from '@inertiajs/vue3';

// Recibimos los datos que enviamos desde web.php
const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    settings: Object, // Aquí vienen Nombre, Dirección, Teléfono, etc.
});
</script>

<template>
    <Head :title="'Bienvenido a ' + settings?.company_name || 'JKAutomotive'" />

    <div class="relative min-h-screen bg-gray-100 selection:bg-jk-blue selection:text-white">
        <div class="absolute inset-0 z-0 overflow-hidden">
            <div class="absolute -top-[30%] -left-[10%] w-[70%] h-[70%] bg-blue-200/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-[20%] -right-[10%] w-[60%] h-[60%] bg-red-100/20 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 flex flex-col min-h-screen">
            <header class="flex justify-end p-6">
                <nav v-if="canLogin">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-4 py-2 font-semibold text-gray-700 hover:text-jk-blue transition"
                    >
                        Panel de Control
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 font-semibold text-gray-700 hover:text-jk-blue transition"
                        >
                            Iniciar Sesión
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="ml-4 px-6 py-2 bg-jk-blue text-white rounded-lg shadow-md hover:bg-blue-900 transition"
                        >
                            Registrarse
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="flex-grow flex flex-col items-center justify-center px-6 text-center">
            <div class="mb-8">
    <img 
        src="/images/logo-taller.svg" 
        alt="Logo JK Automotive" 
        class="h-32 md:h-48 w-auto mx-auto drop-shadow-lg object-contain"
    />
</div>

                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-4">
                   {{settings?.company_name ?? 'JKAutomotive' }}
                </h2>
                
                <p class="max-w-2xl text-lg text-gray-600 mb-8">
                    Sistema Profesional de Recepción y Control de Vehículos. 
                    Mantenemos tu motor en marcha con tecnología y precisión.
                </p>

                <div class="flex gap-4">
                    <Link
                        :href="route('login')"
                        class="px-8 py-4 bg-jk-blue text-white font-bold rounded-xl shadow-lg hover:scale-105 transition transform"
                    >
                        COMENZAR AHORA
                    </Link>
                </div>
            </main>

            <footer class="py-10 bg-white/50 backdrop-blur-md border-t border-gray-200">
                <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-8 text-center md:text-left">
                    <div>
                        <h4 class="font-bold text-jk-blue uppercase mb-2">Ubicación</h4>
                        <p class="text-gray-600 italic">{{ settings.address }}</p>
                    </div>
                    <div>
                        <h4 class="font-bold text-jk-blue uppercase mb-2">Contacto</h4>
                        <p class="text-gray-600">Tel: <span class="font-bold">{{ settings.phone }}</span></p>
                        <p class="text-gray-600 text-xs">{{ settings.email }}</p>
                    </div>
                    <div>
                        <h4 class="font-bold text-jk-blue uppercase mb-2">Licencia</h4>
                        <p class="text-gray-600">#{{ settings.license_number }}</p>
                    </div>
                </div>
                <div class="mt-8 text-center text-xs text-gray-400">
                    &copy; 2026 {{ settings.company_name }} - Todos los derechos reservados.
                </div>
            </footer>
        </div>
    </div>
</template>

<style>
/* Colores corporativos de JK Automotive */
.text-jk-blue { color: #003366; }
.bg-jk-blue { background-color: #003366; }
.text-jk-red { color: #cc0000; }
.border-jk-blue { border-color: #003366; }
.border-jk-red { border-color: #cc0000; }
</style>