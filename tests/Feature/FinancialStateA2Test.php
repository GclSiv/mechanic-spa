<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Support\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A2: estado financiero autorizado.
 *
 * El cliente solo adeuda lo autorizado en cotizaciones aprobadas. Los escenarios usan los
 * importes autorizados del brief (5,000 / 5,800) fijados como snapshot de la cotización, porque
 * con IVA 16 % en centavos no existe un subtotal que dé exactamente 5,000 de total.
 * El concepto no autorizado (800 de refacción) sí pasa por el IVA real: su total es 928.
 */
class FinancialStateA2Test extends TestCase
{
    use RefreshDatabase;

    private const PENDIENTE_800 = 928.00; // 800 de refacción + IVA 16 %

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

    /** Crea la cotización del tipo indicado con sus conceptos actuales, fija su total autorizado y la aprueba. */
    private function autorizar(RepairOrder $order, string $kind, float $monto): Quote
    {
        $quote = app(CreateQuoteAction::class)->execute($order, null, $kind);
        $quote->update(['total' => $monto]); // snapshot del importe autorizado del brief
        $quote->approve('presencial', 'Cliente de prueba');

        return $quote->fresh();
    }

    private function estado(RepairOrder $order): array
    {
        return app(EstadoFinancieroAction::class)->execute($order->fresh());
    }

    private function pagar(User $admin, RepairOrder $order, float $monto, ?int $quoteId = null)
    {
        return $this->actingAs($admin)->post(route('payments.store', $order), array_filter([
            'amount' => $monto, 'payment_method' => 'Cash', 'quote_id' => $quoteId,
        ], fn ($v) => $v !== null));
    }

    private function estadoEntregado(): RepairOrderStatus
    {
        return RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'entregado'], ['name' => 'Entregado', 'color_class' => 'bg-gray-500']
        );
    }

    // ── Escenarios de referencia ────────────────────────────────────────────

    public function test_escenario_a_saldo_autorizado_es_3000_no_3800(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->concepto($order, 'part', 800);

        $this->pagar($admin, $order, 2000)->assertSessionHasNoErrors(); // sin quote_id: cuenta en el global

        $e = $this->estado($order);
        $this->assertSame(5000.0, $e['total_autorizado']);
        $this->assertSame(2000.0, $e['total_pagado']);
        $this->assertSame(3000.0, $e['saldo_autorizado']);
        $this->assertSame(self::PENDIENTE_800, $e['pendiente_autorizacion']);
        $this->assertFalse($e['liquidado']);
        $this->assertFalse($e['puede_entregarse']);
    }

    public function test_escenario_b_autorizado_pagado_deja_pendiente_y_no_entrega(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->concepto($order, 'part', 800);
        $this->pagar($admin, $order, 5000)->assertSessionHasNoErrors();

        $e = $this->estado($order);
        $this->assertSame(0.0, $e['saldo_autorizado']);
        $this->assertSame(self::PENDIENTE_800, $e['pendiente_autorizacion']);

        $this->actingAs($admin)
            ->patch(route('repair-orders.status.update', $order), ['status_id' => $this->estadoEntregado()->id])
            ->assertSessionHas('error');

        $this->assertNotSame($this->estadoEntregado()->id, $order->fresh()->status_id);
    }

    public function test_escenario_c_adicional_aprobada_sube_autorizado_a_5800_y_saldo_a_800(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->concepto($order, 'part', 800);
        $this->pagar($admin, $order, 5000)->assertSessionHasNoErrors();

        $this->autorizar($order, Quote::KIND_ADICIONAL, 800);

        $e = $this->estado($order);
        $this->assertSame(5800.0, $e['total_autorizado']);
        $this->assertSame(5000.0, $e['total_pagado']);
        $this->assertSame(800.0, $e['saldo_autorizado']);
        $this->assertSame(0.0, $e['pendiente_autorizacion']);
    }

    public function test_escenario_d_liquidado_permite_entregar(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->concepto($order, 'part', 800);
        $this->pagar($admin, $order, 5000);
        $this->autorizar($order, Quote::KIND_ADICIONAL, 800);
        $this->pagar($admin, $order, 800)->assertSessionHasNoErrors();

        $e = $this->estado($order);
        $this->assertSame(5800.0, $e['total_autorizado']);
        $this->assertSame(5800.0, $e['total_pagado']);
        $this->assertSame(0.0, $e['saldo_autorizado']);
        $this->assertSame(0.0, $e['pendiente_autorizacion']);
        $this->assertTrue($e['puede_entregarse']);

        $this->actingAs($admin)
            ->patch(route('repair-orders.status.update', $order), ['status_id' => $this->estadoEntregado()->id])
            ->assertRedirect();

        $this->assertSame($this->estadoEntregado()->id, $order->fresh()->status_id);
        $this->assertTrue((bool) $order->fresh()->is_paid);
    }

    // ── Pagos y sobrepago ───────────────────────────────────────────────────

    public function test_pago_mayor_al_saldo_autorizado_se_rechaza(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->pagar($admin, $order, 4000)->assertSessionHasNoErrors();

        $this->pagar($admin, $order, 1001)->assertSessionHasErrors('amount');

        $this->assertSame(1, Payment::where('repair_order_id', $order->id)->count());
        $this->assertSame(1000.0, $this->estado($order)['saldo_autorizado']);
    }

    public function test_pago_sin_quote_id_reduce_el_saldo_global(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $this->pagar($admin, $order, 1500)->assertSessionHasNoErrors();

        $this->assertNull(Payment::where('repair_order_id', $order->id)->first()->quote_id);
        $this->assertSame(3500.0, $this->estado($order)['saldo_autorizado']);
    }

    public function test_anticipo_sin_cotizacion_se_registra_con_quote_id_nulo(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);

        $this->pagar($admin, $order, 1000)->assertSessionHasNoErrors();

        $pago = Payment::where('repair_order_id', $order->id)->firstOrFail();
        $this->assertNull($pago->quote_id);
        $this->assertSame($order->id, $pago->repair_order_id);
    }

    public function test_anticipo_previo_no_produce_saldo_negativo_ni_sobrepago(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->pagar($admin, $order, 1000)->assertSessionHasNoErrors();

        $e = $this->estado($order);
        $this->assertSame(0.0, $e['total_autorizado']);
        $this->assertSame(1000.0, $e['total_pagado']);
        $this->assertSame(0.0, $e['saldo_autorizado']);
        $this->assertSame(0.0, $e['sobrepago']);
        $this->assertSame(1000.0, $e['anticipo_previo']);
    }

    public function test_anticipo_previo_reduce_el_saldo_autorizado_tras_aprobar_sin_duplicarse(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->pagar($admin, $order, 1000);

        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $e = $this->estado($order);
        $this->assertSame(5000.0, $e['total_autorizado']);
        $this->assertSame(1000.0, $e['total_pagado']);
        $this->assertSame(4000.0, $e['saldo_autorizado']);
        $this->assertSame(1, Payment::where('repair_order_id', $order->id)->count());
    }

    public function test_pagos_posteriores_no_superan_lo_autorizado_con_anticipo_previo(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->pagar($admin, $order, 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $this->pagar($admin, $order, 4000)->assertSessionHasNoErrors();
        $this->pagar($admin, $order, 1)->assertSessionHasErrors('amount');

        $this->assertSame(0.0, $this->estado($order)['saldo_autorizado']);
    }

    public function test_quote_id_de_la_misma_orden_y_aprobada_es_valido(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $quote = $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $this->pagar($admin, $order, 500, $quote->id)->assertSessionHasNoErrors();

        $this->assertSame($quote->id, Payment::where('repair_order_id', $order->id)->value('quote_id'));
        $this->assertSame(4500.0, $this->estado($order)['saldo_autorizado']);
    }

    public function test_anticipo_excedente_antes_de_autorizar_no_se_borra_y_bloquea_nuevos_pagos(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->pagar($admin, $order, 2000)->assertSessionHasNoErrors();
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 1500);

        $e = $this->estado($order);
        $this->assertSame(1500.0, $e['total_autorizado']);
        $this->assertSame(2000.0, $e['total_pagado']);
        $this->assertSame(0.0, $e['saldo_autorizado']);
        $this->assertSame(500.0, $e['sobrepago']);
        $this->assertSame(2000.0, $e['anticipo_previo']);

        // El anticipo histórico se conserva y no se admite ningún pago que amplíe el excedente
        $this->pagar($admin, $order, 1)->assertSessionHasErrors('amount');
        $this->assertSame(1, Payment::where('repair_order_id', $order->id)->count());
    }

    public function test_aprobada_usa_exactamente_el_snapshot_aunque_los_conceptos_cambien(): void
    {
        $order = $this->orden();
        $refaccion = $this->concepto($order, 'part', 800);

        // Cálculo técnico de 800 + IVA = 928; el cliente autoriza 800 totales
        $quote = app(CreateQuoteAction::class)->execute($order);
        $this->assertSame(928.0, (float) $quote->total);
        $quote->update(['total' => 800]);
        $quote->approve('presencial', 'Cliente de prueba');

        $this->assertSame(800.0, $this->estado($order)['total_autorizado']);

        // Cambiar el concepto después de aprobar no recalcula la cotización aprobada
        $refaccion->update(['subtotal' => 5000, 'unit_price' => 5000]);

        $e = $this->estado($order);
        $this->assertSame(800.0, $e['total_autorizado']);
        $this->assertSame(800.0, (float) $quote->fresh()->total);
    }

    public function test_trabajo_pendiente_usa_el_calculo_fiscal_hasta_ser_autorizado(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'part', 800);

        $esperado = TaxCalculator::compute(800.0, 0.0, 16.0)['total'];
        $this->assertSame(928.0, $esperado);

        $e = $this->estado($order);
        $this->assertSame(0.0, $e['total_autorizado']);
        $this->assertSame($esperado, $e['pendiente_autorizacion']);
        $this->assertSame(0.0, $e['saldo_autorizado']);
    }

    public function test_quote_id_de_otra_orden_o_no_aprobada_se_rechaza(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $otra = $this->orden();
        $this->concepto($otra, 'labor', 100);
        $cotizacionAjena = app(CreateQuoteAction::class)->execute($otra);

        $this->pagar($admin, $order, 100, $cotizacionAjena->id)->assertSessionHasErrors('quote_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_eliminar_pago_vuelve_a_mostrar_el_saldo(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->pagar($admin, $order, 2000);
        $pago = Payment::where('repair_order_id', $order->id)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('payments.destroy', [$order, $pago]))
            ->assertRedirect();

        $this->assertSame(5000.0, $this->estado($order)['saldo_autorizado']);
    }

    // ── Conceptos autorizados ───────────────────────────────────────────────

    public function test_concepto_autorizado_no_se_elimina(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $refaccion = $this->concepto($order, 'part', 500);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 580);

        $this->actingAs($admin)
            ->delete(route('repair-orders.items.destroy', [$order, $refaccion]))
            ->assertSessionHasErrors('item');

        $this->assertNotNull($refaccion->fresh());
        $this->assertSame(580.0, $this->estado($order)['total_autorizado']);
    }

    public function test_concepto_no_autorizado_si_se_puede_eliminar(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $pendiente = $this->concepto($order, 'labor', 50);

        $this->actingAs($admin)
            ->delete(route('repair-orders.items.destroy', [$order, $pendiente]))
            ->assertSessionHasNoErrors();

        $this->assertNull($pendiente->fresh());
    }

    public function test_agregar_trabajo_tecnico_no_aumenta_el_autorizado(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $this->concepto($order, 'part', 800);
        $e = $this->estado($order);

        $this->assertSame(5000.0, $e['total_autorizado']);
        $this->assertSame(self::PENDIENTE_800, $e['pendiente_autorizacion']);
    }

    // ── Cotizaciones ────────────────────────────────────────────────────────

    public function test_no_se_puede_crear_segunda_principal_con_una_aprobada(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $this->expectException(ValidationException::class);
        app(CreateQuoteAction::class)->execute($order);
    }

    public function test_no_se_puede_aprobar_una_segunda_principal(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        // Borrador principal creado sin pasar por la acción (simula un borrador anterior)
        $borrador = Quote::create([
            'repair_order_id' => $order->id, 'version' => 2, 'kind' => Quote::KIND_PRINCIPAL,
            'folio' => 'OR-X-C2', 'status' => Quote::BORRADOR, 'tax_rate' => 16,
            'parts_subtotal' => 0, 'labor_subtotal' => 0, 'subtotal' => 0, 'tax' => 0, 'total' => 0,
            'valid_until' => now()->addDays(15)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $borrador->approve('presencial', 'Cliente de prueba');
    }

    public function test_adicional_no_incluye_conceptos_ya_autorizados(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        // Sin conceptos nuevos no hay adicional
        try {
            app(CreateQuoteAction::class)->execute($order, null, Quote::KIND_ADICIONAL);
            $this->fail('Una adicional sin conceptos nuevos debe rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        $nuevo = $this->concepto($order, 'part', 800);
        $adicional = app(CreateQuoteAction::class)->execute($order, null, Quote::KIND_ADICIONAL);

        $this->assertSame(1, QuoteItem::where('quote_id', $adicional->id)->count());
        $this->assertSame($nuevo->id, QuoteItem::where('quote_id', $adicional->id)->value('order_item_id'));
    }

    public function test_aprobar_adicional_marca_sus_conceptos_como_autorizados(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $nuevo = $this->concepto($order, 'part', 800);

        $this->assertSame(self::PENDIENTE_800, $this->estado($order)['pendiente_autorizacion']);

        $this->autorizar($order, Quote::KIND_ADICIONAL, 800);

        $this->assertNotNull($nuevo->fresh()->billed_quote_id);
        $this->assertSame(0.0, $this->estado($order)['pendiente_autorizacion']);
    }

    public function test_cotizacion_rechazada_no_suma_al_autorizado(): void
    {
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->concepto($order, 'part', 800);

        $adicional = app(CreateQuoteAction::class)->execute($order, null, Quote::KIND_ADICIONAL);
        $adicional->update(['total' => 800]);
        $adicional->reject('El cliente no autorizó');

        $e = $this->estado($order);
        $this->assertSame(5000.0, $e['total_autorizado']);
        $this->assertSame(self::PENDIENTE_800, $e['pendiente_autorizacion']);
    }

    // ── Fuente única y is_paid como caché ──────────────────────────────────

    public function test_is_paid_desactualizado_no_altera_las_decisiones(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->concepto($order, 'part', 800);
        $this->pagar($admin, $order, 5000);

        // Caché falsa: marcada como liquidada aunque hay pendiente de autorización
        $order->fresh()->update(['is_paid' => true]);

        $e = $this->estado($order);
        $this->assertSame(0.0, $e['saldo_autorizado']);
        $this->assertFalse($e['puede_entregarse']);

        $this->actingAs($admin)
            ->patch(route('repair-orders.status.update', $order), ['status_id' => $this->estadoEntregado()->id])
            ->assertSessionHas('error');
    }

    public function test_sincronizar_corrige_is_paid_al_cambiar_el_pendiente(): void
    {
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);
        $this->pagar($admin, $order, 5000);
        $this->assertTrue((bool) $order->fresh()->is_paid);

        // Agregar trabajo no autorizado lo vuelve pendiente
        $this->actingAs($admin)
            ->post(route('repair-orders.items.store', $order), [
                'type' => 'labor', 'description' => 'Extra', 'quantity' => 1, 'unit_price' => 100,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $order->fresh()->is_paid);
    }

    public function test_dos_pagos_simultaneos_no_superan_el_saldo_autorizado(): void
    {
        // La concurrencia real requiere MySQL (SQLite no aplica lockForUpdate). Aquí se verifica
        // secuencialmente que el segundo pago se rechaza cuando el primero agotó el saldo.
        $admin = $this->admin();
        $order = $this->orden();
        $this->concepto($order, 'labor', 1000);
        $this->autorizar($order, Quote::KIND_PRINCIPAL, 5000);

        $this->pagar($admin, $order, 5000)->assertSessionHasNoErrors();
        $this->pagar($admin, $order, 1)->assertSessionHasErrors('amount');

        $this->assertSame(0.0, $this->estado($order)['saldo_autorizado']);
    }
}
