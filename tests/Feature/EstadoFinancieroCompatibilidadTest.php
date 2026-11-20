<?php

namespace Tests\Feature;

use App\Actions\RepairOrders\CalculateOrderTotalsAction;
use App\Actions\RepairOrders\EstadoFinancieroAction;
use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Compatibilidad de la fachada individual EstadoFinancieroAction::execute(RepairOrder) tras el refactor
 * y contrato del lector en lote para IDs inexistentes y para instancias.
 */
class EstadoFinancieroCompatibilidadTest extends TestCase
{
    use RefreshDatabase;

    private function ordenConManoDeObra(float $tasa = 16.0): RepairOrder
    {
        $orden = RepairOrder::factory()->create(['tax_rate' => $tasa]);

        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Mano de obra',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
        ]);

        return $orden;
    }

    // ── 1. execute() respeta la instancia recibida ─────────────────────────

    public function test_execute_usa_la_tasa_en_memoria_de_la_instancia_no_la_de_la_base(): void
    {
        $orden = $this->ordenConManoDeObra(16.0);

        // Modificada en memoria, sin guardar
        $orden->tax_rate = 8.0;

        $estado = app(EstadoFinancieroAction::class)->execute($orden);

        // Semántica previa: calcular() leía $repairOrder->tax_rate de la instancia
        $previo = app(CalculateOrderTotalsAction::class)->calcular($orden);

        $this->assertSame(8.0, $estado['tasa']);
        $this->assertEquals($previo['tax_rate'], $estado['tasa']);
        $this->assertEquals($previo['total'], $estado['total_tecnico']);   // 500 + 8 % = 540
        $this->assertEquals(540.0, $estado['total_tecnico']);
        $this->assertEquals(540.0, $estado['pendiente_autorizacion']);

        // La base no cambió: la diferencia solo existe en memoria
        $this->assertSame(16.0, (float) $orden->fresh()->tax_rate);
    }

    public function test_el_lote_por_id_lee_la_tasa_de_la_base_mientras_la_fachada_usa_la_instancia(): void
    {
        $orden = $this->ordenConManoDeObra(16.0);
        $orden->tax_rate = 8.0; // solo en memoria

        $porId = app(EstadosFinancierosOrdenesAction::class)->execute([$orden->id])[$orden->id];
        $porInstancia = app(EstadoFinancieroAction::class)->execute($orden);

        $this->assertSame(16.0, $porId['tasa']);            // base de datos
        $this->assertSame(8.0, $porInstancia['tasa']);      // instancia, como antes del refactor
    }

    public function test_instancia_con_tasa_nula_en_memoria_usa_la_configuracion(): void
    {
        $orden = $this->ordenConManoDeObra(16.0);
        $orden->tax_rate = null;

        $estado = app(EstadoFinancieroAction::class)->execute($orden);
        $previo = app(CalculateOrderTotalsAction::class)->calcular($orden);

        $this->assertSame(16.0, $estado['tasa']);
        $this->assertEquals($previo['total'], $estado['total_tecnico']);
        $this->assertEquals(580.0, $estado['total_tecnico']);
    }

    public function test_instancia_e_id_del_mismo_registro_prevalecen_como_instancia(): void
    {
        $orden = $this->ordenConManoDeObra(16.0);
        $orden->tax_rate = 8.0;

        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([$orden, $orden->id]);

        $this->assertCount(1, $lote);
        $this->assertSame(8.0, $lote[$orden->id]['tasa']);
    }

    // ── 2. Contrato para IDs inexistentes ──────────────────────────────────

    public function test_lote_omite_los_ids_inexistentes_sin_lanzar_excepcion(): void
    {
        $existente = $this->ordenConManoDeObra();

        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([$existente->id, 999999]);

        $this->assertSame([$existente->id], array_keys($lote));
        $this->assertArrayNotHasKey(999999, $lote);
    }

    public function test_lote_solo_con_ids_inexistentes_devuelve_arreglo_vacio(): void
    {
        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([999998, 999999]);

        $this->assertSame([], $lote);
    }

    public function test_lote_vacio_devuelve_arreglo_vacio(): void
    {
        $this->assertSame([], app(EstadosFinancierosOrdenesAction::class)->execute([]));
    }

    public function test_lote_conserva_el_orden_de_entrada_y_no_duplica(): void
    {
        $a = $this->ordenConManoDeObra();
        $b = $this->ordenConManoDeObra();

        $lote = app(EstadosFinancierosOrdenesAction::class)->execute([$b->id, $a->id, $b->id]);

        $this->assertSame([$b->id, $a->id], array_keys($lote));
    }

    // ── 3. Instancia sin registro en base ─────────────────────────────────

    public function test_instancia_sin_registro_en_base_se_calcula_sin_conceptos_como_antes(): void
    {
        $fantasma = new RepairOrder();
        $fantasma->id = 987654;
        $fantasma->tax_rate = 16.0;

        // La fachada no lanza excepción nueva: la instancia recibida siempre tiene resultado
        $estado = app(EstadoFinancieroAction::class)->execute($fantasma);

        $this->assertSame(16.0, $estado['tasa']);
        $this->assertSame(0.0, $estado['total_tecnico']);
        $this->assertSame(0.0, $estado['total_autorizado']);
        $this->assertSame(0.0, $estado['total_pagado']);
    }

    // ── 4. Consultas del lote por instancias ──────────────────────────────

    public function test_lote_por_instancias_no_consulta_las_ordenes(): void
    {
        $ordenes = collect(range(1, 10))->map(fn () => $this->ordenConManoDeObra());

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(EstadosFinancierosOrdenesAction::class)->execute($ordenes);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Conceptos, cotizaciones y pagos. Sin consulta de órdenes porque ya llegaron como instancias.
        $this->assertSame(3, $consultas);
    }
}
