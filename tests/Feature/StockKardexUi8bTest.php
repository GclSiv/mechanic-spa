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
use LogicException;
use Tests\TestCase;

/**
 * UI-8B: código de barras, kardex inmutable, movimientos manuales solo admin/owner,
 * stock inicial idempotente y atomicidad de órdenes.
 */
class StockKardexUi8bTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['role' => User::ROLE_ADMIN]); }
    private function owner(): User { return User::factory()->create(['role' => User::ROLE_OWNER]); }
    private function mecanico(): User { return User::factory()->create(['role' => User::ROLE_MECHANIC]); }

    private function part(int $stock = 5, array $extra = []): Part
    {
        return Part::create(array_merge([
            'name' => 'Balata', 'sku' => 'BAL-' . uniqid(), 'sale_price' => 150, 'cost_price' => 80,
            'stock' => $stock, 'low_stock_threshold' => 1,
        ], $extra));
    }

    private function datosAlta(array $extra = []): array
    {
        return array_merge([
            'name' => 'Filtro', 'sku' => 'FIL-' . uniqid(), 'cost_price' => 10, 'sale_price' => 20,
            'stock' => 0, 'low_stock_threshold' => 1,
        ], $extra);
    }

    // ---------- Código de barras ----------

    public function test_codigo_de_barras_es_unico_y_los_vacios_no_chocan(): void
    {
        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosAlta(['barcode' => '7501234567890']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosAlta(['barcode' => '7501234567890']))
            ->assertSessionHasErrors('barcode');

        // Varias refacciones sin código de barras (null) son válidas.
        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosAlta(['barcode' => '']))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosAlta(['barcode' => '  ']))->assertSessionHasNoErrors();

        $this->assertSame(2, Part::whereNull('barcode')->count());
    }

    public function test_la_base_rechaza_codigo_de_barras_duplicado(): void
    {
        $this->part(0, ['barcode' => 'DUP-1']);

        $this->expectException(QueryException::class);
        $this->part(0, ['barcode' => 'DUP-1']);
    }

    public function test_escanear_busca_por_codigo_de_barras_y_luego_por_sku(): void
    {
        $admin = $this->admin();
        $porBarras = $this->part(3, ['barcode' => 'BARRAS-9', 'sku' => 'SKU-A']);
        $porSku = $this->part(3, ['barcode' => null, 'sku' => 'SKU-B']);

        $this->actingAs($admin)->get(route('parts.scan', ['codigo' => 'BARRAS-9']))
            ->assertRedirect(route('parts.kardex', $porBarras));

        $this->actingAs($admin)->get(route('parts.scan', ['codigo' => 'SKU-B']))
            ->assertRedirect(route('parts.kardex', $porSku));
    }

    public function test_escanear_sin_coincidencia_muestra_error(): void
    {
        $this->actingAs($this->admin())->get(route('parts.scan', ['codigo' => 'NO-EXISTE']))
            ->assertSessionHas('error');
    }

    // ---------- Acceso ----------

    public function test_mecanico_no_accede_al_kardex_ni_a_movimientos_ni_a_escaneo(): void
    {
        $parte = $this->part(5);
        $mecanico = $this->mecanico();

        $this->actingAs($mecanico)->get(route('parts.kardex', $parte))->assertForbidden();
        $this->actingAs($mecanico)->get(route('parts.scan', ['codigo' => 'X']))->assertForbidden();
        $this->actingAs($mecanico)->post(route('parts.movimientos.store', $parte), [
            'type' => 'entrada', 'quantity' => 2,
        ])->assertForbidden();

        $this->assertSame(5, $parte->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_admin_y_owner_pueden_ver_el_kardex(): void
    {
        $parte = $this->part(5);

        $this->actingAs($this->admin())->get(route('parts.kardex', $parte))->assertOk();
        $this->actingAs($this->owner())->get(route('parts.kardex', $parte))->assertOk();
    }

    // ---------- Movimientos manuales ----------

    public function test_entrada_suma_y_registra_antes_y_despues(): void
    {
        $parte = $this->part(5);

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'entrada', 'quantity' => 3, 'reason' => 'Compra',
        ])->assertRedirect(route('parts.kardex', $parte));

        $this->assertSame(8, $parte->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $parte->id, 'type' => 'entrada', 'quantity' => 3,
            'stock_before' => 5, 'stock_after' => 8, 'reason' => 'Compra',
        ]);
    }

    public function test_salida_descuenta_y_exige_motivo(): void
    {
        $parte = $this->part(5);

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'salida', 'quantity' => 2,
        ])->assertSessionHasErrors('reason');

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'salida', 'quantity' => 2, 'reason' => 'Merma',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $parte->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['type' => 'salida', 'quantity' => -2, 'stock_before' => 5, 'stock_after' => 3]);
    }

    public function test_salida_mayor_al_stock_se_rechaza_sin_dejar_rastro(): void
    {
        $parte = $this->part(1);

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'salida', 'quantity' => 4, 'reason' => 'Prueba',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(1, $parte->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_ajuste_acepta_delta_firmado_con_motivo_y_no_cero(): void
    {
        $parte = $this->part(5);

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'ajuste', 'quantity' => -2,
        ])->assertSessionHasErrors('reason');

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'ajuste', 'quantity' => 0, 'reason' => 'Conteo',
        ])->assertSessionHasErrors('quantity');

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'ajuste', 'quantity' => -2, 'reason' => 'Conteo físico',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $parte->fresh()->stock);
    }

    public function test_tipo_no_manual_no_puede_registrarse_desde_el_kardex(): void
    {
        $parte = $this->part(5);

        $this->actingAs($this->admin())->post(route('parts.movimientos.store', $parte), [
            'type' => 'consumo_orden', 'quantity' => 1,
        ])->assertSessionHasErrors('type');
    }

    // ---------- Inmutabilidad ----------

    public function test_los_movimientos_no_se_editan_ni_eliminan(): void
    {
        $parte = $this->part(5);
        $movimiento = StockMovement::create([
            'part_id' => $parte->id, 'type' => 'entrada', 'quantity' => 1,
            'stock_before' => 5, 'stock_after' => 6,
        ]);

        $this->expectException(LogicException::class);
        $movimiento->update(['quantity' => 99]);
    }

    public function test_los_movimientos_no_se_eliminan_con_delete(): void
    {
        $parte = $this->part(5);
        $movimiento = StockMovement::create([
            'part_id' => $parte->id, 'type' => 'entrada', 'quantity' => 1,
            'stock_before' => 5, 'stock_after' => 6,
        ]);

        $this->expectException(LogicException::class);
        $movimiento->delete();
    }

    // ---------- Alta, edición y borrado ----------

    public function test_alta_con_stock_crea_movimiento_inicial(): void
    {
        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosAlta(['sku' => 'ALTA-1', 'stock' => 7]));

        $parte = Part::where('sku', 'ALTA-1')->firstOrFail();
        $this->assertSame(7, $parte->stock);
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $parte->id, 'type' => 'inicial', 'quantity' => 7, 'stock_before' => 0, 'stock_after' => 7,
        ]);
    }

    public function test_alta_sin_stock_no_crea_movimiento(): void
    {
        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosAlta(['sku' => 'ALTA-0', 'stock' => 0]));

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_no_se_puede_borrar_una_refaccion_con_movimientos(): void
    {
        $parte = $this->part(5);
        StockMovement::create([
            'part_id' => $parte->id, 'type' => 'inicial', 'quantity' => 5, 'stock_before' => 0, 'stock_after' => 5,
        ]);

        $this->actingAs($this->admin())->delete(route('parts.destroy', $parte))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('parts', ['id' => $parte->id]);
    }

    // ---------- Órdenes: consumo y reversa ----------

    public function test_agregar_refaccion_a_la_orden_crea_consumo_vinculado_al_concepto(): void
    {
        $admin = $this->admin();
        $order = RepairOrder::factory()->create();
        $parte = $this->part(5);

        $this->actingAs($admin)->post(route('repair-orders.items.store', $order), [
            'type' => 'part', 'part_id' => $parte->id, 'quantity' => 2, 'unit_price' => 150,
        ])->assertSessionHasNoErrors();

        $item = RepairOrderItem::where('part_id', $parte->id)->firstOrFail();
        $this->assertSame(3, $parte->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $parte->id, 'type' => 'consumo_orden', 'quantity' => -2,
            'stock_before' => 5, 'stock_after' => 3, 'repair_order_item_id' => $item->id,
        ]);
    }

    public function test_sin_stock_la_orden_no_crea_concepto_ni_movimiento(): void
    {
        $admin = $this->admin();
        $order = RepairOrder::factory()->create();
        $parte = $this->part(1);

        $this->actingAs($admin)->post(route('repair-orders.items.store', $order), [
            'type' => 'part', 'part_id' => $parte->id, 'quantity' => 4, 'unit_price' => 150,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('repair_order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1, $parte->fresh()->stock);
    }

    public function test_eliminar_refaccion_de_la_orden_crea_reversa_y_devuelve_stock(): void
    {
        $admin = $this->admin();
        $order = RepairOrder::factory()->create();
        $parte = $this->part(5);

        $this->actingAs($admin)->post(route('repair-orders.items.store', $order), [
            'type' => 'part', 'part_id' => $parte->id, 'quantity' => 2, 'unit_price' => 150,
        ]);
        $item = RepairOrderItem::where('part_id', $parte->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('repair-orders.items.destroy', [$order, $item]))
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $parte->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $parte->id, 'type' => 'reversa_orden', 'quantity' => 2,
            'stock_before' => 3, 'stock_after' => 5, 'repair_order_item_id' => null,
        ]);
        // Consumo y reversa quedan ambos en el kardex.
        $this->assertSame(2, StockMovement::where('part_id', $parte->id)->count());
    }

    // ---------- Stock inicial (comando idempotente) ----------

    public function test_comando_stock_inicial_crea_un_movimiento_por_refaccion_sin_historial(): void
    {
        $sinHistorial = $this->part(4);
        $conHistorial = $this->part(6);
        StockMovement::create([
            'part_id' => $conHistorial->id, 'type' => 'entrada', 'quantity' => 6, 'stock_before' => 0, 'stock_after' => 6,
        ]);
        $sinStock = $this->part(0);

        Artisan::call('parts:stock-inicial');

        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $sinHistorial->id, 'type' => 'inicial', 'quantity' => 4,
            'stock_before' => 0, 'stock_after' => 4, 'idempotency_key' => 'inicial:part:' . $sinHistorial->id,
        ]);
        $this->assertSame(0, StockMovement::where('part_id', $sinStock->id)->count());
        $this->assertSame(1, StockMovement::where('part_id', $conHistorial->id)->count());
    }

    public function test_comando_stock_inicial_es_idempotente(): void
    {
        $parte = $this->part(4);

        Artisan::call('parts:stock-inicial');
        Artisan::call('parts:stock-inicial');
        Artisan::call('parts:stock-inicial');

        $this->assertSame(1, StockMovement::where('part_id', $parte->id)->count());
        $this->assertSame(4, $parte->fresh()->stock);
    }

    public function test_comando_stock_inicial_en_dry_run_no_escribe(): void
    {
        $parte = $this->part(4);

        Artisan::call('parts:stock-inicial', ['--dry-run' => true]);

        $this->assertSame(0, StockMovement::where('part_id', $parte->id)->count());
    }
}
