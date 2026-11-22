<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Saldo por cobrar de la orden frente a los pagos asociados a cotizaciones (FIN-PAY-1).
 *
 * Reglas (A2, sin cambios):
 * - total_autorizado = suma de snapshots de cotizaciones APROBADAS.
 * - total_pagado = todos los pagos de la orden, con o sin quote_id.
 * - saldo_autorizado = max(total_autorizado - total_pagado, 0), sin compensar entre órdenes.
 * - Un pago asociado a cotización además debe caber en su saldo de referencia.
 */
class PaymentQuoteBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orden(): RepairOrder
    {
        return RepairOrder::factory()->create(['tax_rate' => 16]);
    }

    private function concepto(RepairOrder $order, string $tipo, float $subtotal): RepairOrderItem
    {
        return RepairOrderItem::create([
            'repair_order_id' => $order->id, 'type' => $tipo, 'description' => 'Concepto',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    /** Cotización por el flujo normal, con el importe autorizado del caso fijado como snapshot. */
    private function autorizar(RepairOrder $order, string $kind, float $monto): Quote
    {
        $quote = app(CreateQuoteAction::class)->execute($order, null, $kind);
        $quote->update(['total' => $monto]);
        $quote->approve('presencial', 'Cliente de prueba');

        return $quote->fresh();
    }

    /** Cotización creada directamente en BD (estado arbitrario, p. ej. histórico o enviada). */
    private function cotizacion(RepairOrder $order, string $kind, string $status, float $total): Quote
    {
        return Quote::create([
            'repair_order_id' => $order->id, 'version' => 99, 'kind' => $kind,
            'folio' => 'TEST-' . uniqid(), 'status' => $status, 'tax_rate' => 0,
            'parts_subtotal' => 0, 'labor_subtotal' => 0, 'subtotal' => $total,
            'tax' => 0, 'total' => $total,
        ]);
    }

    private function pagar(RepairOrder $order, float $monto, ?int $quoteId = null): Payment
    {
        return Payment::create([
            'repair_order_id' => $order->id, 'amount' => $monto,
            'payment_method' => 'Efectivo', 'quote_id' => $quoteId,
        ]);
    }

    private function estado(RepairOrder $order): array
    {
        return app(EstadoFinancieroAction::class)->execute($order->fresh());
    }

    private function registrar(RepairOrder $order, float $monto, ?int $quoteId = null)
    {
        return $this->actingAs($this->admin())->post(route('payments.store', $order), [
            'amount' => $monto, 'payment_method' => 'Efectivo', 'quote_id' => $quoteId,
        ]);
    }

    /** Escenario observado: A principal 1,740 + B adicional 481.40; anticipo general 191.40; pago B 481.40. */
    private function escenarioObservado(): array
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $a = $this->autorizar($order, Quote::KIND_PRINCIPAL, 1740.00);
        $this->concepto($order, 'part', 400); // trabajo nuevo, sin autorizar
        $b = $this->autorizar($order, Quote::KIND_ADICIONAL, 481.40);
        $this->pagar($order, 191.40);           // anticipo general (quote_id null)
        $this->pagar($order, 481.40, $b->id);   // pago asociado a B

        return [$order, $a, $b];
    }

    public function test_caso_observado_da_los_importes_esperados(): void
    {
        [$order] = $this->escenarioObservado();

        $e = $this->estado($order);

        $this->assertEquals(2221.40, $e['total_autorizado']);
        $this->assertEquals(672.80, $e['total_pagado']);
        $this->assertEquals(1548.60, $e['saldo_autorizado']);
    }

    public function test_saldo_exacto_se_permite_y_el_centavo_de_mas_se_rechaza(): void
    {
        [$order] = $this->escenarioObservado();

        $this->registrar($order, 1548.61)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 2);

        $this->registrar($order, 1548.60)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 3);
        $this->assertEquals(0.0, $this->estado($order)['saldo_autorizado']);
    }

    public function test_pago_asociado_por_encima_del_saldo_global_se_rechaza(): void
    {
        [$order, $a] = $this->escenarioObservado();

        $this->registrar($order, 1740.00, $a->id)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_pago_asociado_a_cotizacion_liquidada_se_rechaza(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $a = $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);
        $this->concepto($order, 'part', 400);
        $this->autorizar($order, Quote::KIND_ADICIONAL, 500.00);
        $this->pagar($order, 1000.00, $a->id); // A queda liquidada; B sigue debiendo

        $this->registrar($order, 10.00, $a->id)->assertSessionHasErrors('quote_id');
        $this->assertDatabaseCount('payments', 1);

        // Un anticipo general sigue permitido mientras haya saldo global
        $this->registrar($order, 10.00)->assertSessionHasNoErrors();
    }

    public function test_pago_asociado_superior_al_saldo_de_la_cotizacion_se_rechaza_aunque_haya_saldo_global(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $a = $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);
        $this->concepto($order, 'part', 2000);
        $this->autorizar($order, Quote::KIND_ADICIONAL, 2000.00);

        // Saldo global 3,000, pero la cotización A solo tiene 1,000 de saldo de referencia
        $this->registrar($order, 1500.00, $a->id)->assertSessionHasErrors('quote_id');
        $this->assertDatabaseCount('payments', 0);

        $this->registrar($order, 1000.00, $a->id)->assertSessionHasNoErrors();
    }

    public function test_pago_general_puede_llegar_hasta_el_saldo_global(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);

        $this->registrar($order, 1000.01)->assertSessionHasErrors('amount');
        $this->registrar($order, 1000.00)->assertSessionHasNoErrors();
        $this->assertEquals(0.0, $this->estado($order)['saldo_autorizado']);
    }

    public function test_cotizacion_de_otra_orden_no_se_admite(): void
    {
        $otra = $this->orden();
        $this->concepto($otra, 'labor', 500);
        $ajena = $this->autorizar($otra, Quote::KIND_PRINCIPAL, 500.00);

        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);

        $this->registrar($order, 10.00, $ajena->id)->assertSessionHasErrors('quote_id');
    }

    public function test_cotizacion_no_aprobada_no_admite_pagos_asociados(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);
        $enviada = $this->cotizacion($order, Quote::KIND_ADICIONAL, Quote::ENVIADA, 300.00);

        $this->registrar($order, 10.00, $enviada->id)->assertSessionHasErrors('quote_id');
    }

    public function test_dos_principales_aprobadas_no_se_pueden_obtener_por_aprobacion(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);
        $segunda = $this->cotizacion($order, Quote::KIND_PRINCIPAL, Quote::ENVIADA, 500.00);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Ya existe una cotización principal aprobada para esta orden.');
        $segunda->approve('presencial', 'Cliente de prueba');
    }

    public function test_no_se_puede_crear_principal_si_ya_hay_una_aprobada(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);

        $this->expectException(ValidationException::class);
        app(CreateQuoteAction::class)->execute($order->fresh(), null, Quote::KIND_PRINCIPAL);
    }

    public function test_principal_reemplazada_no_aumenta_la_autorizacion(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);
        $this->cotizacion($order, Quote::KIND_PRINCIPAL, Quote::REEMPLAZADA, 999.00);

        $this->assertEquals(1000.00, $this->estado($order)['total_autorizado']);
    }

    public function test_cotizacion_rechazada_no_aumenta_la_autorizacion(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);
        $this->cotizacion($order, Quote::KIND_ADICIONAL, Quote::RECHAZADA, 500.00);

        $this->assertEquals(1000.00, $this->estado($order)['total_autorizado']);
    }

    public function test_anticipo_previo_a_la_autorizacion_queda_como_excedente_sin_saldo(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->pagar($order, 1200.00); // sin autorización todavía: se admite
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);

        $e = $this->estado($order);
        $this->assertEquals(0.0, $e['saldo_autorizado']);
        $this->assertEquals(200.00, $e['sobrepago']);
        $this->assertEquals(1200.00, $e['anticipo_previo']);
    }

    public function test_pagos_de_una_orden_no_compensan_el_saldo_de_otra(): void
    {
        $uno = $this->orden();
        $this->concepto($uno, 'labor', 1000);
        $this->autorizar($uno, Quote::KIND_PRINCIPAL, 1000.00);

        $dos = $this->orden();
        $this->concepto($dos, 'labor', 800);
        $this->autorizar($dos, Quote::KIND_PRINCIPAL, 800.00);
        $this->pagar($dos, 500.00);

        $this->assertEquals(1000.00, $this->estado($uno)['saldo_autorizado']);
        $this->assertEquals(300.00, $this->estado($dos)['saldo_autorizado']);
    }

    public function test_trabajo_pendiente_de_autorizar_bloquea_la_entrega(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1000.00);
        $this->pagar($order, 1000.00);
        $this->concepto($order, 'part', 200); // sin autorizar

        $e = $this->estado($order);
        $this->assertEquals(0.0, $e['saldo_autorizado']);
        $this->assertGreaterThan(0, $e['pendiente_autorizacion']);
        $this->assertFalse($e['puede_entregarse']);
    }

    public function test_la_pagina_de_orden_recibe_el_estado_financiero_del_servidor(): void
    {
        [$order] = $this->escenarioObservado();

        $this->actingAs($this->admin())
            ->get(route('repair-orders.show', $order))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('estado_financiero.total_autorizado', 2221.4)
                ->where('estado_financiero.total_pagado', 672.8)
                ->where('estado_financiero.saldo_autorizado', 1548.6)
                ->has('estado_financiero.cotizaciones', 2)
                ->has('estado_financiero.cotizaciones.0', fn (AssertableInertia $q) => $q
                    ->hasAll(['id', 'folio', 'kind', 'status', 'total', 'pagado', 'saldo'])
                    ->etc())
            );
    }
}
