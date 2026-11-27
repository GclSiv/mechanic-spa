<script setup>
import AppShellLayout from "@/Layouts/AppShellLayout.vue";
import { Head, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const props = defineProps({
    solicitudes: Array,
    recordatorios: Array,
    motivos: Array,
});

const etiquetasEstado = { pendiente: 'Pendiente', confirmada: 'Confirmada', rechazada: 'Rechazada', cancelada: 'Cancelada', completada: 'Completada' };
const etiquetasMotivo = { mantenimiento: 'Mantenimiento', revision: 'Revisión', garantia: 'Garantía / revisión posterior', diagnostico: 'Diagnóstico', otro: 'Otro' };
const etiquetasHorario = { manana: 'Mañana', tarde: 'Tarde' };

const formularios = reactive({});
function form(c) {
    if (!formularios[c.id]) {
        formularios[c.id] = { confirmed_date: c.fecha ?? '', confirmed_time: '', customer_note: '', admin_notes: '', error: '' };
    }
    return formularios[c.id];
}

function accion(c, nombre) {
    const f = form(c);
    f.error = '';
    const datos = { accion: nombre, admin_notes: f.admin_notes || null };
    if (nombre === 'confirmar') {
        datos.confirmed_date = f.confirmed_date;
        datos.confirmed_time = f.confirmed_time;
        datos.customer_note = f.customer_note || null;
    }
    router.patch(route('citas.update', c.id), datos, {
        preserveScroll: true,
        onError: (errores) => { f.error = Object.values(errores)[0] ?? 'No se pudo actualizar la solicitud.'; },
    });
}

const acciones = (estado) => ({
    pendiente: ['confirmar', 'rechazar', 'cancelar'],
    confirmada: ['completar', 'cancelar'],
}[estado] ?? []);

const etiquetaAccion = { confirmar: 'Confirmar', rechazar: 'Rechazar', cancelar: 'Cancelar', completar: 'Marcar completada' };
const hayRecordatorios = computed(() => props.recordatorios.length > 0);
</script>

<template>
    <Head title="Solicitudes de cita" />

    <AppShellLayout>
        <div class="mx-auto max-w-6xl space-y-6 p-4 md:p-6">
            <header>
                <h1 class="text-xl font-bold text-ink">Solicitudes de cita</h1>
                <p class="text-sm text-ink-muted">Las solicitudes del portal no se reservan solas: confirma fecha y hora aquí.</p>
            </header>

            <section class="space-y-3">
                <p v-if="solicitudes.length === 0" class="rounded-xl border border-line bg-surface p-4 text-sm text-ink-muted">No hay solicitudes de cita.</p>

                <article v-for="c in solicitudes" :key="c.id" class="rounded-xl border border-line bg-surface p-4 space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-ink">{{ c.cliente ?? 'Cliente sin nombre' }}</p>
                            <p class="text-sm text-ink-muted">{{ c.vehiculo }}<span v-if="c.folio"> · Orden {{ c.folio }}</span></p>
                        </div>
                        <span class="rounded-full border border-line px-3 py-1 text-xs font-semibold text-ink">{{ etiquetasEstado[c.estado] ?? c.estado }}</span>
                    </div>

                    <dl class="grid gap-2 text-sm md:grid-cols-4">
                        <div><dt class="text-ink-muted">Motivo</dt><dd class="text-ink">{{ etiquetasMotivo[c.motivo] ?? c.motivo }}</dd></div>
                        <div><dt class="text-ink-muted">Fecha solicitada</dt><dd class="text-ink">{{ c.fecha }}</dd></div>
                        <div><dt class="text-ink-muted">Horario</dt><dd class="text-ink">{{ etiquetasHorario[c.horario] ?? 'Sin preferencia' }}</dd></div>
                        <div><dt class="text-ink-muted">Teléfono</dt><dd class="text-ink">{{ c.telefono ?? '—' }}</dd></div>
                    </dl>

                    <p v-if="c.comentario" class="text-sm text-ink"><span class="text-ink-muted">Comentario del cliente:</span> {{ c.comentario }}</p>
                    <p v-if="c.estado === 'confirmada'" class="text-sm text-ink">Confirmada para {{ c.confirmed_date }} {{ c.confirmed_time }}<span v-if="c.customer_note"> · {{ c.customer_note }}</span></p>

                    <div v-if="acciones(c.estado).length" class="space-y-3 border-t border-line pt-3">
                        <div v-if="c.estado === 'pendiente'" class="grid gap-3 md:grid-cols-4">
                            <label class="text-sm">Fecha definitiva
                                <input v-model="form(c).confirmed_date" type="date" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
                            </label>
                            <label class="text-sm">Hora definitiva
                                <input v-model="form(c).confirmed_time" type="time" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
                            </label>
                            <label class="text-sm md:col-span-2">Nota para el cliente
                                <input v-model="form(c).customer_note" type="text" maxlength="500" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
                            </label>
                        </div>
                        <label class="block text-sm">Nota interna (no visible al cliente)
                            <input v-model="form(c).admin_notes" type="text" maxlength="1000" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
                        </label>
                        <p v-if="form(c).error" class="text-sm text-red-700">{{ form(c).error }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="a in acciones(c.estado)" :key="a" type="button" @click="accion(c, a)"
                                class="min-h-[44px] rounded-lg px-4 font-semibold"
                                :class="a === 'confirmar' || a === 'completar' ? 'bg-navy text-white' : 'border border-line text-ink'">
                                {{ etiquetaAccion[a] }}
                            </button>
                        </div>
                    </div>
                </article>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-muted">Próximos servicios pendientes</h2>
                <p v-if="!hayRecordatorios" class="rounded-xl border border-line bg-surface p-4 text-sm text-ink-muted">No hay próximos servicios programados.</p>
                <ul v-else class="divide-y divide-line rounded-xl border border-line bg-surface">
                    <li v-for="r in recordatorios" :key="r.id" class="flex flex-wrap justify-between gap-2 p-4 text-sm">
                        <span class="font-semibold text-ink">{{ r.titulo }}</span>
                        <span class="text-ink-muted">{{ r.cliente }} · {{ r.vehiculo }}</span>
                        <span class="text-ink">
                            <template v-if="r.fecha">{{ r.fecha }}</template>
                            <template v-if="r.fecha && r.kilometraje"> o </template>
                            <template v-if="r.kilometraje">{{ Number(r.kilometraje).toLocaleString('es-MX') }} km</template>
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </AppShellLayout>
</template>
