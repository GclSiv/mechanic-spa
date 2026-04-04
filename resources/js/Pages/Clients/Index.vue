<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Recibimos la lista de clientes desde el ClientController
defineProps({
    clients: {
        type: Array,
        default: () => []
    },
});
</script>

<template>
    <Head title="Clientes - JK Automotive" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-bold text-xl text-gray-800 leading-tight uppercase tracking-wider">
                    Directorio de Clientes
                </h2>
                <!-- Botón para crear un nuevo cliente directamente (opcional) -->
                <Link :href="route('clientes.create')" 
                      class="bg-jk-blue hover:bg-blue-900 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md">
                    + NUEVO CLIENTE
                </Link>
            </div>
        </template>
        
        <div class="py-12 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Contenedor de la Tabla -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl border-t-4 border-jk-blue">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-500 uppercase tracking-wider">Nombre del Cliente</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-500 uppercase tracking-wider">Teléfono</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-500 uppercase tracking-wider">RFC</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-black text-gray-500 uppercase tracking-wider">Dirección</th>
                                    <th scope="col" class="px-6 py-4 text-center text-xs font-black text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <!-- Iteramos sobre los clientes -->
                                <tr v-for="client in clients" :key="client.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 font-bold">
                                                {{ client.first_name.charAt(0) }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900">
                                                    {{ client.first_name }} {{ client.last_name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600">{{ client.phone || 'Sin registrar' }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 uppercase">
                                            {{ client.rfc || 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs">
                                        {{ client.address || 'Sin dirección' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <!-- Enlaces para editar o ver historial (ajusta las rutas según las tengas) -->
                                        <Link :href="route('clientes.edit', client.id)" class="text-jk-blue hover:text-blue-900 font-bold">
                                            Editar
                                        </Link>
                                    </td>
                                </tr>

                                <!-- Mensaje si la base de datos está vacía -->
                                <tr v-if="clients.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 font-medium">
                                        No hay clientes registrados en el sistema. 
                                        <br>
                                        <span class="text-sm text-gray-400">Los clientes se crearán automáticamente al registrar una nueva recepción.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>