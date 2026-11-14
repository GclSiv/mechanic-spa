<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Cálculo de IVA para México.
 *
 * - Tasa general: 16% sobre refacciones y mano de obra.
 * - Zona fronteriza: 8% sobre refacciones y mano de obra.
 *
 * Los importes se redondean a centavos al final, no por concepto.
 * Clase pura: no depende de base de datos, por lo que se puede probar sola.
 */
class TaxCalculator
{
    public const GENERAL  = 16.00;
    public const FRONTERA = 8.00;

    public const RATES = [self::GENERAL, self::FRONTERA];

    public static function isValidRate(float $rate): bool
    {
        return in_array($rate, self::RATES, true);
    }

    public static function compute(float $partsTotal, float $laborTotal, float $rate): array
    {
        if (! self::isValidRate($rate)) {
            throw new InvalidArgumentException("Tasa de IVA no soportada: {$rate}");
        }

        $partsSubtotal = round($partsTotal, 2);
        $laborSubtotal = round($laborTotal, 2);
        $subtotal      = round($partsSubtotal + $laborSubtotal, 2);
        $tax           = round($subtotal * ($rate / 100), 2);

        return [
            'parts_subtotal' => $partsSubtotal,
            'labor_subtotal' => $laborSubtotal,
            'subtotal'       => $subtotal,
            'tax_rate'       => $rate,
            'tax'            => $tax,
            'total'          => round($subtotal + $tax, 2),
        ];
    }
}
