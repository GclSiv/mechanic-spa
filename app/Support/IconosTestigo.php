<?php

namespace App\Support;

/**
 * Registro interno de iconos de testigos. Solo se guarda la clave; nunca un SVG ni una ruta de usuario.
 * La lista debe coincidir con resources/js/Components/Icons/registroTestigos.js (lo verifica un test).
 *
 * El PDF usa PNG rasterizados ubicados en resources/images/warning-lights/{clave}.png, no los SVG
 * públicos: así el PDF no depende de archivos de public/ ni de su contenido vectorial.
 */
final class IconosTestigo
{
    public const ADVERTENCIA = 'warning';

    /**
     * Biblioteca completa (13 claves). Disponibles para registrar testigos; el catálogo activo
     * (warning_lights) decide cuáles aparecen en Nueva recepción. Añadir una clave exige su componente Vue
     * en registroTestigos.js y su PNG en resources/images/warning-lights/.
     */
    public const CLAVES = [
        'engine', 'abs', 'oil', 'battery', 'temp', 'brake', 'tpms',
        'low_beam', 'service', 'low_fuel', 'eps', 'airbag',
        'warning',
    ];

    /** Carpeta controlada de PNG para PDF (relativa a resources/). */
    public const CARPETA_PNG = 'images/warning-lights';

    /** Clave conocida o, si no existe en el registro, el icono de advertencia genérico. */
    public static function resolver(?string $clave): string
    {
        return in_array($clave, self::CLAVES, true) ? $clave : self::ADVERTENCIA;
    }

    /** Ruta absoluta del PNG de biblioteca para una clave (desconocida → warning.png). */
    public static function rutaPng(?string $clave): string
    {
        return resource_path(self::CARPETA_PNG . '/' . self::resolver($clave) . '.png');
    }

    /**
     * Imagen lista para DomPDF como data URI. Acepta la fuente que devuelve WarningLight::fuenteIcono():
     * biblioteca → PNG del registro; personalizado → archivo ya validado en almacenamiento privado.
     */
    public static function imagenPdf(array $fuente): string
    {
        if (($fuente['tipo'] ?? null) === 'personalizado' && isset($fuente['ruta']) && is_file($fuente['ruta'])) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fuente['ruta']);
            if (array_key_exists($mime, IconoPersonalizado::FORMATOS)) {
                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fuente['ruta']));
            }
        }

        $ruta = self::rutaPng($fuente['clave'] ?? null);

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($ruta));
    }
}
