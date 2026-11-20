<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mechanic_cannot_register_a_payment(): void
    {
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $order = RepairOrder::factory()->create();

        $this->actingAs($mechanic)
            ->post(route('payments.store', $order), ['amount' => 100, 'payment_method' => 'Efectivo'])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_admin_can_register_a_payment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();

        // A2: el pago se registra contra un trabajo autorizado
        \App\Models\RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'labor', 'description' => 'Mano de obra',
            'quantity' => 1, 'unit_price' => 200, 'subtotal' => 200,
        ]);
        app(\App\Actions\RepairOrders\CalculateOrderTotalsAction::class)->execute($order);
        app(\App\Actions\Quotes\CreateQuoteAction::class)->execute($order)->approve('presencial', 'Cliente de prueba');

        $this->actingAs($admin)
            ->post(route('payments.store', $order), ['amount' => 100, 'payment_method' => 'Efectivo'])
            ->assertRedirect(route('repair-orders.show', $order));

        $this->assertDatabaseHas('payments', ['repair_order_id' => $order->id, 'amount' => 100]);
    }

    public function test_mechanic_cannot_delete_a_payment(): void
    {
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $order = RepairOrder::factory()->create();
        $payment = Payment::factory()->create(['repair_order_id' => $order->id]);

        $this->actingAs($mechanic)
            ->delete(route('payments.destroy', [$order, $payment]))
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_admin_can_delete_a_payment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = RepairOrder::factory()->create();
        $payment = Payment::factory()->create(['repair_order_id' => $order->id]);

        $this->actingAs($admin)
            ->delete(route('payments.destroy', [$order, $payment]))
            ->assertRedirect(route('repair-orders.show', $order));

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }
}
