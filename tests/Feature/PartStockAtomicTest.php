<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Descuento de stock en addItem: comprobación y decremento en una sola operación condicional.
 *
 * Nota: SQLite (pruebas) ejecuta las peticiones en secuencia, así que estas pruebas validan
 * el comportamiento determinista (sin stock, sin decremento, sin negativos, cantidad entera).
 * Una prueba real de concurrencia requiere varias conexiones simultáneas contra MySQL.
 */
class PartStockAtomicTest extends TestCase
{
    use RefreshDatabase;

    private function part(int $stock): Part
    {
        return Part::create([
            'name' => 'Balata', 'sku' => 'BAL-' . uniqid(), 'sale_price' => 150, 'cost_price' => 80,
            'stock' => $stock, 'low_stock_threshold' => 1,
        ]);
    }

    private function agregarRefaccion($actor, RepairOrder $order, Part $part, $quantity)
    {
        return $this->actingAs($actor)->post(route('repair-orders.items.store', $order), [
            'type'       => 'part',
            'part_id'    => $part->id,
            'quantity'   => $quantity,
            'unit_price' => 150,
        ]);
    }

    public function test_request_with_insufficient_stock_fails(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();
        $part = $this->part(1);

        $this->agregarRefaccion($admin, $order, $part, 2)
            ->assertSessionHasErrors(['quantity']);

        $this->assertDatabaseCount('repair_order_items', 0);
    }

    public function test_stock_is_never_decremented_when_the_operation_cannot_complete(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();
        $part = $this->part(1);

        $this->agregarRefaccion($admin, $order, $part, 2)->assertSessionHasErrors(['quantity']);
        $this->agregarRefaccion($admin, $order, $part, 5)->assertSessionHasErrors(['quantity']);

        $this->assertSame(1, $part->fresh()->stock);
        $this->assertDatabaseCount('repair_order_items', 0);
    }

    public function test_stock_never_becomes_negative_across_consecutive_requests(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();
        $part = $this->part(3);

        $this->agregarRefaccion($admin, $order, $part, 2)->assertRedirect();
        $this->agregarRefaccion($admin, $order, $part, 2)->assertSessionHasErrors(['quantity']);

        $this->assertSame(1, $part->fresh()->stock);
        $this->assertDatabaseCount('repair_order_items', 1);
    }

    public function test_exact_remaining_stock_can_be_consumed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();
        $part = $this->part(2);

        $this->agregarRefaccion($admin, $order, $part, 2)->assertRedirect();

        $this->assertSame(0, $part->fresh()->stock);
    }

    public function test_mechanic_request_with_insufficient_stock_fails_without_decrement(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $mechanic = \App\Models\Mechanic::create([
            'name' => 'Mec', 'user_id' => $user->id,
            'gender_id' => \App\Models\Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => \App\Models\MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);
        $order = RepairOrder::factory()->create(['mechanic_id' => $mechanic->id]);
        $part = $this->part(1);

        $this->agregarRefaccion($user, $order, $part, 3)->assertSessionHasErrors(['quantity']);

        $this->assertSame(1, $part->fresh()->stock);
    }

    // ── Cantidad de refacciones: enteros positivos ──────────────────────

    public function test_decimal_part_quantity_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();
        $part = $this->part(5);

        $this->agregarRefaccion($admin, $order, $part, 0.5)->assertSessionHasErrors(['quantity']);
        $this->agregarRefaccion($admin, $order, $part, 1.5)->assertSessionHasErrors(['quantity']);

        $this->assertDatabaseCount('repair_order_items', 0);
        $this->assertSame(5, $part->fresh()->stock);
    }

    public function test_zero_and_negative_part_quantities_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();
        $part = $this->part(5);

        $this->agregarRefaccion($admin, $order, $part, 0)->assertSessionHasErrors(['quantity']);
        $this->agregarRefaccion($admin, $order, $part, -2)->assertSessionHasErrors(['quantity']);

        $this->assertDatabaseCount('repair_order_items', 0);
        $this->assertSame(5, $part->fresh()->stock);
    }

    public function test_labor_quantity_keeps_its_existing_validation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();

        // La mano de obra no cambia de criterio en esta fase
        $this->actingAs($admin)->post(route('repair-orders.items.store', $order), [
            'type'        => 'labor',
            'description' => 'Diagnóstico',
            'quantity'    => 1.5,
            'unit_price'  => 300,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('repair_order_items', ['repair_order_id' => $order->id, 'type' => 'labor']);
    }
}
