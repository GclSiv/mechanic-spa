<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Cuentas (Finanzas): la lista de órdenes recientes clasifica el estado financiero de cada orden.
 * Una orden sin monto autorizado no es "Pagada" aunque su saldo sea 0. Las órdenes liquidadas sí lo son.
 */
class FinanceCuentasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Orden con recepción, cliente y vehículo, como las que lista Cuentas. */
    private function orden(): RepairOrder
    {
        $status = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'recibido'],
            ['name' => 'recibido', 'color_class' => 'bg-gray-500']
        );
        $cliente = Client::factory()->create();
        $vehiculo = Vehicle::factory()->create(['client_id' => $cliente->id]);
        $recepcion = Recepcion::create([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'fuel_level' => '1/2',
        ]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id, 'tax_rate' => 16, 'status_id' => $status->id,
        ]);
    }

    private function concepto(RepairOrder $order, float $subtotal): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => 'labor', 'description' => 'Trabajo',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    /** Cotización principal aprobada con importe autorizado fijo (snapshot). */
    private function autorizar(RepairOrder $order, float $monto): void
    {
        $quote = app(CreateQuoteAction::class)->execute($order, null, Quote::KIND_PRINCIPAL);
        $quote->update(['total' => $monto]);
        $quote->approve('presencial', 'Cliente de prueba');
    }

    /** Fila de la orden en "órdenes recientes" tal como la entrega el controlador. */
    private function filaReciente(RepairOrder $order): array
    {
        $fila = null;

        $this->actingAs($this->admin())
            ->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($order, &$fila) {
                $fila = collect($page->toArray()['props']['recentOrders'])->firstWhere('id', $order->id);
            });

        $this->assertNotNull($fila, 'La orden debe aparecer en órdenes recientes.');

        return $fila;
    }

    public function test_orden_sin_monto_autorizado_no_se_marca_como_liquidada(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 500); // trabajo técnico sin cotización aprobada

        $fila = $this->filaReciente($orden);

        $this->assertFalse($fila['autorizado'], 'Sin cotización aprobada no hay monto autorizado.');
        $this->assertFalse($fila['liquidado'], 'Autorizado = 0 no es "Pagado".');
    }

    public function test_orden_sin_conceptos_ni_cotizacion_no_se_marca_como_pagada(): void
    {
        $fila = $this->filaReciente($this->orden());

        $this->assertFalse($fila['autorizado']);
        $this->assertFalse($fila['liquidado']);
    }

    public function test_orden_con_saldo_autorizado_pendiente_no_se_marca_como_liquidada(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);

        $fila = $this->filaReciente($orden);

        $this->assertTrue($fila['autorizado']);
        $this->assertFalse($fila['liquidado']);
    }

    public function test_orden_realmente_liquidada_sigue_marcada_como_liquidada(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);

        $this->actingAs($this->admin())
            ->post(route('payments.store', $orden), ['amount' => 1000, 'payment_method' => 'Cash'])
            ->assertRedirect();

        $fila = $this->filaReciente($orden);

        $this->assertTrue($fila['autorizado']);
        $this->assertTrue($fila['liquidado']);
    }

    public function test_mecanico_no_puede_consultar_cuentas(): void
    {
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)->get(route('finance.index'))->assertForbidden();
    }
}
