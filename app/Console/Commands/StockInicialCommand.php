<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crea el movimiento "inicial" de refacciones que tienen stock pero aún no tienen kardex.
 * Idempotente: la clave por refacción evita duplicados aunque el comando se ejecute varias veces.
 * Se ejecuta como comando, no dentro de una migración.
 */
class StockInicialCommand extends Command
{
    protected $signature = 'parts:stock-inicial {--dry-run : Muestra qué se crearía sin escribir nada}';

    protected $description = 'Crea el movimiento inicial del kardex para refacciones con stock y sin historial.';

    public function handle(): int
    {
        $pendientes = Part::where('stock', '>', 0)
            ->whereDoesntHave('movements')
            ->orderBy('id')
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay refacciones pendientes de stock inicial.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("Se crearían {$pendientes->count()} movimientos iniciales:");
            $pendientes->each(fn (Part $p) => $this->line("  #{$p->id} {$p->sku}: stock {$p->stock}"));

            return self::SUCCESS;
        }

        $creados = 0;
        foreach ($pendientes as $parte) {
            // Línea base: el stock ya existe en la refacción, así que el movimiento lo registra sin sumarlo.
            // Por eso no pasa por RegistrarMovimientoStockAction (que sí modifica stock).
            $clave = 'inicial:part:' . $parte->id;

            $creado = DB::transaction(function () use ($parte, $clave) {
                if (StockMovement::where('idempotency_key', $clave)->exists()) {
                    return false;
                }

                $actual = (int) Part::whereKey($parte->id)->lockForUpdate()->value('stock');

                StockMovement::create([
                    'part_id'         => $parte->id,
                    'type'            => StockMovement::TIPO_INICIAL,
                    'quantity'        => $actual,
                    'stock_before'    => 0,
                    'stock_after'     => $actual,
                    'reason'          => 'Stock inicial al activar el kardex',
                    'idempotency_key' => $clave,
                ]);

                return true;
            });

            if ($creado) {
                $creados++;
            }
        }

        $this->info("Movimientos iniciales creados: {$creados}.");

        return self::SUCCESS;
    }
}
