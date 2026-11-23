<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function estadosCerrados(): array
    {
        return [['entregado'], ['rechazado']];
    }

    private function ordenEn(string $slug, ?int $mechanicId = null): RepairOrder
    {
        $status = RepairOrderStatus::firstOrCreate(['slug' => $slug], ['name' => $slug, 'color_class' => 'bg-gray-500']);

        return RepairOrder::factory()->create([
            'status_id'   => $status->id,
            'tax_rate'    => 16,
            'mechanic_id' => $mechanicId,
        ]);
    }

    #[DataProvider('estadosCerrados')]
    public function test_nadie_registra_ni_elimina_pagos_en_orden_cerrada(string $slug): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->ordenEn($slug);
        $pago = $order->payments()->create(['amount' => 100, 'payment_method' => 'Cash']);

        $this->actingAs($admin)
            ->post(route('payments.store', $order), ['amount' => 50, 'payment_method' => 'Cash'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('payments.destroy', [$order, $pago]))
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['id' => $pago->id]);
    }

    #[DataProvider('estadosCerrados')]
    public function test_admin_ve_historial_y_resumen_de_pagos_en_orden_cerrada(string $slug): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->ordenEn($slug);
        $order->payments()->create(['amount' => 100, 'payment_method' => 'Cash']);

        $this->actingAs($admin)
            ->get(route('repair-orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.esta_cerrada', true)
                ->has('orden.payments', 1)
                ->where('estado_financiero.total_pagado', 100)
            );
    }

    public function test_mechanic_no_recibe_pagos_ni_is_paid_en_su_orden(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $mechanic = Mechanic::create([
            'name'             => 'Mecánico de prueba',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);
        $order = $this->ordenEn('en-reparacion', $mechanic->id);
        $order->payments()->create(['amount' => 100, 'payment_method' => 'Cash']);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('estado_financiero', null)
                ->missing('orden.payments')
                ->missing('orden.is_paid')
                ->missing('orden.estimated_cost')
            );
    }
}
