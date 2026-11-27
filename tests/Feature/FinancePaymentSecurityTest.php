<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Client;
use App\Models\Mechanic;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finanzas: permisos y aislamiento de pagos en el backend (no solo en la vista).
 */
class FinancePaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        return User::factory()->create(['role' => $rol]);
    }

    /** Orden con recepción; si se indica, asignada a ese usuario mecánico. */
    private function orden(?User $mecanico = null): RepairOrder
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

        $responsable = $mecanico?->mechanic ?? null;

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'tax_rate' => 16,
            'status_id' => $status->id,
            'mechanic_id' => $responsable?->id,
        ]);
    }

    /** Orden con una principal aprobada por $monto y un pago previo de $pagado (opcional). */
    private function ordenAutorizada(float $monto, float $pagado = 0): RepairOrder
    {
        $orden = $this->orden();
        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Trabajo',
            'quantity' => 1, 'unit_price' => $monto, 'subtotal' => $monto,
        ]);
        $quote = app(CreateQuoteAction::class)->execute($orden, null, Quote::KIND_PRINCIPAL);
        $quote->update(['total' => $monto]);
        $quote->approve('presencial', 'Cliente de prueba');

        if ($pagado > 0) {
            $orden->payments()->create(['amount' => $pagado, 'payment_method' => 'Cash', 'quote_id' => $quote->id]);
        }

        return $orden->fresh();
    }

    public function test_mecanico_asignado_no_puede_registrar_pagos_en_su_orden(): void
    {
        $usuario = $this->usuario(User::ROLE_MECHANIC);
        $mecanico = Mechanic::create([
            'name' => 'Mec', 'gender_id' => \App\Models\Gender::firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => \App\Models\MechanicType::firstOrCreate(['name' => 'General'])->id,
            'user_id' => $usuario->id,
        ]);
        $orden = $this->orden();
        $orden->update(['mechanic_id' => $mecanico->id]);

        $this->actingAs($usuario)
            ->post(route('payments.store', $orden), ['amount' => 100, 'payment_method' => 'Cash'])
            ->assertForbidden();

        $this->assertSame(0, Payment::where('repair_order_id', $orden->id)->count());
    }

    public function test_mecanico_no_puede_consultar_el_ticket_de_pago(): void
    {
        $orden = $this->ordenAutorizada(1000, 500);
        $pago = $orden->payments()->first();

        $this->actingAs($this->usuario(User::ROLE_MECHANIC))
            ->get(route('payments.ticket', ['order' => $orden->id, 'payment' => $pago->id]))
            ->assertForbidden();
    }

    public function test_owner_puede_registrar_pagos(): void
    {
        $orden = $this->ordenAutorizada(1000);

        $this->actingAs($this->usuario(User::ROLE_OWNER))
            ->post(route('payments.store', $orden), ['amount' => 400, 'payment_method' => 'Cash'])
            ->assertRedirect();

        $this->assertEquals(400.0, (float) $orden->payments()->sum('amount'));
    }

    public function test_pago_de_otra_orden_no_se_elimina_desde_esta(): void
    {
        $admin = $this->usuario(User::ROLE_ADMIN);
        $ajena = $this->ordenAutorizada(1000, 500);
        $pagoAjeno = $ajena->payments()->first();
        $propia = $this->ordenAutorizada(1000);

        $this->actingAs($admin)
            ->delete(route('payments.destroy', ['order' => $propia->id, 'payment' => $pagoAjeno->id]))
            ->assertNotFound();

        $this->assertNotNull(Payment::find($pagoAjeno->id), 'El pago de otra orden debe seguir existiendo.');
    }

    public function test_ticket_de_pago_de_otra_orden_responde_404(): void
    {
        $admin = $this->usuario(User::ROLE_ADMIN);
        $ajena = $this->ordenAutorizada(1000, 500);
        $pagoAjeno = $ajena->payments()->first();
        $propia = $this->ordenAutorizada(1000);

        $this->actingAs($admin)
            ->get(route('payments.ticket', ['order' => $propia->id, 'payment' => $pagoAjeno->id]))
            ->assertNotFound();
    }

    public function test_cotizacion_de_otra_orden_no_se_puede_aprobar_desde_esta(): void
    {
        $admin = $this->usuario(User::ROLE_ADMIN);
        $ajena = $this->orden();
        RepairOrderItem::create([
            'repair_order_id' => $ajena->id, 'type' => 'labor', 'description' => 'X',
            'quantity' => 1, 'unit_price' => 300, 'subtotal' => 300,
        ]);
        $cotizacionAjena = app(CreateQuoteAction::class)->execute($ajena, null, Quote::KIND_PRINCIPAL);
        $cotizacionAjena->markSent();

        $propia = $this->orden();

        $this->actingAs($admin)
            ->patch(route('repair-orders.quotes.approve', ['order' => $propia->id, 'quote' => $cotizacionAjena->id]), [
                'approval_method' => 'presencial', 'approved_by_name' => 'Cliente',
            ])
            ->assertNotFound();

        $this->assertSame(Quote::ENVIADA, $cotizacionAjena->fresh()->status);
    }
}
