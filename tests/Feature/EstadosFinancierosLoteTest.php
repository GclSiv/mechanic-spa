<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Support\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B1.2.1: lector en lote. Debe dar exactamente el mismo estado que EstadoFinancieroAction (A2)
 * y usar un número de consultas que no crece con el número de órdenes.
 */
class EstadosFinancierosLoteTest extends TestCase
{
    use RefreshDatabase;

    private function orden(?float $tasa = 16): RepairOrder
    {
        return RepairOrder::factory()->create(['tax_rate' => $tasa]);
    }

    private function concepto(RepairOrder $o, string $tipo, float $subtotal): RepairOrderItem
    {
        return RepairOrderItem::create([
            'repair_order_id' => $o->id, 'type' => $tipo, 'description' => 'Concepto',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    /** Crea la cotización con sus conceptos actuales, fija su snapshot y la aprueba. */
    private function autorizar(RepairOrder $o, string $kind, float $monto): Quote
    {
        $q = app(CreateQuoteAction::class)->execute($o, null, $kind);
        $q->update(['total' => $monto]); // snapshot autorizado
        $q->approve('presencial', 'Cliente de prueba');

        return $q->fresh();
    }

    private function pagar(RepairOrder $o, float $monto, ?int $quoteId = null, ?string $cuando = null): Payment
    {
        $p = Payment::create([
            'repair_order_id' => $o->id, 'quote_id' => $quoteId, 'amount' => $monto, 'payment_method' => 'Cash',
        ]);

        if ($cuando !== null) {
            $p->forceFill(['created_at' => $cuando])->save();
        }

        return $p;
    }

    /** Lector en lote y acción individual deben coincidir para la misma orden. */
    private function estadoLote(RepairOrder $o): array
    {
        return app(EstadosFinancierosOrdenesAction::class)->execute([$o->id])[$o->id];
    }

    private function estadoIndividual(RepairOrder $o): array
    {
        return app(EstadoFinancieroAction::class)->execute($o->fresh());
    }

    private function assertParidad(RepairOrder $o): array
    {
        $lote = $this->estadoLote($o);
        $this->assertEquals($this->estadoIndividual($o), $lote, "Paridad fallida en la orden {$o->id}");

        return $lote;
    }

    // ── Escenarios A–I ──────────────────────────────────────────────────────

    public function test_escenario_a_principal_aprobada_con_pago_parcial(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 1000);
        $q = $this->autorizar($o, Quote::KIND_PRINCIPAL, 1000);
        $this->pagar($o, 400, $q->id);

        $e = $this->assertParidad($o);

        $this->assertEquals(1000.0, $e['total_autorizado']);
        $this->assertEquals(400.0, $e['total_pagado']);
        $this->assertEquals(600.0, $e['saldo_autorizado']);
        $this->assertEquals(0.0, $e['pendiente_autorizacion']);
        $this->assertEquals(0.0, $e['sobrepago']);
        $this->assertFalse($e['liquidado']);
    }

    public function test_escenario_b_principal_aprobada_con_adicional_pendiente(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 1000);
        $this->autorizar($o, Quote::KIND_PRINCIPAL, 1000);
        $this->concepto($o, 'labor', 500); // sin autorizar: mano de obra con IVA 16 %

        $e = $this->assertParidad($o);

        $this->assertEquals(1000.0, $e['total_autorizado']);
        $this->assertEquals(1000.0, $e['saldo_autorizado']);
        $this->assertEquals(580.0, $e['pendiente_autorizacion']); // 500 + 80 de IVA
        $this->assertFalse($e['liquidado']);
    }

    public function test_escenario_c_principal_mas_adicional_aprobada(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 1000);
        $this->autorizar($o, Quote::KIND_PRINCIPAL, 1000);
        $this->concepto($o, 'labor', 500);
        $this->autorizar($o, Quote::KIND_ADICIONAL, 580);

        $e = $this->assertParidad($o);

        $this->assertEquals(1580.0, $e['total_autorizado']);
        $this->assertEquals(1580.0, $e['saldo_autorizado']);
        $this->assertEquals(0.0, $e['pendiente_autorizacion']);
    }

    public function test_escenario_d_orden_liquidada(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 1000);
        $q = $this->autorizar($o, Quote::KIND_PRINCIPAL, 1000);
        $this->pagar($o, 1000, $q->id);

        $e = $this->assertParidad($o);

        $this->assertEquals(0.0, $e['saldo_autorizado']);
        $this->assertEquals(0.0, $e['pendiente_autorizacion']);
        $this->assertTrue($e['liquidado']);
        $this->assertTrue($e['puede_entregarse']);
    }

    public function test_escenario_e_anticipo_antes_de_autorizar(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 1000);
        $this->pagar($o, 300); // anticipo general, sin cotización
        $this->autorizar($o, Quote::KIND_PRINCIPAL, 1000);

        $e = $this->assertParidad($o);

        $this->assertEquals(300.0, $e['anticipo_previo']);
        $this->assertEquals(300.0, $e['total_pagado']);
        $this->assertEquals(700.0, $e['saldo_autorizado']);
        $this->assertEquals(0.0, $e['sobrepago']);
    }

    public function test_escenario_f_anticipo_mayor_que_la_autorizacion_posterior(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 1000);
        $this->pagar($o, 1200);
        $this->autorizar($o, Quote::KIND_PRINCIPAL, 1000);

        $e = $this->assertParidad($o);

        $this->assertEquals(1200.0, $e['anticipo_previo']);
        $this->assertEquals(0.0, $e['saldo_autorizado']);
        $this->assertEquals(200.0, $e['sobrepago']);
        $this->assertTrue($e['liquidado']);
    }

    public function test_escenario_g_cotizacion_rechazada(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 1000);
        $q = app(CreateQuoteAction::class)->execute($o, null, Quote::KIND_PRINCIPAL);
        $q->update(['total' => 1000]);
        $q->reject('Cliente no acepta');

        $e = $this->assertParidad($o);

        // Rechazada: no autoriza ni cancela el trabajo técnico; el concepto sigue pendiente
        $this->assertEquals(0.0, $e['total_autorizado']);
        $this->assertEquals(0.0, $e['saldo_autorizado']);
        $this->assertEquals(1160.0, $e['pendiente_autorizacion']); // 1000 + 16 %
        $this->assertSame(Quote::RECHAZADA, $e['cotizaciones'][0]['status']);
    }

    public function test_escenario_h_conceptos_tecnicos_sin_autorizacion(): void
    {
        $o = $this->orden();
        $this->concepto($o, 'part', 800);
        $this->concepto($o, 'labor', 200);

        $e = $this->assertParidad($o);

        $this->assertEquals(0.0, $e['total_autorizado']);
        $this->assertEquals(1160.0, $e['pendiente_autorizacion']);
        $this->assertEquals(1160.0, $e['total_tecnico']);
        $this->assertEquals(0.0, $e['saldo_autorizado']);
        $this->assertFalse($e['liquidado']);
    }

    public function test_escenario_i_varias_ordenes_en_un_mismo_lote(): void
    {
        $a = $this->orden();
        $this->concepto($a, 'part', 1000);
        $qa = $this->autorizar($a, Quote::KIND_PRINCIPAL, 1000);
        $this->pagar($a, 1000, $qa->id);

        $b = $this->orden();
        $this->concepto($b, 'part', 800);
        $this->concepto($b, 'labor', 200);

        $c = $this->orden();
        $this->concepto($c, 'part', 1000);
        $qc = app(CreateQuoteAction::class)->execute($c, null, Quote::KIND_PRINCIPAL);
        $qc->update(['total' => 1000]);
        $qc->reject(null);

        $d = $this->orden();
        $this->concepto($d, 'part', 1000);
        $qd = $this->autorizar($d, Quote::KIND_PRINCIPAL, 1000);
        $this->pagar($d, 300, $qd->id);

        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([$a, $b, $c, $d]);

        $this->assertCount(4, $lote);
        foreach ([$a, $b, $c, $d] as $o) {
            $this->assertEquals($this->estadoIndividual($o), $lote[$o->id], "Paridad fallida en la orden {$o->id}");
        }
        $this->assertEquals(0.0, $lote[$a->id]['saldo_autorizado']);
        $this->assertEquals(1160.0, $lote[$b->id]['pendiente_autorizacion']);
        $this->assertEquals(1160.0, $lote[$c->id]['pendiente_autorizacion']);
        $this->assertEquals(700.0, $lote[$d->id]['saldo_autorizado']);
    }

    // ── No compensación entre órdenes ──────────────────────────────────────

    public function test_excedente_de_una_orden_no_compensa_el_saldo_de_otra(): void
    {
        $a = $this->orden();
        $this->concepto($a, 'part', 1000);
        $qa = $this->autorizar($a, Quote::KIND_PRINCIPAL, 1000);
        $this->pagar($a, 1500, $qa->id);

        $b = $this->orden();
        $this->concepto($b, 'part', 1000);
        $this->autorizar($b, Quote::KIND_PRINCIPAL, 1000);

        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([$a->id, $b->id]);

        $this->assertEquals(0.0, $lote[$a->id]['saldo_autorizado']);
        $this->assertEquals(500.0, $lote[$a->id]['sobrepago']);
        $this->assertEquals(1000.0, $lote[$b->id]['saldo_autorizado']);
        $this->assertEquals(0.0, $lote[$b->id]['sobrepago']);

        // El saldo del cliente es la suma por orden: 1000, nunca 500
        $this->assertEquals(1000.0, round(array_sum(array_column($lote, 'saldo_autorizado')), 2));
    }

    // ── IVA por orden y tasa ───────────────────────────────────────────────

    public function test_iva_se_calcula_por_orden_con_su_propia_tasa(): void
    {
        $general = $this->orden(16);
        $this->concepto($general, 'labor', 500);

        $fronteriza = $this->orden(8);
        $this->concepto($fronteriza, 'labor', 500);

        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([$general, $fronteriza]);

        $this->assertEquals(16.0, $lote[$general->id]['tasa']);
        $this->assertEquals(580.0, $lote[$general->id]['total_tecnico']);
        $this->assertEquals(8.0, $lote[$fronteriza->id]['tasa']);
        $this->assertEquals(540.0, $lote[$fronteriza->id]['total_tecnico']);
        $this->assertEquals(
            TaxCalculator::compute(0.0, 500.0, 8.0)['total'],
            $lote[$fronteriza->id]['total_tecnico']
        );
    }

    public function test_orden_sin_tasa_usa_la_configuracion_una_sola_vez_para_el_lote(): void
    {
        $sinTasa = $this->orden(null);
        $this->concepto($sinTasa, 'labor', 500);

        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([$sinTasa]);

        $this->assertEquals(16.0, $lote[$sinTasa->id]['tasa']);
        $this->assertEquals(580.0, $lote[$sinTasa->id]['total_tecnico']);
    }

    // ── Número de consultas ────────────────────────────────────────────────

    private function consultasDelLote(array $ids): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(EstadosFinancierosOrdenesAction::class)->execute($ids);

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    public function test_numero_de_consultas_no_crece_con_el_numero_de_ordenes(): void
    {
        $ids = [];
        $crear = function (int $n) use (&$ids) {
            for ($i = 0; $i < $n; $i++) {
                $o = $this->orden();
                $this->concepto($o, 'part', 1000);
                $this->concepto($o, 'labor', 200);
                $this->pagar($o, 100);
                $ids[] = $o->id;
            }
        };

        $crear(1);
        $consultas1 = $this->consultasDelLote($ids);

        $crear(9);
        $consultas10 = $this->consultasDelLote($ids);

        $crear(90);
        $consultas100 = $this->consultasDelLote($ids);

        $this->assertSame($consultas1, $consultas10, 'Consultas 1 vs 10 órdenes');
        $this->assertSame($consultas10, $consultas100, 'Consultas 10 vs 100 órdenes');
        // Con IDs: órdenes, conceptos, cotizaciones y pagos. Siempre 4, sin importar el número de órdenes.
        $this->assertSame(4, $consultas1);
        $this->assertSame(4, $consultas100);
    }
}
