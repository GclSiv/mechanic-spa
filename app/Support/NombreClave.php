<?php

namespace App\Support;

/**
 * Clave determinista para detectar duplicados de marcas y modelos.
 * Minúsculas, espacios colapsados y sin acentos del español. No altera el nombre visible.
 */
final class NombreClave
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ];

    public static function de(?string $texto): string
    {
        $clave = mb_strtolower(trim((string) $texto));
        $clave = preg_replace('/\s+/u', ' ', $clave);

        return strtr($clave, self::ACENTOS);
    }
}
