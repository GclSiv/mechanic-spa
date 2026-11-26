<?php

namespace App\Support;

/**
 * Reglas de un icono personalizado subido por el usuario. Preparación: aún no hay endpoint de carga.
 *
 * Solo PNG, WebP o JPG. Se valida el MIME REAL del contenido (fileinfo), no la extensión. SVG, HTML,
 * ejecutables y cualquier otro tipo se rechazan. Dimensiones: mínimo 64, máximo 1024, cuadrado (1:1).
 * Peso máximo 500 KB. El almacenamiento final usa nombre aleatorio y normalización a 128×128 (futuro).
 */
final class IconoPersonalizado
{
    /** MIME real permitido => extensión de almacenamiento. */
    public const FORMATOS = [
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
    ];

    public const MAX_BYTES = 500 * 1024;
    public const MIN_PX = 64;
    public const MAX_PX = 1024;
    public const OBJETIVO_PX = 128;
    public const RECOMENDADO_PX = 256;

    /**
     * Valida un archivo temporal ya recibido. Devuelve la lista de errores (vacía si es válido).
     *
     * @return list<string>
     */
    public static function errores(string $rutaArchivo): array
    {
        if (! is_file($rutaArchivo) || ! is_readable($rutaArchivo)) {
            return ['El archivo no se pudo leer.'];
        }

        $errores = [];

        $bytes = filesize($rutaArchivo);
        if ($bytes === false || $bytes > self::MAX_BYTES) {
            $errores[] = 'El icono no puede pesar más de 500 KB.';
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($rutaArchivo);
        if (! array_key_exists($mime, self::FORMATOS)) {
            // Incluye image/svg+xml, text/html, application/x-dosexec, etc.
            return array_merge($errores, ['Formato no permitido. Usa PNG, WebP o JPG.']);
        }

        $medidas = @getimagesize($rutaArchivo);
        if ($medidas === false) {
            return array_merge($errores, ['La imagen está dañada o no es una imagen válida.']);
        }

        [$ancho, $alto] = $medidas;
        if ($ancho !== $alto) {
            $errores[] = 'El icono debe ser cuadrado (relación 1:1).';
        }
        if ($ancho < self::MIN_PX || $alto < self::MIN_PX) {
            $errores[] = 'El icono debe medir al menos 64 × 64 px.';
        }
        if ($ancho > self::MAX_PX || $alto > self::MAX_PX) {
            $errores[] = 'El icono no puede medir más de 1024 × 1024 px.';
        }

        return $errores;
    }
}
