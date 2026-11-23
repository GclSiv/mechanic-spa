<script setup>
// Detalle de una recepción (solo admin/owner). Solo lectura: la edición vive en Edit (legacy).
// Si la recepción ya generó orden, el backend redirige al Expediente; aquí siempre es "sin orden".
import AppShellLayout from '@/Layouts/AppShellLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ recepcion: Object });

const page = usePage();
const { t } = useI18n();

// Marca y modelo: relación catálogo si existe; si no, el texto guardado en el vehículo.
const marcaVehiculo = computed(() => props.recepcion.vehicle?.brand?.name
    ?? (typeof props.recepcion.vehicle?.brand === 'string' ? props.recepcion.vehicle.brand : null));
const modeloVehiculo = computed(() => props.recepcion.vehicle?.vehicle_model?.name ?? props.recepcion.vehicle?.model ?? null);

const idFormateado = computed(() => String(props.recepcion.id).padStart(4, '0'));

const fechaIngreso = computed(() => (props.recepcion.created_at
    ? new Date(props.recepcion.created_at).toLocaleDateString('es-MX', { day: '2-digit', month: 'long', year: 'numeric' })
    : '—'));

function getLang() {
    try { return localStorage.getItem('jk_locale') ?? 'es'; } catch { return 'es'; }
}

// Teléfono en formato WhatsApp de México: 52 + 10 dígitos
function normalizarTelefono(tel) {
    const d = (tel ?? '').replace(/\D/g, '');
    if (!d) return null;
    if (d.length === 10) return '52' + d;
    if (d.length === 13 && d.startsWith('521')) return '52' + d.slice(3);
    return d;
}

const whatsappUrl = computed(() => {
    const telefono = normalizarTelefono(props.recepcion.client?.phone);
    if (!telefono) return null;
    const taller = page.props.settings?.company_name || t('recepcionUi.tallerGenerico');
    const cliente = props.recepcion.client;
    const vehiculo = props.recepcion.vehicle;
    const marca = marcaVehiculo.value ?? '';
    const modelo = modeloVehiculo.value ?? '';
    const texto =
        `Hola ${cliente?.first_name ?? ''}, le escribimos de ${taller}. ` +
        `Su vehículo ${marca} ${modelo} ${vehiculo?.year ?? ''} quedó registrado en recepción #${idFormateado.value}` +
        (cliente?.client_number ? ` (cliente ${cliente.client_number})` : '') +
        '. Cualquier duda, con gusto le atendemos.';
    return `https://wa.me/${telefono}?text=${encodeURIComponent(texto)}`;
});

const cliente = computed(() => [props.recepcion.client?.first_name, props.recepcion.client?.last_name].filter(Boolean).join(' ') || '—');
</script>

<template>
    <Head :title="t('recepcionUi.detalleTitulo')" />

    <AppShellLayout>
        <Link :href="route('recepcion.index')" class="ds-focus mb-3 inline-flex rounded-md text-ds-caption font-semibold text-navy hover:underline">
            ← {{ t('recepcionUi.volver') }}
        </Link>

        <PageHeader
            :title="`${t('recepcionUi.detalleTitulo')} #${idFormateado}`"
            :description="t('recepcionUi.fechaIngreso', { fecha: fechaIngreso })"
        >
            <Link
                :href="route('recepcion.edit', recepcion.id)"
                class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
            >{{ t('recepcionUi.editar') }}</Link>
            <a
                :href="route('recepcion.pdf', recepcion.id) + '?lang=' + getLang()"
                target="_blank"
                rel="noopener"
                class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
            >{{ t('recepcionUi.imprimir') }}</a>
            <a
                v-if="whatsappUrl"
                :href="whatsappUrl"
                target="_blank"
                rel="noopener"
                class="ds-focus inline-flex h-control items-center rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
            >{{ t('recepcionUi.whatsapp') }}</a>
            <a
                v-if="recepcion.vehicle"
                :href="route('vehicles.history', recepcion.vehicle.id)"
                class="ds-focus inline-flex h-control items-center whitespace-nowrap rounded-lg border border-line bg-surface px-4 text-ds-control font-semibold text-ink-secondary hover:bg-surface-2"
            >{{ t('recepcionUi.historialVehiculo') }}</a>
            <Link
                :href="route('recepcion.generate-order', recepcion.id)"
                method="post"
                as="button"
                class="ds-focus inline-flex h-control items-center rounded-lg bg-navy px-4 text-ds-control font-semibold text-white hover:bg-navy/90"
            >{{ t('recepcionUi.generarOrden') }}</Link>
        </PageHeader>

        <div class="mt-6 grid min-w-0 gap-5 xl:grid-cols-2">
            <section class="min-w-0 rounded-xl border border-line bg-surface p-5" aria-labelledby="sec-estado">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="sec-estado" class="text-ds-section-title text-navy">{{ t('recepcionUi.seccionEstado') }}</h2>
                    <BaseBadge variant="neutral">{{ t('recepcionUi.sinOrden') }}</BaseBadge>
                </div>
                <p class="mt-2 text-ds-caption text-ink-muted">{{ t('recepcionUi.estadoAyuda') }}</p>
            </section>

            <section class="min-w-0 rounded-xl border border-line bg-surface p-5" aria-labelledby="sec-cliente">
                <h2 id="sec-cliente" class="mb-4 text-ds-section-title text-navy">{{ t('recepcionUi.seccionCliente') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.nombre') }}</dt>
                        <dd class="text-ds-body text-ink">{{ cliente }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.telefono') }}</dt>
                        <dd class="text-ds-body text-ink">{{ recepcion.client?.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcionUi.rfc') }}</dt>
                        <dd class="font-mono text-ds-body text-ink">{{ recepcion.client?.rfc ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.direccion') }}</dt>
                        <dd class="text-ds-body text-ink">{{ recepcion.client?.address ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="min-w-0 rounded-xl border border-line bg-surface p-5" aria-labelledby="sec-vehiculo">
                <h2 id="sec-vehiculo" class="mb-4 text-ds-section-title text-navy">{{ t('recepcionUi.seccionVehiculo') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.marca') }}</dt>
                        <dd class="text-ds-body text-ink">{{ marcaVehiculo ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.modelo') }}</dt>
                        <dd class="text-ds-body text-ink">{{ modeloVehiculo ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.anio') }}</dt>
                        <dd class="text-ds-body text-ink">{{ recepcion.vehicle?.year ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.placas') }}</dt>
                        <dd class="font-mono text-ds-body uppercase text-ink">{{ recepcion.vehicle?.plate ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">VIN</dt>
                        <dd class="break-all font-mono text-ds-body text-ink">{{ recepcion.vehicle?.vin ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.motor') }}</dt>
                        <dd class="text-ds-body text-ink">{{ recepcion.vehicle?.engine ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="min-w-0 rounded-xl border border-line bg-surface p-5 xl:col-span-2" aria-labelledby="sec-ingreso">
                <h2 id="sec-ingreso" class="mb-4 text-ds-section-title text-navy">{{ t('recepcionUi.seccionIngreso') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.kilometraje') }}</dt>
                        <dd class="text-ds-body text-ink">{{ recepcion.miles ? Number(recepcion.miles).toLocaleString('en-US') + ' mi' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('recepcion_form.gasolina') }}</dt>
                        <dd class="text-ds-body text-ink">{{ recepcion.fuel_level ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ds-label text-ink-muted">{{ t('inspeccion.fechaIngreso') }}</dt>
                        <dd class="text-ds-body text-ink">{{ fechaIngreso }}</dd>
                    </div>
                </dl>

                <div v-if="recepcion.witnesses?.length" class="mt-5">
                    <h3 class="mb-2 text-ds-label text-ink-muted">{{ t('recepcion_form.testigos') }}</h3>
                    <ul class="flex flex-wrap gap-2">
                        <li v-for="w in recepcion.witnesses" :key="w">
                            <BaseBadge variant="info">{{ t('inspeccion.' + w) }}</BaseBadge>
                        </li>
                    </ul>
                </div>

                <div v-if="recepcion.inventory?.length" class="mt-5">
                    <h3 class="mb-2 text-ds-label text-ink-muted">{{ t('recepcion_form.inventarioIngreso') }}</h3>
                    <ul class="flex flex-wrap gap-2">
                        <li v-for="i in recepcion.inventory" :key="i">
                            <BaseBadge variant="neutral">{{ t('recepcion_form.inv_' + i) }}</BaseBadge>
                        </li>
                    </ul>
                </div>

                <div v-if="recepcion.damages?.length" class="mt-5">
                    <h3 class="mb-2 text-ds-label text-ink-muted">{{ t('recepcion_form.danos') }}</h3>
                    <ul class="list-disc space-y-1 pl-5 text-ds-body text-ink">
                        <li v-for="(d, idx) in recepcion.damages" :key="idx">
                            {{ t('recepcion_form.zona_' + d.zone) }}<span v-if="d.severity" class="capitalize"> ({{ d.severity }})</span><span v-if="d.note"> — {{ d.note }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="min-w-0 rounded-xl border border-line bg-surface p-5 xl:col-span-2" aria-labelledby="sec-sintomas">
                <h2 id="sec-sintomas" class="text-ds-section-title text-navy">{{ t('recepcionUi.seccionSintomas') }}</h2>
                <p class="mt-1 text-ds-caption text-ink-muted">{{ t('recepcionUi.sintomasAyuda') }}</p>
                <p class="mt-3 whitespace-pre-line rounded-lg bg-surface-2 p-3 text-ds-body text-ink">
                    {{ recepcion.symptoms ?? t('inspeccion.sinDescripcion') }}
                </p>
            </section>

            <section class="min-w-0 rounded-xl border border-line bg-surface p-5 xl:col-span-2" aria-labelledby="sec-fotos">
                <h2 id="sec-fotos" class="text-ds-section-title text-navy">{{ t('recepcionUi.seccionFotos') }}</h2>
                <p v-if="!recepcion.photos?.length" class="mt-3 text-ds-body text-ink-muted">{{ t('recepcionUi.sinFotos') }}</p>
                <ul v-else class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <li v-for="(photo, i) in recepcion.photos" :key="i">
                        <a
                            :href="'/storage/' + photo"
                            target="_blank"
                            rel="noopener"
                            class="ds-focus block aspect-square overflow-hidden rounded-xl border border-line hover:opacity-80"
                        >
                            <img :src="'/storage/' + photo" class="h-full w-full object-cover" :alt="t('recepcionUi.fotoAlt', { n: i + 1 })" />
                        </a>
                    </li>
                </ul>
            </section>
        </div>
    </AppShellLayout>
</template>
