// Registro interno de iconos de testigos. Solo se guardan claves en warning_lights.icon; nunca SVG de usuario.
// Las claves DEBEN coincidir con App\Support\IconosTestigo::CLAVES (lo verifica TestigosV2Test).
// Para un icono nuevo: añadir su componente aquí y su clave en IconosTestigo::CLAVES. No hace falta tocar la UI.
import IconEngine from '@/Components/Icons/IconEngine.vue';
import IconAbs from '@/Components/Icons/IconAbs.vue';
import IconOil from '@/Components/Icons/IconOil.vue';
import IconBattery from '@/Components/Icons/IconBattery.vue';
import IconTemp from '@/Components/Icons/IconTemp.vue';
import IconBrake from '@/Components/Icons/IconBrake.vue';
import IconTpms from '@/Components/Icons/IconTpms.vue';
import IconWarning from '@/Components/Icons/IconWarning.vue';
import IconLucesBajas from '@/Components/Icons/IconLucesBajas.vue';
import IconServicio from '@/Components/Icons/IconServicio.vue';
import IconNivelBajoCombustible from '@/Components/Icons/IconNivelBajoCombustible.vue';
import IconEPS from '@/Components/Icons/IconEPS.vue';
import IconAirbag from '@/Components/Icons/IconAirbag.vue';

export const ICONOS_TESTIGO = {
    engine: IconEngine,
    abs: IconAbs,
    oil: IconOil,
    battery: IconBattery,
    temp: IconTemp,
    brake: IconBrake,
    tpms: IconTpms,
    warning: IconWarning,
    low_beam: IconLucesBajas,
    service: IconServicio,
    low_fuel: IconNivelBajoCombustible,
    eps: IconEPS,
    airbag: IconAirbag,
};

// Componente para una clave. Una clave desconocida usa el icono genérico: nunca rompe la pantalla.
export function iconoTestigo(clave) {
    return ICONOS_TESTIGO[clave] ?? ICONOS_TESTIGO.warning;
}
