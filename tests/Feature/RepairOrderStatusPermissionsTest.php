<?php

namespace Tests\Feature;

use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estados de la orden por rol (decisión A1.1):
 * el mecánico usa solo estados operativos en sus órdenes; entregado y rechazado son de admin/owner.
 */
class RepairOrderStatusPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => 'Mecánico de prueba',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function estado(string $slug): RepairOrderStatus
    {
        return RepairOrderStatus::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500']
        );
    }

    private function orderIn(string $slug, ?Mechanic $mechanic = null): RepairOrder
    {
        return RepairOrder::factory()->create([
            'status_id'   => $this->estado($slug)->id,
            'mechanic_id' => $mechanic?->id,
        ]);
    }

    private function cambiar($actor, RepairOrder $order, string $slug)
    {
        return $this->actingAs($actor)->patch(
            route('repair-orders.status.update', $order),
            ['status_id' => $this->estado($slug)->id]
        );
    }

    // ── Mecánico: estados operativos en su orden ─────────────────────────

    public function test_mechanic_can_move_own_order_to_operational_statuses(): void
    {
        [$user, $mechanic] = $this->mechanic();

        foreach (['diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'] as $slug) {
            $order = $this->orderIn('recibido', $mechanic);

            $this->cambiar($user, $order, $slug)->assertRedirect(route('repair-orders.show', $order));

            $this->assertSame($this->estado($slug)->id, $order->fresh()->status_id, "No cambió a {$slug}");
        }
    }

    public function test_mechanic_cannot_set_entregado(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->orderIn('reparado', $mechanic);

        $this->cambiar($user, $order, 'entregado')->assertForbidden();

        $this->assertSame($this->estado('reparado')->id, $order->fresh()->status_id);
    }

    public function test_mechanic_cannot_set_rechazado(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->orderIn('en-reparacion', $mechanic);

        $this->cambiar($user, $order, 'rechazado')->assertForbidden();

        $this->assertSame($this->estado('en-reparacion')->id, $order->fresh()->status_id);
    }

    public function test_mechanic_cannot_set_a_status_outside_the_operational_list(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->orderIn('en-reparacion', $mechanic);

        $this->cambiar($user, $order, 'recibido')->assertForbidden();
    }

    public function test_mechanic_cannot_change_a_closed_order(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $entregada = $this->orderIn('entregado', $mechanic);
        $rechazada = $this->orderIn('rechazado', $mechanic);

        $this->cambiar($user, $entregada, 'reparado')->assertForbidden();
        $this->cambiar($user, $rechazada, 'en-reparacion')->assertForbidden();

        $this->assertSame($this->estado('entregado')->id, $entregada->fresh()->status_id);
        $this->assertSame($this->estado('rechazado')->id, $rechazada->fresh()->status_id);
    }

    public function test_mechanic_cannot_change_status_of_another_mechanics_order(): void
    {
        [$user] = $this->mechanic();
        [, $otro] = $this->mechanic();
        $order = $this->orderIn('recibido', $otro);

        $this->cambiar($user, $order, 'diagnostico')->assertForbidden();
    }

    // ── Admin / owner conservan todos los estados ────────────────────────

    public function test_admin_can_set_rechazado(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->orderIn('recibido');

        $this->cambiar($admin, $order, 'rechazado')->assertRedirect();

        $this->assertSame($this->estado('rechazado')->id, $order->fresh()->status_id);
    }

    public function test_owner_can_set_entregado_when_balance_is_zero(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $order = $this->orderIn('reparado');

        $this->cambiar($owner, $order, 'entregado')->assertRedirect();

        $this->assertSame($this->estado('entregado')->id, $order->fresh()->status_id);
    }

    public function test_admin_cannot_reopen_a_closed_order(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->orderIn('rechazado');

        $this->cambiar($admin, $order, 'en-reparacion')->assertForbidden();

        $this->assertSame($this->estado('rechazado')->id, $order->fresh()->status_id);
    }

    public function test_admin_cannot_change_an_entregada_order(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->orderIn('entregado');

        $this->cambiar($admin, $order, 'reparado')->assertForbidden();
        $this->cambiar($admin, $order, 'rechazado')->assertForbidden();

        $this->assertSame($this->estado('entregado')->id, $order->fresh()->status_id);
    }

    public function test_admin_cannot_change_a_rechazada_order_to_any_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->orderIn('rechazado');

        foreach (['recibido', 'diagnostico', 'entregado'] as $slug) {
            $this->cambiar($admin, $order, $slug)->assertForbidden();
        }

        $this->assertSame($this->estado('rechazado')->id, $order->fresh()->status_id);
    }

    public function test_admin_valid_transitions_on_open_orders_still_work(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->orderIn('recibido');

        $this->cambiar($admin, $order, 'diagnostico')->assertRedirect();
        $this->assertSame($this->estado('diagnostico')->id, $order->fresh()->status_id);

        $this->cambiar($admin, $order, 'espera-piezas')->assertRedirect();
        $this->assertSame($this->estado('espera-piezas')->id, $order->fresh()->status_id);
    }

    public function test_cancelling_an_order_does_not_return_parts_to_stock(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->orderIn('en-reparacion');
        $part = \App\Models\Part::create([
            'name' => 'Balata', 'sku' => 'BAL-' . uniqid(), 'sale_price' => 150, 'cost_price' => 80,
            'stock' => 4, 'low_stock_threshold' => 1,
        ]);
        RepairOrderItem::create([
            'repair_order_id' => $order->id,
            'type'            => 'part',
            'part_id'         => $part->id,
            'description'     => $part->name,
            'quantity'        => 1,
            'unit_price'      => 150,
            'subtotal'        => 150,
        ]);

        $this->cambiar($admin, $order, 'rechazado')->assertRedirect();

        // La refacción pudo haberse usado físicamente: el stock no se devuelve automáticamente
        $this->assertSame(4, $part->fresh()->stock);
    }
}
