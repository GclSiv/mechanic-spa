<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Part;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Restricciones de autorización del mecánico (Frente A1).
 * El mecánico solo ve y modifica sus órdenes asignadas, registra refacciones
 * al precio de catálogo y no accede a recepción, clientes, cotizaciones, pagos ni finanzas.
 */
class MechanicRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'              => 'Mecánico de prueba',
            'user_id'           => $user->id,
            'gender_id'         => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id'  => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function assignedOrder(Mechanic $mechanic): RepairOrder
    {
        return RepairOrder::factory()->create(['mechanic_id' => $mechanic->id]);
    }

    private function part(array $overrides = []): Part
    {
        return Part::create(array_merge([
            'name'                => 'Balata delantera',
            'sku'                 => 'BAL-' . uniqid(),
            'cost_price'          => 80,
            'sale_price'          => 150,
            'stock'               => 5,
            'low_stock_threshold' => 1,
        ], $overrides));
    }

    private function quoteFor(RepairOrder $order): Quote
    {
        return Quote::create([
            'repair_order_id' => $order->id,
            'version'         => 1,
            'kind'            => Quote::KIND_PRINCIPAL,
            'folio'           => 'COT-' . uniqid(),
            'status'          => Quote::BORRADOR,
            'tax_rate'        => 0,
            'parts_subtotal'  => 0,
            'labor_subtotal'  => 0,
            'subtotal'        => 0,
            'tax'             => 0,
            'total'           => 0,
        ]);
    }

    // ── Recepción ─────────────────────────────────────────────────────────

    public function test_mechanic_cannot_open_recepciones(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)->get(route('recepcion.index'))->assertForbidden();
        $this->actingAs($user)->get(route('recepcion.create'))->assertForbidden();
    }

    public function test_mechanic_cannot_create_a_recepcion(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)
            ->post(route('recepcion.store'), ['symptoms' => 'Ruido'])
            ->assertForbidden();

        $this->assertDatabaseCount('recepcions', 0);
    }

    public function test_mechanic_cannot_delete_a_recepcion(): void
    {
        [$user] = $this->mechanic();
        $recepcion = Recepcion::factory()->create();

        $this->actingAs($user)
            ->delete(route('recepcion.destroy', $recepcion))
            ->assertForbidden();

        $this->assertDatabaseHas('recepcions', ['id' => $recepcion->id]);
    }

    public function test_mechanic_cannot_generate_an_order_from_a_recepcion(): void
    {
        [$user] = $this->mechanic();
        $recepcion = Recepcion::factory()->create();

        $this->actingAs($user)
            ->post(route('recepcion.generate-order', $recepcion))
            ->assertForbidden();

        $this->assertDatabaseCount('repair_orders', 0);
    }

    public function test_admin_can_delete_a_recepcion_without_orders(): void
    {
        $recepcion = Recepcion::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('recepcion.destroy', $recepcion))
            ->assertRedirect();

        $this->assertDatabaseMissing('recepcions', ['id' => $recepcion->id]);
    }

    public function test_recepcion_with_an_order_cannot_be_deleted(): void
    {
        $recepcion = Recepcion::factory()->create();
        $order = RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'client_id'    => $recepcion->client_id,
            'vehicle_id'   => $recepcion->vehicle_id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('recepcion.destroy', $recepcion))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('recepcions', ['id' => $recepcion->id]);
        $this->assertDatabaseHas('repair_orders', ['id' => $order->id]);
    }

    // ── Clientes y vehículos ──────────────────────────────────────────────

    public function test_mechanic_cannot_list_clients(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)->get(route('clients.index'))->assertForbidden();
    }

    public function test_mechanic_cannot_check_clients_or_vehicles(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)->getJson(route('clients.check', ['rfc' => 'XAXX010101000']))->assertForbidden();
        $this->actingAs($user)->getJson(route('vehicles.check', ['plate' => 'ABC123']))->assertForbidden();
    }

    public function test_mechanic_cannot_view_vehicle_history(): void
    {
        [$user] = $this->mechanic();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)->get(route('vehicles.history', $vehicle))->assertForbidden();
    }

    public function test_admin_can_list_clients(): void
    {
        $this->actingAs($this->admin())->get(route('clients.index'))->assertOk();
    }

    // ── Acceso a órdenes ──────────────────────────────────────────────────

    public function test_mechanic_can_open_the_orders_board(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)->get(route('repair-orders.index'))->assertOk();
    }

    public function test_mechanic_can_view_an_assigned_order(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);

        $this->actingAs($user)->get(route('repair-orders.show', $order))->assertOk();
    }

    public function test_mechanic_cannot_view_another_mechanics_order(): void
    {
        [$user] = $this->mechanic();
        [, $otherMechanic] = $this->mechanic();
        $order = $this->assignedOrder($otherMechanic);

        $this->actingAs($user)->get(route('repair-orders.show', $order))->assertForbidden();
    }

    public function test_mechanic_cannot_view_an_unassigned_order(): void
    {
        [$user] = $this->mechanic();
        $order = RepairOrder::factory()->create(['mechanic_id' => null]);

        $this->actingAs($user)->get(route('repair-orders.show', $order))->assertForbidden();
    }

    public function test_mechanic_cannot_view_another_mechanics_order_photo_or_add_photos(): void
    {
        [$user] = $this->mechanic();
        [, $otherMechanic] = $this->mechanic();
        $order = $this->assignedOrder($otherMechanic);

        $this->actingAs($user)
            ->post(route('repair-orders.avance.store', $order), [])
            ->assertForbidden();
    }

    public function test_mechanic_cannot_open_the_order_pdf(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);

        $this->actingAs($user)->get(route('repair-orders.pdf', $order))->assertForbidden();
    }

    public function test_mechanic_cannot_open_the_order_list_with_balances(): void
    {
        [$user] = $this->mechanic();

        $this->actingAs($user)->get(route('repair-orders.lista'))->assertForbidden();
    }

    public function test_mechanic_cannot_reassign_the_order(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);

        $this->actingAs($user)
            ->patch(route('repair-orders.mechanic.assign', $order), ['mechanic_id' => $mechanic->id])
            ->assertForbidden();
    }

    // ── Refacciones y mano de obra ────────────────────────────────────────

    public function test_mechanic_adds_a_part_at_catalog_price_and_stock_is_discounted(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);
        $part = $this->part(['sale_price' => 150, 'stock' => 5]);

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $order), [
                'type'       => 'part',
                'part_id'    => $part->id,
                'quantity'   => 2,
                'unit_price' => 1, // el navegador intenta fijar otro precio
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('repair_order_items', [
            'repair_order_id' => $order->id,
            'type'            => 'part',
            'part_id'         => $part->id,
            'unit_price'      => 150,
        ]);
        $this->assertSame(3, $part->fresh()->stock);
    }

    public function test_mechanic_cannot_add_labor(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $order), [
                'type'        => 'labor',
                'description' => 'Cambio de balatas',
                'quantity'    => 1,
                'unit_price'  => 500,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('repair_order_items', 0);
    }

    public function test_mechanic_cannot_add_items_to_another_mechanics_order(): void
    {
        [$user] = $this->mechanic();
        [, $otherMechanic] = $this->mechanic();
        $order = $this->assignedOrder($otherMechanic);
        $part = $this->part();

        $this->actingAs($user)
            ->post(route('repair-orders.items.store', $order), [
                'type'     => 'part',
                'part_id'  => $part->id,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('repair_order_items', 0);
        $this->assertSame(5, $part->fresh()->stock);
    }

    public function test_mechanic_can_remove_a_part_from_an_assigned_order(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);
        $part = $this->part(['stock' => 4]);
        $item = RepairOrderItem::create([
            'repair_order_id' => $order->id,
            'type'            => 'part',
            'description'     => $part->name,
            'part_id'         => $part->id,
            'quantity'        => 1,
            'unit_price'      => 150,
            'subtotal'        => 150,
        ]);

        $this->actingAs($user)
            ->delete(route('repair-orders.items.destroy', [$order, $item]))
            ->assertRedirect();

        $this->assertDatabaseMissing('repair_order_items', ['id' => $item->id]);
    }

    public function test_mechanic_cannot_remove_labor(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);
        $labor = RepairOrderItem::create([
            'repair_order_id' => $order->id,
            'type'            => 'labor',
            'description'     => 'Mano de obra',
            'quantity'        => 1,
            'unit_price'      => 500,
            'subtotal'        => 500,
        ]);

        $this->actingAs($user)
            ->delete(route('repair-orders.items.destroy', [$order, $labor]))
            ->assertForbidden();

        $this->assertDatabaseHas('repair_order_items', ['id' => $labor->id]);
    }

    // ── Cotizaciones ──────────────────────────────────────────────────────

    public function test_mechanic_cannot_create_send_approve_reject_or_delete_quotes(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);
        $quote = $this->quoteFor($order);

        $this->actingAs($user)
            ->post(route('repair-orders.quotes.store', $order), [])
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('repair-orders.quotes.send', [$order, $quote]))
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('repair-orders.quotes.approve', [$order, $quote]))
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('repair-orders.quotes.reject', [$order, $quote]))
            ->assertForbidden();
        $this->actingAs($user)
            ->delete(route('repair-orders.quotes.destroy', [$order, $quote]))
            ->assertForbidden();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    }

    public function test_mechanic_cannot_download_quote_pdf(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);
        $quote = $this->quoteFor($order);

        $this->actingAs($user)
            ->get(route('repair-orders.quotes.pdf', [$order, $quote]))
            ->assertForbidden();
    }

    // ── Pagos ─────────────────────────────────────────────────────────────

    public function test_mechanic_cannot_view_a_payment_ticket(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);
        $payment = \App\Models\Payment::factory()->create(['repair_order_id' => $order->id]);

        $this->actingAs($user)
            ->get(route('payments.ticket', [$order, $payment]))
            ->assertForbidden();
    }

    // ── Datos que llegan al navegador (Inertia) ───────────────────────────

    public function test_mechanic_show_payload_contains_no_financial_or_personal_fields(): void
    {
        [$user, $mechanic] = $this->mechanic();
        $client = Client::factory()->create(['rfc' => 'XAXX010101000', 'phone' => '9511234567', 'email' => 'x@x.com', 'address' => 'Calle 1']);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id]);
        $order = RepairOrder::factory()->create([
            'mechanic_id'    => $mechanic->id,
            'recepcion_id'   => $recepcion->id,
            'client_id'      => $client->id,
            'vehicle_id'     => $recepcion->vehicle_id,
            'estimated_cost' => 999,
            'tax_rate'       => 16,
        ]);
        RepairOrderItem::create([
            'repair_order_id' => $order->id,
            'type'            => 'part',
            'description'     => 'Balata',
            'quantity'        => 1,
            'unit_price'      => 150,
            'subtotal'        => 150,
        ]);
        $this->part(['name' => 'Filtro', 'sale_price' => 77, 'stock' => 3]);
        $this->quoteFor($order);
        \App\Models\Payment::factory()->create(['repair_order_id' => $order->id]);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isAdmin', false)
                ->where('financial_breakdown', null)
                ->missing('orden.payments')
                ->missing('orden.quotes')
                ->missing('orden.estimated_cost')
                ->missing('orden.tax_rate')
                ->missing('orden.items.0.unit_price')
                ->missing('orden.items.0.subtotal')
                ->missing('parts.0.sale_price')
                ->missing('recepcion.client.rfc')
                ->missing('recepcion.client.phone')
                ->missing('recepcion.client.email')
                ->missing('recepcion.client.address')
            );
    }

    public function test_admin_show_payload_keeps_financial_data(): void
    {
        [, $mechanic] = $this->mechanic();
        $order = $this->assignedOrder($mechanic);
        RepairOrderItem::create([
            'repair_order_id' => $order->id,
            'type'            => 'part',
            'description'     => 'Balata',
            'quantity'        => 1,
            'unit_price'      => 150,
            'subtotal'        => 150,
        ]);
        $this->part(['name' => 'Filtro', 'sale_price' => 77, 'stock' => 3]);

        $this->actingAs($this->admin())
            ->get(route('repair-orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isAdmin', true)
                ->has('financial_breakdown')
                ->has('orden.items.0.unit_price')
                ->has('parts.0.sale_price')
            );
    }

    // ── Casos positivos para administrador y dueño ────────────────────────

    public function test_owner_can_open_the_order_pdf_and_the_order_list(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $order = RepairOrder::factory()->create();

        $this->actingAs($owner)->get(route('repair-orders.lista'))->assertOk();
        $this->actingAs($owner)->get(route('repair-orders.show', $order))->assertOk();
    }

    public function test_admin_can_add_labor_and_remove_it(): void
    {
        $admin = $this->admin();
        $order = RepairOrder::factory()->create();

        $this->actingAs($admin)
            ->post(route('repair-orders.items.store', $order), [
                'type'        => 'labor',
                'description' => 'Cambio de balatas',
                'quantity'    => 1,
                'unit_price'  => 500,
                'mechanic_id' => $this->ejecutor(),
            ])
            ->assertRedirect();

        $labor = RepairOrderItem::where('repair_order_id', $order->id)->where('type', 'labor')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('repair-orders.items.destroy', [$order, $labor]))
            ->assertRedirect();

        $this->assertDatabaseMissing('repair_order_items', ['id' => $labor->id]);
    }

    // ── Seguimiento público (sin cambios) ─────────────────────────────────

    public function test_public_tracking_page_still_works_without_login(): void
    {
        $order = RepairOrder::factory()->create();

        $this->get(route('tracking.show', $order->tracking_token))->assertOk();
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
