<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const props = defineProps({ settings: Object });

const form = useForm({
    company_name:         props.settings.company_name ?? '',
    logo:                 null,
    primary_color:        props.settings.primary_color,
    secondary_color:      props.settings.secondary_color,
    license_number:       props.settings.license_number ?? '',
    phone:                props.settings.phone ?? '',
    whatsapp:             props.settings.whatsapp ?? '',
    email:                props.settings.email ?? '',
    website:              props.settings.website ?? '',
    address:              props.settings.address ?? '',
    razon_social:         props.settings.razon_social ?? '',
    rfc:                  props.settings.rfc ?? '',
    regimen_fiscal:       props.settings.regimen_fiscal ?? '',
    codigo_postal_fiscal: props.settings.codigo_postal_fiscal ?? '',
    domicilio_fiscal:     props.settings.domicilio_fiscal ?? '',
    clauses:              props.settings.clauses ?? '',
    warranty_service_enabled: !!props.settings.warranty_service_enabled,
    warranty_service_days:    props.settings.warranty_service_days ?? 30,
    warranty_service_km:      props.settings.warranty_service_km ?? null,
    warranty_parts_enabled:   !!props.settings.warranty_parts_enabled,
    warranty_parts_days:      props.settings.warranty_parts_days ?? 90,
    warranty_notes:           props.settings.warranty_notes ?? '',
});

// Vista previa: solo presentación. El logo guardado se sirve por la misma ruta que usa el encabezado.
const previsualizacion = ref(null);
const logoMostrado = computed(() => previsualizacion.value ?? page.props.logo_url);

const alElegirLogo = (evento) => {
    if (previsualizacion.value) URL.revokeObjectURL(previsualizacion.value);
    const archivo = evento.target.files[0] ?? null;
    form.logo = archivo;
    previsualizacion.value = archivo ? URL.createObjectURL(archivo) : null;
};

onBeforeUnmount(() => {
    if (previsualizacion.value) URL.revokeObjectURL(previsualizacion.value);
});

const hayErrores = computed(() => Object.keys(form.errors).length > 0);

const secciones = [
    { id: 'identidad',  etiqueta: 'Identidad' },
    { id: 'contacto',   etiqueta: 'Contacto' },
    { id: 'domicilio',  etiqueta: 'Domicilio comercial' },
    { id: 'fiscal',     etiqueta: 'Datos fiscales' },
    { id: 'documentos', etiqueta: 'Cláusulas' },
    { id: 'garantia',   etiqueta: 'Garantía' },
];

const campo = 'mt-1 w-full rounded-xl border bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-jk-blue focus:ring-jk-blue';
const claseCampo = (clave) => [campo, form.errors[clave] ? 'border-jk-red' : 'border-gray-300'];

const submit = () => {
    form.post(route('settings.update', { _method: 'put' }));
};
</script>

<template>
    <AuthenticatedLayout :title="t('settings.titulo')">
        <div class="min-h-screen bg-gray-50 py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

                <!-- Encabezado -->
                <header class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Configuración / Empresa</p>
                    <h1 class="text-2xl font-bold text-jk-blue">{{ t('settings.titulo') }}</h1>
                    <p class="mt-1 text-sm text-gray-600">Datos del taller que aparecen en documentos y comunicaciones.</p>
                </header>

                <div v-if="hayErrores" role="alert"
                     class="mb-6 rounded-xl border border-jk-red/40 bg-red-50 px-4 py-3 text-sm font-medium text-jk-red">
                    Revisa los campos marcados antes de guardar.
                </div>

                <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-[200px_1fr]">

                    <!-- Navegación por secciones (escritorio) -->
                    <nav class="sticky top-6 hidden self-start space-y-1 lg:block" aria-label="Secciones de empresa">
                        <a v-for="s in secciones" :key="s.id" :href="'#' + s.id"
                           class="block rounded-lg px-3 py-2 text-sm font-semibold text-jk-blue hover:bg-white hover:shadow-sm">
                            {{ s.etiqueta }}
                        </a>
                    </nav>

                    <div class="space-y-6">

                        <!-- Identidad -->
                        <section id="identidad" class="scroll-mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-bold text-jk-blue">Identidad</h2>
                            <p class="mt-1 text-sm text-gray-500">Nombre, logotipo y colores que identifican al taller.</p>

                            <div class="mt-6 grid gap-6 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label for="company_name" class="block text-sm font-semibold text-gray-700">Nombre comercial</label>
                                    <input id="company_name" v-model="form.company_name" type="text" maxlength="255" :class="claseCampo('company_name')">
                                    <p class="mt-1 text-xs text-gray-500">Nombre que aparecerá en documentos y comunicaciones del taller.</p>
                                    <p v-if="form.errors.company_name" class="mt-1 text-xs text-jk-red">{{ form.errors.company_name }}</p>
                                </div>

                                <div>
                                    <p class="text-sm font-semibold text-gray-700">{{ t('settings.logotipo') }}</p>
                                    <div class="mt-1 flex h-32 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3">
                                        <img v-if="logoMostrado" :src="logoMostrado" alt="Logotipo del taller" class="max-h-24 object-contain">
                                        <span v-else class="text-xs text-gray-400">{{ t('settings.sinLogo') }}</span>
                                    </div>
                                    <input id="logo" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" @change="alElegirLogo"
                                           class="mt-3 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-jk-blue file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-900">
                                    <p class="mt-1 text-xs text-gray-500">PNG, JPG, SVG o WEBP de hasta 2 MB.</p>
                                    <p v-if="form.errors.logo" class="mt-1 text-xs text-jk-red">{{ form.errors.logo }}</p>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700">Colores</p>
                                        <div class="mt-1 grid grid-cols-2 gap-3">
                                            <div class="rounded-xl border border-gray-200 p-3">
                                                <label for="primary_color" class="block text-xs font-semibold text-gray-500">{{ t('settings.botones') }}</label>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <input id="primary_color" v-model="form.primary_color" type="color" class="h-8 w-8 cursor-pointer rounded border-none bg-transparent">
                                                    <input v-model="form.primary_color" type="text" maxlength="7" class="w-full border-none bg-transparent p-0 font-mono text-xs focus:ring-0">
                                                </div>
                                            </div>
                                            <div class="rounded-xl border border-gray-200 p-3">
                                                <label for="secondary_color" class="block text-xs font-semibold text-gray-500">{{ t('settings.acentos') }}</label>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <input id="secondary_color" v-model="form.secondary_color" type="color" class="h-8 w-8 cursor-pointer rounded border-none bg-transparent">
                                                    <input v-model="form.secondary_color" type="text" maxlength="7" class="w-full border-none bg-transparent p-0 font-mono text-xs focus:ring-0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="license_number" class="block text-sm font-semibold text-gray-700">{{ t('settings.licencia') }}</label>
                                        <input id="license_number" v-model="form.license_number" type="text" :class="claseCampo('license_number')">
                                        <p v-if="form.errors.license_number" class="mt-1 text-xs text-jk-red">{{ form.errors.license_number }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Contacto -->
                        <section id="contacto" class="scroll-mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-bold text-jk-blue">Contacto</h2>
                            <p class="mt-1 text-sm text-gray-500">Medios para que tus clientes te encuentren.</p>

                            <div class="mt-6 grid gap-6 md:grid-cols-2">
                                <div>
                                    <label for="phone" class="block text-sm font-semibold text-gray-700">{{ t('settings.telefono') }}</label>
                                    <input id="phone" v-model="form.phone" type="tel" :class="claseCampo('phone')">
                                    <p v-if="form.errors.phone" class="mt-1 text-xs text-jk-red">{{ form.errors.phone }}</p>
                                </div>
                                <div>
                                    <label for="whatsapp" class="block text-sm font-semibold text-gray-700">WhatsApp</label>
                                    <input id="whatsapp" v-model="form.whatsapp" type="tel" :class="claseCampo('whatsapp')">
                                    <p v-if="form.errors.whatsapp" class="mt-1 text-xs text-jk-red">{{ form.errors.whatsapp }}</p>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-semibold text-gray-700">Correo electrónico</label>
                                    <input id="email" v-model="form.email" type="email" :class="claseCampo('email')">
                                    <p v-if="form.errors.email" class="mt-1 text-xs text-jk-red">{{ form.errors.email }}</p>
                                </div>
                                <div>
                                    <label for="website" class="block text-sm font-semibold text-gray-700">Sitio web</label>
                                    <input id="website" v-model="form.website" type="url" placeholder="https://" :class="claseCampo('website')">
                                    <p v-if="form.errors.website" class="mt-1 text-xs text-jk-red">{{ form.errors.website }}</p>
                                </div>
                            </div>
                        </section>

                        <!-- Domicilio comercial -->
                        <section id="domicilio" class="scroll-mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-bold text-jk-blue">Domicilio comercial</h2>
                            <p class="mt-1 text-sm text-gray-500">Dirección donde se atiende a los clientes.</p>

                            <div class="mt-6">
                                <label for="address" class="block text-sm font-semibold text-gray-700">Domicilio comercial</label>
                                <textarea id="address" v-model="form.address" rows="3" :class="claseCampo('address')"></textarea>
                                <p v-if="form.errors.address" class="mt-1 text-xs text-jk-red">{{ form.errors.address }}</p>
                            </div>
                        </section>

                        <!-- Datos fiscales: bloque diferenciado -->
                        <section id="fiscal" class="scroll-mt-6 rounded-2xl border border-gray-200 border-l-4 border-l-jk-blue bg-gray-50 p-6">
                            <h2 class="text-lg font-bold text-jk-blue">Datos fiscales</h2>
                            <p class="mt-1 text-sm text-gray-500">Información que se usará en documentos fiscales. Puedes completarla después.</p>

                            <div class="mt-6 grid gap-6 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label for="razon_social" class="block text-sm font-semibold text-gray-700">Razón social / nombre fiscal</label>
                                    <input id="razon_social" v-model="form.razon_social" type="text" maxlength="500" :class="claseCampo('razon_social')">
                                    <p v-if="form.errors.razon_social" class="mt-1 text-xs text-jk-red">{{ form.errors.razon_social }}</p>
                                </div>
                                <div>
                                    <label for="rfc" class="block text-sm font-semibold text-gray-700">RFC</label>
                                    <input id="rfc" v-model="form.rfc" type="text" maxlength="13" autocapitalize="characters" :class="[claseCampo('rfc'), 'uppercase']">
                                    <p v-if="form.errors.rfc" class="mt-1 text-xs text-jk-red">{{ form.errors.rfc }}</p>
                                </div>
                                <div>
                                    <label for="codigo_postal_fiscal" class="block text-sm font-semibold text-gray-700">Código postal fiscal</label>
                                    <input id="codigo_postal_fiscal" v-model="form.codigo_postal_fiscal" type="text" inputmode="numeric"
                                           pattern="[0-9]{5}" maxlength="5" placeholder="00000" :class="claseCampo('codigo_postal_fiscal')">
                                    <p class="mt-1 text-xs text-gray-500">Cinco dígitos.</p>
                                    <p v-if="form.errors.codigo_postal_fiscal" class="mt-1 text-xs text-jk-red">{{ form.errors.codigo_postal_fiscal }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="regimen_fiscal" class="block text-sm font-semibold text-gray-700">Régimen fiscal</label>
                                    <input id="regimen_fiscal" v-model="form.regimen_fiscal" type="text" maxlength="150" :class="claseCampo('regimen_fiscal')">
                                    <p v-if="form.errors.regimen_fiscal" class="mt-1 text-xs text-jk-red">{{ form.errors.regimen_fiscal }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="domicilio_fiscal" class="block text-sm font-semibold text-gray-700">Domicilio fiscal</label>
                                    <textarea id="domicilio_fiscal" v-model="form.domicilio_fiscal" rows="3" maxlength="500" :class="claseCampo('domicilio_fiscal')"></textarea>
                                    <p v-if="form.errors.domicilio_fiscal" class="mt-1 text-xs text-jk-red">{{ form.errors.domicilio_fiscal }}</p>
                                </div>
                            </div>
                        </section>

                        <!-- Cláusulas (se conservan) -->
                        <section id="documentos" class="scroll-mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-bold text-jk-blue">{{ t('settings.clausulas') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">Términos legales que aparecen en la hoja de recepción.</p>
                            <div class="mt-6">
                                <textarea v-model="form.clauses" rows="5" aria-label="Cláusulas legales"
                                          class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 font-mono text-sm shadow-sm focus:border-jk-blue focus:ring-jk-blue"></textarea>
                                <p v-if="form.errors.clauses" class="mt-1 text-xs text-jk-red">{{ form.errors.clauses }}</p>
                            </div>
                        </section>

                        <!-- Garantía (se conserva) -->
                        <section id="garantia" class="scroll-mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-bold text-jk-blue">Garantía</h2>
                            <p class="mt-1 text-sm text-gray-500">Aparece en la hoja de recepción.</p>

                            <div class="mt-6 grid gap-4 md:grid-cols-2">
                                <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" v-model="form.warranty_service_enabled" class="rounded border-gray-300 text-jk-blue focus:ring-jk-blue">
                                        Garantía de servicio (mano de obra)
                                    </label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <span class="text-xs font-semibold text-gray-500">Días</span>
                                            <input v-model="form.warranty_service_days" type="number" min="0" max="3650" :disabled="!form.warranty_service_enabled"
                                                   class="mt-1 w-full rounded-xl border-gray-300 text-sm disabled:opacity-40">
                                            <p v-if="form.errors.warranty_service_days" class="mt-1 text-xs text-jk-red">{{ form.errors.warranty_service_days }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs font-semibold text-gray-500">Kilómetros (opcional)</span>
                                            <input v-model="form.warranty_service_km" type="number" min="0" max="1000000" :disabled="!form.warranty_service_enabled"
                                                   class="mt-1 w-full rounded-xl border-gray-300 text-sm disabled:opacity-40">
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" v-model="form.warranty_parts_enabled" class="rounded border-gray-300 text-jk-blue focus:ring-jk-blue">
                                        Garantía de refacciones
                                    </label>
                                    <div>
                                        <span class="text-xs font-semibold text-gray-500">Días desde la entrega</span>
                                        <input v-model="form.warranty_parts_days" type="number" min="0" max="3650" :disabled="!form.warranty_parts_enabled"
                                               class="mt-1 w-full rounded-xl border-gray-300 text-sm disabled:opacity-40">
                                        <p v-if="form.errors.warranty_parts_days" class="mt-1 text-xs text-jk-red">{{ form.errors.warranty_parts_days }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <span class="text-xs font-semibold text-gray-500">Condiciones adicionales (opcional)</span>
                                <textarea v-model="form.warranty_notes" rows="2" maxlength="1000"
                                          class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-jk-blue focus:ring-jk-blue"></textarea>
                            </div>
                        </section>

                        <!-- Acciones -->
                        <div class="sticky bottom-0 flex items-center justify-end gap-4 rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                            <transition name="fade">
                                <span v-if="form.recentlySuccessful" role="status" class="text-sm font-semibold text-green-700">
                                    ✅ {{ t('settings.guardadoOk') }}
                                </span>
                            </transition>
                            <button type="submit" :disabled="form.processing"
                                    class="rounded-xl px-8 py-3 text-xs font-black uppercase tracking-widest text-white shadow transition hover:opacity-90 active:scale-95 disabled:opacity-50"
                                    :style="{ backgroundColor: form.primary_color || '#10213E' }">
                                {{ form.processing ? t('settings.sincronizando') : t('settings.guardarCambios') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
