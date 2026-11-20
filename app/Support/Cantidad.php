<?php

namespace App\Support;

/**
 * Aritmética y formato de cantidades de conceptos de orden.
 *
 * - Refacciones: cantidad entera (1, 2, 10). Se muestra sin decimales.
 * - Mano de obra: magnitud con hasta dos decimales (1.50). Se muestra con dos decimales.
 *
 * El importe se calcula en centavos y diezmilésimas para no introducir errores de punto flotante.
 */
class Cantidad
{
    /** Importe de una línea: cantidad × precio unitario, redondeado a centavos. */
    public static function importe(string|int|float $cantidad, string|int|float $precioUnitario): float
    {
        $cantidadCentavos = (int) round((float) $cantidad * 100);
        $precioCentavos   = (int) round((float) $precioUnitario * 100);

        // (centavos × centavos) = diezmilésimas; dividir entre 10 000 da unidades monetarias
        return round($cantidadCentavos * $precioCentavos / 10000, 2);
    }

    /** Texto para mostrar la cantidad según el tipo de concepto. */
    public static function texto(?string $tipo, string|int|float|null $cantidad): string
    {
        $valor = (float) $cantidad;

        return $tipo === 'part'
            ? (string) (int) round($valor)
            : number_format($valor, 2, '.', '');
    }
}
