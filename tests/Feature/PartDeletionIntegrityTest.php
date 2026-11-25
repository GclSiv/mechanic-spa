<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Integridad de borrado de refacciones (UI-8B).
 * Regla: sin historial se borra; con conceptos de orden o con movimientos de kardex, no.
 * Las FK son RESTRICT (no CASCADE): la base también protege, pero el flujo de aplicación bloquea antes.
 */
class PartDeletionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['role' => User::ROLE_ADMIN]); }

    private function part(int $stock = 0): Part
    {
        return Part::create([
            'name' => 'Filtro', 'sku' => 'DEL-' . uniqid(), 'sale_price' => 20, 'cost_price' => 10,
            'stock' => $stock, 'low_stock_threshold' => 1,
        ]);
    }

    public function test_caso_1_sin_historial_puede_eliminarse(): void
    {
        $parte = $this->part(0);

        $this->actingAs($this->admin())->delete(route('parts.destroy', $parte))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('parts', ['id' => $parte->id]);
    }

    public function test_caso_2_con_concepto_de_orden_se_bloquea(): void
    {
        $parte = $this->part(0);
        $orden = RepairOrder::factory()->create();
        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'part_id' => $parte->id, 'type' => 'part',
            'description' => $parte->name, 'quantity' => 1, 'unit_price' => 20, 'subtotal' => 20,
        ]);

        $this->actingAs($this->admin())->delete(route('parts.destroy', $parte))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('parts', ['id' => $parte->id]);
        $this->assertDatabaseCount('repair_order_items', 1);
    }

    public function test_caso_3_con_movimientos_de_kardex_se_bloquea_y_el_kardex_permanece(): void
    {
        $parte = $this->part(0);
        StockMovement::create([
            'part_id' => $parte->id, 'type' => 'entrada', 'quantity' => 3,
            'stock_before' => 0, 'stock_after' => 3, 'reason' => 'Compra',
        ]);

        $this->actingAs($this->admin())->delete(route('parts.destroy', $parte))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('parts', ['id' => $parte->id]);
        $this->assertDatabaseHas('stock_movements', ['part_id' => $parte->id, 'reason' => 'Compra']);
    }

    /** Protección de base: un borrado directo fuera de la aplicación también falla y no arrastra el kardex. */
    public function test_la_base_rechaza_borrar_una_refaccion_con_movimientos(): void
    {
        $parte = $this->part(0);
        StockMovement::create([
            'part_id' => $parte->id, 'type' => 'entrada', 'quantity' => 3,
            'stock_before' => 0, 'stock_after' => 3,
        ]);

        $this->expectException(QueryException::class);
        \Illuminate\Support\Facades\DB::table('parts')->where('id', $parte->id)->delete();
    }

    /** Saldo inicial: no suma stock, solo registra la línea base. Idempotente. */
    public function test_saldo_inicial_registra_linea_base_sin_modificar_el_stock(): void
    {
        $parte = $this->part(7);

        Artisan::call('parts:stock-inicial');
        Artisan::call('parts:stock-inicial');

        $this->assertSame(7, $parte->fresh()->stock);
        $this->assertSame(1, StockMovement::where('part_id', $parte->id)->count());
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $parte->id, 'type' => 'inicial', 'quantity' => 7,
            'stock_before' => 0, 'stock_after' => 7, 'idempotency_key' => 'inicial:part:' . $parte->id,
        ]);
    }
}
