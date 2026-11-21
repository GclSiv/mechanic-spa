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
 * B1.2.4: resumen de SOLO LECTURA de cotizaciones por servicio en la pestaña Servicios.
 */
class ClientCotizacionesServiciosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orden(Client $cliente, array $attrs = []): RepairOrder
    {
        $vehiculo = \App\Models\Vehicle::factory()->create(['client_id' => $cliente->id]);

        return RepairOrder::factory()->create(array_merge([
            'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id, 'tax_rate' => 16.0,
        ], $attrs));
    }

    private function concepto(RepairOrder $o, string $tipo, float $subtotal): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $o->id, 'type' => $tipo, 'description' => 'Concepto',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

    /** Crea la cotización, fija su snapshot y la aprueba. */
    private function autorizar(RepairOrder $o, float $monto, string $kind = Quote::KIND_PRINCIPAL): Quote
    {
        if ($kind === Quote::KIND_ADICIONAL) {
            $this->concepto($o, "labor", $monto); // el adicional solo cotiza conceptos nuevos
        }
        $q = app(CreateQuoteAction::class)->execute($o, null, $kind);
        $q->update(['total' => $monto]);
        $q->approve('presencial', 'Cliente de prueba');

        return $q->fresh();
    }

    private function servicios(Client $cliente, string $query = ''): array
    {
        $response = $this->actingAs($this->admin())->get(route('clients.show', $cliente) . $query);
        $response->assertOk();

        return $response->inertiaProps('servicios');
    }

    private function filaDe(array $servicios, RepairOrder $o): array
    {
        return collect($servicios['data'])->firstWhere('id', $o->id);
    }

    public function test_principal_aprobada_muestra_el_total_snapshot_aunque_los_conceptos_cambien(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 1000);
        $this->autorizar($o, 1000);
        $this->concepto($o, 'labor', 500); // conceptos actuales cambiados después de aprobar

        $cot = $this->filaDe($this->servicios($cliente), $o)['cotizaciones'];

        $this->assertCount(1, $cot);
        $this->assertSame('principal', $cot[0]['kind']);
        $this->assertSame('aprobada', $cot[0]['status']);
        $this->assertEquals(1000.0, $cot[0]['total']);
    }

    public function test_principal_y_adicionales_aparecen_clasificadas_y_ordenadas(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 1000);
        $this->autorizar($o, 1000, Quote::KIND_PRINCIPAL);
        $this->autorizar($o, 200, Quote::KIND_ADICIONAL);
        $this->autorizar($o, 300, Quote::KIND_ADICIONAL);

        $cot = $this->filaDe($this->servicios($cliente), $o)['cotizaciones'];

        $this->assertSame(['principal', 'adicional', 'adicional'], array_column($cot, 'kind'));
        $this->assertEquals([1000.0, 200.0, 300.0], array_column($cot, 'total'));
    }

    public function test_rechazada_es_visible_y_no_altera_el_total_autorizado(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 1000);
        $this->autorizar($o, 1000);

        $this->concepto($o, 'labor', 500);
        $rechazada = app(CreateQuoteAction::class)->execute($o, null, Quote::KIND_ADICIONAL);
        $rechazada->update(['total' => 500, 'status' => Quote::RECHAZADA, 'rejected_at' => now()]);

        $fila = $this->filaDe($this->servicios($cliente), $o);

        $estados = array_column($fila['cotizaciones'], 'status');
        $this->assertContains('rechazada', $estados);
        $this->assertEquals(1000.0, $fila['financiero']['total_autorizado']);
    }

    public function test_servicio_sin_cotizaciones_recibe_lista_vacia(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);

        $this->assertSame([], $this->filaDe($this->servicios($cliente), $o)['cotizaciones']);
    }

    public function test_no_aparecen_cotizaciones_de_ordenes_de_otro_cliente(): void
    {
        $cliente = Client::factory()->create();
        $otro = Client::factory()->create();

        $propia = $this->orden($cliente);
        $this->concepto($propia, 'part', 100);
        $this->autorizar($propia, 100);

        $ajena = $this->orden($otro);
        $this->concepto($ajena, 'part', 9999);
        $this->autorizar($ajena, 9999);

        $todas = collect($this->servicios($cliente)['data'])->flatMap(fn ($f) => $f['cotizaciones'])->pluck('total')->all();

        $this->assertEquals([100.0], $todas);
    }

    public function test_solo_se_envian_cotizaciones_de_los_servicios_de_la_pagina_actual(): void
    {
        $cliente = Client::factory()->create();

        // La orden más antigua queda en la página 2 (15 más recientes en la página 1)
        $antigua = $this->orden($cliente, ['created_at' => now()->subDays(30)]);
        $this->concepto($antigua, 'part', 700);
        $this->autorizar($antigua, 700);
        for ($i = 0; $i < 15; $i++) {
            $this->orden($cliente);
        }

        $pagina1 = $this->servicios($cliente);
        $pagina2 = $this->servicios($cliente, '?page=2');

        $totalesPagina1 = collect($pagina1['data'])->flatMap(fn ($f) => $f['cotizaciones'])->count();
        $this->assertSame(0, $totalesPagina1);

        $fila = $this->filaDe($pagina2, $antigua);
        $this->assertCount(1, $fila['cotizaciones']);
    }

    public function test_el_numero_de_consultas_no_crece_con_servicios_y_cotizaciones(): void
    {
        $cliente = Client::factory()->create();
        $this->servicios($cliente); // calentamiento

        $contar = function () use ($cliente): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin())->get(route('clients.show', $cliente))->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 100);
        $this->autorizar($o, 100);
        $conUno = $contar();

        for ($i = 0; $i < 14; $i++) {
            $x = $this->orden($cliente);
            $this->concepto($x, 'part', 100);
            $this->autorizar($x, 100);
            $this->autorizar($x, 50, Quote::KIND_ADICIONAL);
        }
        $conQuince = $contar();

        $this->assertSame($conUno, $conQuince, 'Las cotizaciones no deben generar consultas por servicio');
    }

    public function test_mechanic_no_accede_al_expediente_ni_a_sus_cotizaciones(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, "part", 100);
        $this->autorizar($o, 100);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_MECHANIC]))
            ->get(route('clients.show', $cliente))
            ->assertForbidden();
    }

    public function test_el_total_mostrado_es_quotes_total_aunque_haya_cambiado_el_subtotal_tecnico(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 1000);
        $this->autorizar($o, 1000);
        RepairOrderItem::query()->where('repair_order_id', $o->id)->update(['subtotal' => 9000, 'unit_price' => 9000]);

        $cot = $this->filaDe($this->servicios($cliente), $o)['cotizaciones'];

        $this->assertEquals(1000.0, $cot[0]['total']);
    }
}
