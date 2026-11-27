<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Actions\Reports\IngresosPeriodoAction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FIN-002: el reporte mensual de INGRESOS es dinero cobrado: pagos válidos registrados en el periodo.
 * Nunca monto autorizado, total técnico ni conceptos pendientes/rechazados.
 */
class ReporteIngresosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orden(string $slug = 'recibido'): RepairOrder
    {
        $status = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500']
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

    private function concepto(RepairOrder $orden, float $subtotal): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Trabajo',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    /** Cotización principal aprobada con importe autorizado fijo (snapshot A2). */
    private function autorizar(RepairOrder $orden, float $monto, string $kind = Quote::KIND_PRINCIPAL): Quote
    {
        $quote = app(CreateQuoteAction::class)->execute($orden, null, $kind);
        $quote->update(['total' => $monto]);
        $quote->approve('presencial', 'Cliente de prueba');

        return $quote->fresh();
    }

    /** Pago con fecha de registro controlada (created_at), como se usa en el reporte. */
    private function pago(RepairOrder $orden, float $monto, Carbon $fecha, ?Quote $cotizacion = null): Payment
    {
        $pago = $orden->payments()->create([
            'amount' => $monto,
            'payment_method' => 'Cash',
            'quote_id' => $cotizacion?->id,
        ]);
        $pago->forceFill(['created_at' => $fecha])->saveQuietly();

        return $pago;
    }

    private function ingresos(int $mes, int $anio): array
    {
        return app(IngresosPeriodoAction::class)->execute($mes, $anio);
    }

    // ── FIN-002: reproducción y reglas base ───────────────────────────────

    public function test_fin_002_conceptos_no_autorizados_no_cuentan_como_ingreso(): void
    {
        $orden = $this->orden('reparado');
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);
        $this->concepto($orden, 500); // técnico, pendiente de autorizar: nunca cobrado

        $this->pago($orden, 1000, Carbon::create(2026, 11, 15, 12), $orden->quotes()->first());

        $datos = $this->ingresos(11, 2026);

        // Reproducción: el total técnico de la orden (lo que sumaba el reporte anterior) supera lo cobrado.
        $tecnico = app(CalculateOrderTotalsAction::class)->execute($orden->fresh())['total'];
        $this->assertGreaterThan(1000, $tecnico, 'El total técnico incluye el concepto no autorizado.');

        $this->assertEquals(1000.0, $datos['totals']['ingresos']);
        $this->assertNotEquals($tecnico, $datos['totals']['ingresos']);
    }

    public function test_fin_002_pago_valido_cuenta_como_ingreso_del_periodo(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 800);
        $this->autorizar($orden, 800);
        $this->pago($orden, 300, Carbon::create(2026, 11, 10, 12), $orden->quotes()->first());

        $datos = $this->ingresos(11, 2026);

        $this->assertEquals(300.0, $datos['totals']['ingresos']);
        $this->assertSame(1, $datos['totals']['pagos']);
        $this->assertSame(1, $datos['totals']['ordenes']);
        $this->assertSame(300.0, (float) $datos['rows'][0]['monto']);
    }

    public function test_fin_002_pago_general_con_quote_id_nulo_cuenta(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 800);
        $this->autorizar($orden, 800);
        $this->pago($orden, 200, Carbon::create(2026, 11, 12, 12)); // quote_id = null

        $datos = $this->ingresos(11, 2026);

        $this->assertEquals(200.0, $datos['totals']['ingresos']);
        $this->assertEquals(200.0, $datos['totals']['generales']);
        $this->assertSame('general', $datos['rows'][0]['tipo']);
    }

    public function test_fin_002_anticipo_antes_de_autorizar_cuenta_en_el_mes_de_registro(): void
    {
        $orden = $this->orden();
        $this->pago($orden, 500, Carbon::create(2026, 11, 30, 12)); // anticipo sin cotización

        // Cotización aprobada en diciembre: no mueve el ingreso de noviembre.
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);

        $noviembre = $this->ingresos(11, 2026);
        $diciembre = $this->ingresos(12, 2026);

        $this->assertEquals(500.0, $noviembre['totals']['ingresos']);
        $this->assertEquals(0.0, $diciembre['totals']['ingresos']);
    }

    public function test_fin_002_pago_de_otro_mes_no_entra_al_periodo(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);
        $this->pago($orden, 400, Carbon::create(2026, 11, 20, 12), $orden->quotes()->first());
        $this->pago($orden, 600, Carbon::create(2026, 12, 2, 12), $orden->quotes()->first());

        $this->assertEquals(400.0, $this->ingresos(11, 2026)['totals']['ingresos']);
        $this->assertEquals(600.0, $this->ingresos(12, 2026)['totals']['ingresos']);
    }

    public function test_fin_002_el_periodo_usa_la_fecha_de_pago_no_la_de_entrega(): void
    {
        $orden = $this->orden('entregado');
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);
        $orden->forceFill(['updated_at' => Carbon::create(2026, 11, 28, 12)])->saveQuietly();
        $this->pago($orden, 1000, Carbon::create(2026, 12, 3, 12), $orden->quotes()->first());

        $this->assertEquals(0.0, $this->ingresos(11, 2026)['totals']['ingresos'], 'Entregada en noviembre no implica ingreso de noviembre.');
        $this->assertEquals(1000.0, $this->ingresos(12, 2026)['totals']['ingresos']);
    }

    public function test_fin_002_pago_eliminado_no_se_cuenta(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 1000);
        $this->autorizar($orden, 1000);
        $pago = $this->pago($orden, 700, Carbon::create(2026, 11, 5, 12), $orden->quotes()->first());
        $pago->delete();

        $this->assertEquals(0.0, $this->ingresos(11, 2026)['totals']['ingresos']);
    }

    public function test_fin_002_la_suma_corresponde_a_pagos_reales(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 1500);
        $this->autorizar($orden, 1500);
        $this->pago($orden, 500, Carbon::create(2026, 11, 3, 12), $orden->quotes()->first());
        $this->pago($orden, 200, Carbon::create(2026, 11, 9, 12));

        $esperado = (float) Payment::whereYear('created_at', 2026)->whereMonth('created_at', 11)->sum('amount');
        $datos = $this->ingresos(11, 2026);

        $this->assertEquals($esperado, $datos['totals']['ingresos']);
        $this->assertEquals(700.0, $datos['totals']['ingresos']);
        $this->assertCount(2, $datos['rows']);
        $this->assertEquals(700.0, round($datos['rows']->sum('monto'), 2));
    }

    public function test_el_pdf_se_genera_con_el_periodo_solicitado(): void
    {
        $orden = $this->orden();
        $this->concepto($orden, 500);
        $this->autorizar($orden, 500);
        $this->pago($orden, 500, Carbon::create(2026, 11, 14, 12), $orden->quotes()->first());

        $respuesta = $this->actingAs($this->admin())
            ->get(route('reports.pdf', ['month' => 11, 'year' => 2026]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('content-type'));
    }

    // ── Caso A2 exacto de regresión de caja ───────────────────────────────

    public function test_caso_a2_1500_con_pagos_500_y_700_luego_300_y_sobrepago_rechazado(): void
    {
        $admin = $this->admin();
        $orden = $this->orden();
        $this->concepto($orden, 1500);
        $this->autorizar($orden, 1500);

        $this->actingAs($admin)->post(route('payments.store', $orden), ['amount' => 500, 'payment_method' => 'Cash'])->assertRedirect();
        $this->actingAs($admin)->post(route('payments.store', $orden), ['amount' => 700, 'payment_method' => 'Cash'])->assertRedirect();

        $estado = app(EstadoFinancieroAction::class)->execute($orden->fresh());
        $this->assertEquals(1500.0, $estado['total_autorizado']);
        $this->assertEquals(1200.0, $estado['total_pagado']);
        $this->assertEquals(300.0, $estado['saldo_autorizado']);
        $this->assertEquals(0.0, $estado['sobrepago']);

        $this->actingAs($admin)->post(route('payments.store', $orden), ['amount' => 300, 'payment_method' => 'Cash'])->assertRedirect();

        $estado = app(EstadoFinancieroAction::class)->execute($orden->fresh());
        $this->assertEquals(1500.0, $estado['total_pagado']);
        $this->assertEquals(0.0, $estado['saldo_autorizado']);
        $this->assertEquals(0.0, $estado['sobrepago']);

        $this->actingAs($admin)
            ->post(route('payments.store', $orden), ['amount' => 1, 'payment_method' => 'Cash'])
            ->assertSessionHasErrors('amount');

        $this->assertEquals(1500.0, app(EstadoFinancieroAction::class)->execute($orden->fresh())['total_pagado']);
    }
}
