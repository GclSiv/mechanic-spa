<?php

namespace App\Actions\Parts;

use App\Models\Part;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Único punto que cambia stock de refacciones (UI-8B).
 * Bloquea la fila, valida que no quede negativo y actualiza solo si el stock sigue igual que al leerlo
 * (compare-and-set). El movimiento se escribe en la misma transacción: no hay cambio sin rastro.
 */
class RegistrarMovimientoStockAction
{
    /**
     * @param  int          $cantidad  Delta firmado: positivo suma, negativo descuenta. Nunca 0.
     * @param  string|null  $clave     Clave idempotente: si ya existe un movimiento con ella, se devuelve sin cambiar nada.
     */
    public function ejecutar(
        int $partId,
        string $tipo,
        int $cantidad,
        ?int $userId,
        ?string $motivo = null,
        ?int $itemId = null,
        ?string $clave = null,
    ): StockMovement {
        if ($cantidad === 0) {
            throw new InvalidArgumentException('Un movimiento no puede tener cantidad cero.');
        }

        return DB::transaction(function () use ($partId, $tipo, $cantidad, $userId, $motivo, $itemId, $clave) {
            if ($clave !== null && ($existente = StockMovement::where('idempotency_key', $clave)->first())) {
                return $existente;
            }

            $parte = Part::whereKey($partId)->lockForUpdate()->firstOrFail();
            $antes = (int) $parte->stock;
            $despues = $antes + $cantidad;

            if ($despues < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuficiente. Disponible: {$antes}",
                ]);
            }

            $filas = Part::whereKey($partId)->where('stock', $antes)->update(['stock' => $despues]);

            if ($filas !== 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'El stock cambió mientras se registraba el movimiento. Intenta de nuevo.',
                ]);
            }

            return StockMovement::create([
                'part_id'               => $partId,
                'type'                  => $tipo,
                'quantity'              => $cantidad,
                'stock_before'          => $antes,
                'stock_after'           => $despues,
                'user_id'               => $userId,
                'repair_order_item_id'  => $itemId,
                'reason'                => $motivo,
                'idempotency_key'       => $clave,
            ]);
        });
    }
}
