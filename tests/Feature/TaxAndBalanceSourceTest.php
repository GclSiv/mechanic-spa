<?php

namespace Tests\Feature;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Support\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una sola fuente de cálculo para subtotal, IVA y total (TaxCalculator vía CalculateOrderTotalsAction).
 * El saldo que bloquea la entrega y el total que muestran las listas deben coincidir.
 */
class TaxAndBalanceSourceTest extends TestCase
{
    use RefreshDatabase;

    private function ordenConRefaccionYManoDeObra(): RepairOrder
    {
        $order = RepairOrder::factory()->create(['tax_rate' => 16]);

        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'part', 'description' => 'Balata',
            'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100,
        ]);
        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'labor', 'description' => 'Mano de obra',
            'quantity' => 1, 'unit_price' => 200, 'subtotal' => 200,
        ]);

        return $order;
    }

    public function test_iva_applies_to_parts_and_labor_at_the_configured_rate(): void
    {
        $order = $this->ordenConRefaccionYManoDeObra();

        $breakdown = app(CalculateOrderTotalsAction::class)->execute($order);

        // Decisión A1.1: refacciones y mano de obra con la misma tasa (16 %)
        $this->assertSame(TaxCalculator::compute(100, 200, 16)['total'], $breakdown['total']);
        $this->assertSame(348.0, $breakdown['total']);
        $this->assertSame(348.0, (float) $order->fresh()->estimated_cost);
    }

    public function test_delivery_is_blocked_by_the_same_total_the_order_shows(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->ordenConRefaccionYManoDeObra();
        $entregado = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'entregado'], ['name' => 'Entregado', 'color_class' => 'bg-gray-500']
        );
        $reparado = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'reparado'], ['name' => 'Reparado', 'color_class' => 'bg-gray-500']
        );
        $order->update(['status_id' => $reparado->id]);

        // Un centavo menos que el total: no se entrega
        Payment::create(['repair_order_id' => $order->id, 'amount' => 347.99, 'payment_method' => 'Cash']);

        $this->actingAs($admin)
            ->patch(route('repair-orders.status.update', $order), ['status_id' => $entregado->id])
            ->assertSessionHas('error');

        $this->assertSame($reparado->id, $order->fresh()->status_id);

        // Se completa el total exacto que muestra la orden: ahora sí se entrega
        Payment::create(['repair_order_id' => $order->id, 'amount' => 0.01, 'payment_method' => 'Cash']);

        $this->actingAs($admin)
            ->patch(route('repair-orders.status.update', $order), ['status_id' => $entregado->id])
            ->assertRedirect();

        $this->assertSame($entregado->id, $order->fresh()->status_id);
    }
}
