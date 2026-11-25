<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\User;
use App\Support\Cantidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Integridad de quantity en conceptos de orden (A1.2):
 * refacciones enteras; mano de obra con hasta dos decimales. Se verifica el dato persistido.
 */
class ItemQuantityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function part(int $stock = 10): Part
    {
        return Part::create([
            'name' => 'Balata', 'sku' => 'BAL-' . uniqid(), 'sale_price' => 300, 'cost_price' => 80,
            'stock' => $stock, 'low_stock_threshold' => 1,
        ]);
    }

    private function agregar($actor, RepairOrder $order, array $datos)
    {
        return $this->actingAs($actor)->post(route('repair-orders.items.store', $order), $datos);
    }

    private function refaccion(Part $part, $cantidad): array
    {
        return ['type' => 'part', 'part_id' => $part->id, 'quantity' => $cantidad, 'unit_price' => 300];
    }

    private function manoDeObra($cantidad, $precio = 300): array
    {
        return ['type' => 'labor', 'description' => 'Cambio de balatas', 'quantity' => $cantidad, 'unit_price' => $precio, 'mechanic_id' => $this->ejecutor()];
    }

    private function ultimoItem(): RepairOrderItem
    {
        return RepairOrderItem::query()->orderByDesc('id')->firstOrFail();
    }

    // ── Esquema ─────────────────────────────────────────────────────────────

    public function test_quantity_column_is_now_decimal(): void
    {
        // MySQL/MariaDB reportan "decimal"; SQLite (pruebas) reporta "numeric" para DECIMAL.
        $this->assertContains(Schema::getColumnType('repair_order_items', 'quantity'), ['decimal', 'numeric']);
    }

    // ── Refacciones: solo enteros positivos ────────────────────────────────

    public function test_part_quantity_one_is_persisted_as_one(): void
    {
        $order = RepairOrder::factory()->create();
        $part = $this->part(10);

        $this->agregar($this->admin(), $order, $this->refaccion($part, 1))->assertRedirect();

        $item = $this->ultimoItem();
        $this->assertSame('1.00', $item->quantity);
        $this->assertEquals(1.0, (float) DB::table('repair_order_items')->where('id', $item->id)->value('quantity'));
        $this->assertSame(9, $part->fresh()->stock);
    }

    public function test_part_quantity_two_works(): void
    {
        $order = RepairOrder::factory()->create();
        $part = $this->part(10);

        $this->agregar($this->admin(), $order, $this->refaccion($part, 2))->assertRedirect();

        $this->assertSame('2.00', $this->ultimoItem()->quantity);
        $this->assertSame(8, $part->fresh()->stock);
    }

    public function test_part_quantity_rejects_zero_negative_and_fractions(): void
    {
        $order = RepairOrder::factory()->create();
        $part = $this->part(10);

        foreach ([0, -1, 0.5, 1.5] as $cantidad) {
            $this->agregar($this->admin(), $order, $this->refaccion($part, $cantidad))
                ->assertSessionHasErrors(['quantity']);
        }

        $this->assertDatabaseCount('repair_order_items', 0);
        $this->assertSame(10, $part->fresh()->stock);
    }

    // ── Mano de obra: hasta dos decimales ──────────────────────────────────

    public function test_labor_quantity_one_is_persisted(): void
    {
        $order = RepairOrder::factory()->create();

        $this->agregar($this->admin(), $order, $this->manoDeObra(1))->assertSessionHasNoErrors();

        $this->assertSame('1.00', $this->ultimoItem()->quantity);
    }

    public function test_labor_quantity_1_50_is_preserved_exactly_with_subtotal_450(): void
    {
        $order = RepairOrder::factory()->create();

        $this->agregar($this->admin(), $order, $this->manoDeObra('1.50', 300))->assertSessionHasNoErrors();

        $item = $this->ultimoItem();

        // Dato persistido: cantidad exacta y subtotal = 1.50 × 300
        $this->assertSame('1.50', $item->quantity);
        $this->assertEquals(1.5, (float) DB::table('repair_order_items')->where('id', $item->id)->value('quantity'));
        $this->assertEquals(450.00, (float) DB::table('repair_order_items')->where('id', $item->id)->value('subtotal'));
        $this->assertEquals(300.0, (float) DB::table('repair_order_items')->where('id', $item->id)->value('unit_price'));
    }

    public function test_labor_quantity_0_25_and_2_25_are_preserved(): void
    {
        $order = RepairOrder::factory()->create();

        $this->agregar($this->admin(), $order, $this->manoDeObra('0.25', 300))->assertSessionHasNoErrors();
        $this->assertSame('0.25', $this->ultimoItem()->quantity);
        $this->assertEquals(75.00, (float) $this->ultimoItem()->subtotal);

        $this->agregar($this->admin(), $order, $this->manoDeObra('2.25', 100))->assertSessionHasNoErrors();
        $this->assertSame('2.25', $this->ultimoItem()->quantity);
        $this->assertEquals(225.00, (float) $this->ultimoItem()->subtotal);
    }

    public function test_labor_subtotal_is_exact_without_floating_point_drift(): void
    {
        $order = RepairOrder::factory()->create();

        // 0.10 × 0.30 en punto flotante daría 0.030000000000000002; el importe debe ser 0.03
        $this->agregar($this->admin(), $order, $this->manoDeObra('0.10', '0.30'))->assertSessionHasNoErrors();

        $this->assertEquals(0.03, (float) $this->ultimoItem()->subtotal);
    }

    public function test_labor_rejects_zero_negative_and_more_than_two_decimals(): void
    {
        $order = RepairOrder::factory()->create();

        foreach (['0', '-1', '1.005', '0.333'] as $cantidad) {
            $this->agregar($this->admin(), $order, $this->manoDeObra($cantidad))
                ->assertSessionHasErrors(['quantity']);
        }

        $this->assertDatabaseCount('repair_order_items', 0);
    }

    // ── Historial: enteros existentes ───────────────────────────────────────

    public function test_existing_integer_quantities_read_back_as_two_decimals(): void
    {
        $order = RepairOrder::factory()->create();
        DB::table('repair_order_items')->insert([
            'repair_order_id' => $order->id, 'type' => 'labor', 'description' => 'Histórico',
            'quantity' => 2, 'unit_price' => 100, 'subtotal' => 200,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('2.00', $this->ultimoItem()->quantity);
    }

    // ── Helper de formato e importe ────────────────────────────────────────

    public function test_display_helper_shows_parts_as_integers_and_labor_with_two_decimals(): void
    {
        $this->assertSame('2', Cantidad::texto('part', '2.00'));
        $this->assertSame('1.50', Cantidad::texto('labor', '1.50'));
        $this->assertSame('1.00', Cantidad::texto('labor', 1));
        $this->assertSame('0.25', Cantidad::texto('labor', '0.25'));
    }

    public function test_importe_helper_is_exact(): void
    {
        $this->assertSame(450.0, Cantidad::importe('1.50', 300));
        $this->assertSame(0.03, Cantidad::importe('0.10', '0.30'));
        $this->assertSame(8.33, Cantidad::importe('0.25', 33.33));
    }

    // ── Vista PDF de la orden ───────────────────────────────────────────────

    public function test_order_pdf_view_shows_labor_with_two_decimals_and_parts_as_integers(): void
    {
        $order = RepairOrder::factory()->create();
        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'part', 'description' => 'Balata',
            'quantity' => 2, 'unit_price' => 100, 'subtotal' => 200,
        ]);
        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'labor', 'description' => 'Mano de obra',
            'quantity' => '1.50', 'unit_price' => 300, 'subtotal' => 450,
        ]);

        $html = view('pdf.cotizacion', [
            'orden'     => $order->fresh()->load('items'),
            'recepcion' => null,
            'client'    => null,
            'vehicle'   => null,
            'settings'  => \App\Models\Setting::current(),
            'subtotal'  => 650,
            'iva'       => 104,
            'tax_rate'  => 16,
            'total'     => 754,
        ])->render();

        $this->assertStringContainsString('>1.50<', $html);
        $this->assertStringContainsString('>2<', $html);
        $this->assertStringNotContainsString('2.00', $html);
    }

    /** Mecánico ejecutor para conceptos de mano de obra nuevos (la aplicación exige mechanic_id). */
    private function ejecutor(): int
    {
        return \App\Models\Mechanic::create([
            'name' => 'Ejecutor de prueba',
            'gender_id' => \App\Models\Gender::firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => \App\Models\MechanicType::firstOrCreate(['name' => 'General'])->id,
        ])->id;
    }
}
