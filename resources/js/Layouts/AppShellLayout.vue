<script setup>
// Shell nuevo del Design System v1 (sidebar + contenido). Convive con AuthenticatedLayout hasta migrar las páginas.
// Los enlaces se muestran según el rol y los módulos que ya expone el backend. Nunca es la autoridad de acceso.
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();

const auth        = computed(() => page.props.auth ?? {});
const esAdmin     = computed(() => auth.value.isAdmin === true);
const esOwner     = computed(() => auth.value.isOwner === true);
const modulos     = computed(() => page.props.modules ?? {});
const nombreTaller = computed(() => page.props.settings?.company_name || 'TALLER');
const logoUrl     = computed(() => page.props.logo_url ?? null);
const inicial     = computed(() => nombreTaller.value.trim().charAt(0).toUpperCase());
const flash       = computed(() => page.props.flash ?? {});

const menuAbierto = ref(false);
watch(() => page.url, () => { menuAbierto.value = false; });

// Iconos lineales (trazo 1.8, viewBox 24). Sin emojis.
const ICONOS = {
    inicio: 'M3 11l9-7 9 7M5 10v10h14V10',
    recepcion: 'M5 4h14v17H5zM9 4h6v3H9zM12 10v6M9 13h6',
    taller: 'M14 7l3 3-8 8-3-3 8-8zM16 4a4 4 0 0 0 4 4',
    ordenes: 'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01',
    clientes: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c1-4 4-6 8-6s7 2 8 6',
    refacciones: 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8',
    herramientas: 'M4 20l8-8M14 4l6 6-3 1-4-4z',
    personal: 'M16 11a4 4 0 1 0-8 0M4 21c1-4 4-6 8-6s7 2 8 6',
    caja: 'M3 6h18v13H3zM3 10h18M16 14.5h1',
    reportes: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    configuracion: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM4 12h2M18 12h2M12 4v2M12 18v2',
    modulos: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
    trabajo: 'M14 7l3 3-8 8-3-3 8-8z',
};

// Owner/admin: estructura aprobada. "Usuarios" se omite hasta que exista ruta real.
const gruposAdmin = () => [
    { titulo: t('shell.inicio'), items: [
        { label: t('shell.inicio'), href: route('dashboard'), activo: route().current('dashboard'), icono: 'inicio' },
    ]},
    { titulo: t('shell.operacion'), items: [
        { label: t('shell.recepcion'), href: route('recepcion.index'), activo: route().current('recepcion.*'), icono: 'recepcion' },
        { label: t('shell.taller'), href: route('repair-orders.index'), activo: route().current('repair-orders.index') || route().current('repair-orders.show'), icono: 'taller' },
        { label: t('shell.ordenes'), href: route('repair-orders.lista'), activo: route().current('repair-orders.lista'), icono: 'ordenes' },
    ]},
    { titulo: t('shell.clientes'), items: [
        { label: t('shell.clientes'), href: route('clients.index'), activo: route().current('clients.*'), icono: 'clientes' },
    ]},
    { titulo: t('shell.recursos'), items: [
        { label: t('shell.refacciones'), href: route('parts.index'), activo: route().current('parts.*'), icono: 'refacciones' },
        { label: t('shell.herramientas'), href: route('tools.index'), activo: route().current('tools.*'), icono: 'herramientas' },
        { label: t('shell.personal'), href: route('mechanics.index'), activo: route().current('mechanics.*'), icono: 'personal' },
    ]},
    { titulo: t('shell.finanzas'), items: [
        { label: t('shell.caja'), href: route('finance.index'), activo: route().current('finance.*'), icono: 'caja', visible: modulos.value.caja === true },
        { label: t('shell.reportes'), href: route('reports.index'), activo: route().current('reports.*'), icono: 'reportes', visible: modulos.value.reportes === true },
    ]},
    { titulo: t('shell.administracion'), items: [
        { label: t('shell.configuracion'), href: route('settings.edit'), activo: route().current('settings.*'), icono: 'configuracion' },
        { label: t('shell.modulos'), href: route('owner.modules.index'), activo: route().current('owner.modules.*'), icono: 'modulos', visible: esOwner.value },
    ]},
];

// Mecánico: solo su trabajo y sus órdenes. El backend ya limita los datos.
const gruposMecanico = () => [
    { titulo: t('shell.inicio'), items: [
        { label: t('shell.miTrabajo'), href: route('dashboard'), activo: route().current('dashboard'), icono: 'trabajo' },
    ]},
    { titulo: t('shell.operacion'), items: [
        { label: t('shell.misOrdenes'), href: route('repair-orders.index'), activo: route().current('repair-orders.*'), icono: 'ordenes' },
    ]},
];

const grupos = computed(() => {
    const base = esAdmin.value ? gruposAdmin() : gruposMecanico();
    return base
        .map((g) => ({ ...g, items: g.items.filter((i) => i.visible !== false) }))
        .filter((g) => g.items.length > 0);
});
</script>

<template>
    <div class="min-h-screen bg-page text-ink">
        <!-- Overlay móvil -->
        <div v-if="menuAbierto" class="fixed inset-0 z-40 bg-black/40 lg:hidden" aria-hidden="true" @click="menuAbierto = false"></div>

        <!-- Sidebar: fijo desde 1024 px; panel desplegable por debajo -->
        <aside
            :class="menuAbierto ? 'flex' : 'hidden'"
            class="fixed inset-y-0 left-0 z-50 w-sidebar flex-col bg-navy text-white lg:z-30 lg:flex"
            aria-label="Navegación principal"
        >
            <div class="flex items-center gap-3 px-5 pb-4 pt-6">
                <!-- El logo del taller es de tinta oscura: se presenta sobre una superficie clara para leerse en el sidebar navy. La imagen no se altera. -->
                <div v-if="logoUrl" class="w-full rounded-xl bg-white px-3 py-3">
                    <img :src="logoUrl" :alt="nombreTaller" class="mx-auto block h-auto max-h-24 w-full object-contain" />
                </div>
                <template v-else>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal text-ds-label font-bold text-navy" aria-hidden="true">{{ inicial }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-ds-body font-bold tracking-wide">{{ nombreTaller }}</p>
                        <p class="text-ds-caption text-white/60">{{ t('shell.centroOperativo') }}</p>
                    </div>
                </template>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Módulos">
                <div v-for="grupo in grupos" :key="grupo.titulo">
                    <p class="px-3 pb-1 pt-4 text-ds-sidebar-group uppercase tracking-wider text-white/60">{{ grupo.titulo }}</p>
                    <ul class="space-y-0.5">
                        <li v-for="item in grupo.items" :key="item.label">
                            <Link
                                :href="item.href"
                                :aria-current="item.activo ? 'page' : undefined"
                                class="ds-focus relative flex h-control-compact items-center gap-2.5 rounded-lg px-3 text-ds-control transition-colors"
                                :class="item.activo ? 'bg-white/10 font-semibold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white'"
                            >
                                <span v-if="item.activo" class="absolute inset-y-1 left-0 w-[3px] rounded-full bg-teal" aria-hidden="true"></span>
                                <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path :d="ICONOS[item.icono]" />
                                </svg>
                                <span class="truncate">{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-white/10 px-3 py-3">
                <p class="truncate px-3 text-ds-label text-white/80">{{ auth.user?.name }}</p>
                <div class="mt-1 space-y-0.5">
                    <Link :href="route('profile.edit')" class="ds-focus block whitespace-nowrap rounded-lg px-3 py-2 text-ds-control text-white/75 hover:bg-white/10 hover:text-white">{{ t('shell.miPerfil') }}</Link>
                    <Link :href="route('logout')" method="post" as="button" class="ds-focus block w-full whitespace-nowrap rounded-lg px-3 py-2 text-left text-ds-control text-white/75 hover:bg-white/10 hover:text-white">{{ t('shell.cerrarSesion') }}</Link>
                </div>
            </div>
        </aside>

        <!-- Contenido -->
        <div class="lg:ml-sidebar">
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between bg-navy px-4 text-white lg:hidden">
                <span class="truncate text-ds-body font-bold">{{ nombreTaller }}</span>
                <button
                    type="button"
                    class="ds-focus h-control-compact rounded-lg px-3 text-ds-control hover:bg-white/10"
                    :aria-expanded="menuAbierto ? 'true' : 'false'"
                    aria-controls="sidebar-principal"
                    @click="menuAbierto = !menuAbierto"
                >{{ t('shell.menu') }}</button>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div v-if="flash.success" role="status" class="mb-6 rounded-lg border border-success bg-success-soft px-4 py-3 text-ds-body text-success-text">{{ flash.success }}</div>
                <div v-if="flash.error" role="alert" class="mb-6 rounded-lg border border-danger bg-danger-soft px-4 py-3 text-ds-body text-danger-text">{{ flash.error }}</div>
                <slot />
            </main>
        </div>
    </div>
</template>
