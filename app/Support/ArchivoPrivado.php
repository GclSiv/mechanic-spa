<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Archivos privados (fotos de clientes, recepciones, notas y avance de taller).
 *
 * - Los archivos nuevos se guardan en el disco privado (storage/app/private), nunca en /storage público.
 * - Solo se sirven desde endpoints con autorización del backend. Ninguna URL pública se entrega a Vue.
 * - Compatibilidad TEMPORAL: si una copia privada no existe, se sirve la copia legada del disco público,
 *   solo a través de estos mismos endpoints. Se retira con `php artisan app:migrate-private-media --retirar`.
 * - Las rutas llegan de la base de datos, nunca del navegador; rutaSegura() rechaza cualquier cosa
 *   que no sea una ruta relativa simple (sin "..", sin absolutas, sin barras invertidas).
 */
final class ArchivoPrivado
{
    public const DISCO = 'local';

    /** Disco público anterior: solo lectura de archivos históricos mientras se migran. */
    public const DISCO_LEGADO = 'public';

    private const MIMES_IMAGEN = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];

    public static function rutaSegura(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || strlen($path) > 255) {
            return null;
        }
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0") || str_starts_with($path, '/')) {
            return null;
        }
        if (! preg_match('#^[A-Za-z0-9_\-./]+$#', $path)) {
            return null;
        }

        return $path;
    }

    public static function guardar(UploadedFile $archivo, string $carpeta): string
    {
        return Storage::disk(self::DISCO)->putFile($carpeta, $archivo);
    }

    /** Ruta absoluta del archivo (privado primero, legado después). Null si no existe o la ruta no es válida. */
    public static function rutaAbsoluta(mixed $path): ?string
    {
        $ruta = self::rutaSegura($path);
        if ($ruta === null) {
            return null;
        }

        foreach ([self::DISCO, self::DISCO_LEGADO] as $disco) {
            $absoluta = Storage::disk($disco)->path($ruta);
            if (is_file($absoluta)) {
                return $absoluta;
            }
        }

        return null;
    }

    /** Respuesta inline de una imagen. 404 si no existe, no es imagen o la ruta no es válida. */
    public static function respuesta(mixed $path, string $nombre = 'foto'): BinaryFileResponse
    {
        $absoluta = self::rutaAbsoluta($path);
        abort_if($absoluta === null, 404);

        $mime = mime_content_type($absoluta) ?: 'application/octet-stream';
        abort_unless(in_array($mime, self::MIMES_IMAGEN, true), 404);

        $respuesta = response()->file($absoluta, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => (new ResponseHeaderBag)->makeDisposition(
                'inline',
                $nombre,
                'foto'
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-store');

        return $respuesta;
    }

    /** Borra la copia privada y la legada del mismo archivo, si existen. */
    public static function eliminar(mixed $path): void
    {
        $ruta = self::rutaSegura($path);
        if ($ruta === null) {
            return;
        }

        foreach ([self::DISCO, self::DISCO_LEGADO] as $disco) {
            if (Storage::disk($disco)->exists($ruta)) {
                Storage::disk($disco)->delete($ruta);
            }
        }
    }
}
