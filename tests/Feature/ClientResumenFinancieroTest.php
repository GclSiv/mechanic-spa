<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B1.2.2: resumen financiero del cliente derivado de TODAS sus órdenes, calculado en lote.
 */
class ClientResumenFinancieroTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orden(Client $cliente, float $tasa = 16): RepairOrder
    {
        return RepairOrder::factory()->create(['client_id' => $cliente->id, 'tax_rate' => $tasa]);
    }

    private function concepto(RepairOrder $o, string $tipo, float $subtotal): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $o->id, 'type' => $tipo, 'description' => 'Concepto',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    /** Cotización principal con snapshot autorizado, aprobada. */
    private function autorizar(RepairOrder $o, float $monto): Quote
    {
        $q = app(CreateQuoteAction::class)->execute($o, null, Quote::KIND_PRINCIPAL);
        $q->update(['total' => $monto]);
        $q->approve('presencial', 'Cliente de prueba');

        return $q->fresh();
    }

    private function pagar(RepairOrder $o, float $monto, ?int $quoteId = null): void
    {
        Payment::create([
            'repair_order_id' => $o->id, 'quote_id' => $quoteId, 'amount' => $monto, 'payment_method' => 'Cash',
        ]);
    }

    /** Payload financiero que entrega el controlador para el cliente. */
    private function resumen(Client $cliente): array
    {
        $response = $this->actingAs($this->admin())->get(route('clients.show', $cliente));
        $response->assertOk();

        return $response->inertiaProps('resumen_financiero');
    }

    public function test_el_resumen_suma_cada_orden_sin_compensar_saldos_ni_excedentes(): void
    {
        $cliente = Client::factory()->create();

        // Orden A: autorizado 1,000, pagado 1,500 → saldo 0, excedente 500
        $a = $this->orden($cliente);
        $this->concepto($a, 'part', 1000);
        $qa = $this->autorizar($a, 1000);
        $this->pagar($a, 1500, $qa->id);

        // Orden B: autorizado 1,000, pagado 0 → saldo 1,000
        $b = $this->orden($cliente);
        $this->concepto($b, 'part', 1000);
        $this->autorizar($b, 1000);

        // Orden C: sin autorizar, mano de obra 100 con IVA 16 % → pendiente 116
        $c = $this->orden($cliente);
        $this->concepto($c, 'labor', 100);

        $r = $this->resumen($cliente);

        $this->assertEquals(2000.0, $r['total_autorizado']);
        $this->assertEquals(1500.0, $r['total_pagado']);
        $this->assertEquals(1000.0, $r['saldo_autorizado']);   // SUM por orden, NO 2,000 − 1,500 = 500
        $this->assertEquals(116.0, $r['pendiente_autorizacion']);
        $this->assertEquals(500.0, $r['excedente_registrado']);
    }

    public function test_el_resumen_ignora_ordenes_de_otros_clientes(): void
    {
        $cliente = Client::factory()->create();
        $otro = Client::factory()->create();

        $ajena = $this->orden($otro);
        $this->concepto($ajena, 'part', 9999);
        $this->autorizar($ajena, 9999);

        $propia = $this->orden($cliente);
        $this->concepto($propia, 'part', 300);
        $this->autorizar($propia, 300);

        $r = $this->resumen($cliente);

        $this->assertEquals(300.0, $r['total_autorizado']);
        $this->assertEquals(300.0, $r['saldo_autorizado']);
    }

    public function test_cliente_sin_ordenes_recibe_ceros_numericos(): void
    {
        $cliente = Client::factory()->create();

        $r = $this->resumen($cliente);

        foreach (['total_autorizado', 'total_pagado', 'saldo_autorizado', 'pendiente_autorizacion', 'excedente_registrado'] as $clave) {
            $this->assertArrayHasKey($clave, $r);
            $this->assertIsNumeric($r[$clave]);
            $this->assertEquals(0, $r[$clave]);
        }
    }

    public function test_el_resumen_entrega_montos_numericos_no_cadenas(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 1000);
        $this->autorizar($o, 1000);

        $r = $this->resumen($cliente);

        foreach ($r as $clave => $valor) {
            $this->assertIsNumeric($valor, "El campo {$clave} debe ser numérico");
        }
    }

    public function test_el_numero_de_consultas_no_crece_con_el_numero_de_ordenes(): void
    {
        $cliente = Client::factory()->create();
        $this->resumen($cliente); // calentamiento: sesión, configuración y similares

        $consultas = function () use ($cliente): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin())->get(route('clients.show', $cliente))->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->orden($cliente);
        $conUna = $consultas();

        for ($i = 0; $i < 9; $i++) {
            $o = $this->orden($cliente);
            $this->concepto($o, 'part', 100);
            $this->autorizar($o, 100);
        }
        $conDiez = $consultas();

        $this->assertSame($conUna, $conDiez, 'El resumen no debe introducir N+1');
    }
}
