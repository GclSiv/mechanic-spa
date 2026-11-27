<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    ordenId: Number,
});

const abierto = ref(false);
const enviando = ref(false);
const error = ref('');
const form = reactive({ titulo: '', fecha: '', kilometraje: '', nota: '' });
const fechaMinima = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

function guardar() {
    error.value = '';
    enviando.value = true;
    router.post(route('repair-orders.recordatorios.store', props.ordenId), {
        title: form.titulo,
        recommended_date: form.fecha || null,
        recommended_mileage: form.kilometraje || null,
        notes: form.nota || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            form.titulo = ''; form.fecha = ''; form.kilometraje = ''; form.nota = '';
            abierto.value = false;
        },
        onError: (errores) => { error.value = Object.values(errores)[0] ?? 'No se pudo guardar el próximo servicio.'; },
        onFinish: () => { enviando.value = false; },
    });
}
</script>

<template>
    <section class="rounded-xl border border-line bg-surface p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-ds-label font-semibold uppercase tracking-wider text-ink-muted">Próximo servicio</h2>
            <button type="button" @click="abierto = !abierto" class="min-h-[44px] rounded-lg border border-line px-4 text-ink">
                Programar próximo servicio
            </button>
        </div>

        <form v-if="abierto" @submit.prevent="guardar" class="mt-4 grid gap-3 md:grid-cols-2">
            <label class="text-sm md:col-span-2">Título
                <input v-model="form.titulo" type="text" required maxlength="120" placeholder="Cambio de aceite" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
            </label>
            <label class="text-sm">Fecha recomendada
                <input v-model="form.fecha" type="date" :min="fechaMinima" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
            </label>
            <label class="text-sm">Kilometraje recomendado
                <input v-model="form.kilometraje" type="number" min="0" max="2000000" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
            </label>
            <label class="text-sm md:col-span-2">Nota para el cliente (opcional)
                <input v-model="form.nota" type="text" maxlength="500" class="mt-1 w-full min-h-[44px] rounded-lg border-line">
            </label>
            <p class="text-xs text-ink-muted md:col-span-2">Captura fecha, kilometraje o ambos. Si pones ambos, se recomienda lo que ocurra primero.</p>
            <p v-if="error" class="text-sm text-red-700 md:col-span-2">{{ error }}</p>
            <div class="md:col-span-2">
                <button type="submit" :disabled="enviando" class="min-h-[44px] rounded-lg bg-navy px-4 font-semibold text-white disabled:opacity-50">Guardar próximo servicio</button>
            </div>
        </form>
    </section>
</template>
