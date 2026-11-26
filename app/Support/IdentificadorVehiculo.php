<?php

namespace App\Support;

/**
 * Identificador flexible del vehículo (Recepción V2). Un VIN completo, sus últimos dígitos,
 * un número de serie u otro texto. El valor se valida según su tipo; el tipo "none" no guarda valor.
 */
final class IdentificadorVehiculo
{
    public const TIPOS = ['vin', 'vin_last_8', 'vin_last_10', 'serial', 'other', 'none'];

    /** Etiqueta en inglés para el PDF, igual que el resto de su rotulado. */
    public static function etiquetaPdf(?string $tipo): string
    {
        return match ($tipo) {
            'vin'         => 'VIN',
            'vin_last_8'  => 'VIN (last 8)',
            'vin_last_10' => 'VIN (last 10)',
            'serial'      => 'Serial no.',
            'other'       => 'ID',
            default       => 'ID',
        };
    }

    /** Mensaje de error para el valor de un tipo, o null si es válido. Vacío exige valor. */
    public static function errorValor(string $tipo, string $valor): ?string
    {
        if ($valor === '') {
            return 'Captura el identificador del vehículo.';
        }

        return match ($tipo) {
            'vin' => match (true) {
                strlen($valor) !== 17 => 'El VIN debe tener exactamente 17 caracteres.',
                ! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/i', $valor) => 'El VIN solo puede contener letras (sin I, O ni Q) y números.',
                default => null,
            },
            'vin_last_8'  => preg_match('/^[A-Z0-9]{8}$/i', $valor) ? null : 'Los últimos 8 del VIN deben ser 8 letras o números.',
            'vin_last_10' => preg_match('/^[A-Z0-9]{10}$/i', $valor) ? null : 'Los últimos 10 del VIN deben ser 10 letras o números.',
            default       => null, // serial y otro: texto libre, con su máximo en la regla
        };
    }

    /** Los tipos VIN se guardan en mayúsculas y sin espacios. */
    public static function normalizarValor(string $tipo, ?string $valor): ?string
    {
        $limpio = trim((string) $valor);
        if ($limpio === '' || $tipo === 'none') {
            return null;
        }

        return in_array($tipo, ['vin', 'vin_last_8', 'vin_last_10'], true) ? strtoupper($limpio) : $limpio;
    }
}
