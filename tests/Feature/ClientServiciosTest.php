<?php

namespace Tests\Feature;

use App\Actions\Quotes\CreateQuoteAction;
use App\Actions\RepairOrders\EstadosFinancierosOrdenesAction;
use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B1.2.3: pestaña Servicios del expediente. Órdenes del cliente, paginadas, con estado financiero en lote.
 */
class ClientServiciosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function vehiculo(Client $cliente, array $attrs = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge(['client_id' => $cliente->id], $attrs));
    }

    private function orden(Client $cliente, array $attrs = []): RepairOrder
    {
        $vehiculo = $this->vehiculo($cliente);

        return RepairOrder::factory()->create(array_merge([
            'client_id'  => $cliente->id,
            'vehicle_id' => $vehiculo->id,
            'tax_rate'   => 16.0,
        ], $attrs));
    }

    private function concepto(RepairOrder $o, string $tipo, float $subtotal): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $o->id, 'type' => $tipo, 'description' => 'Concepto',
            'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal,
        ]);
    }

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

    /** Payload de la pestaña Servicios (página solicitada). */
    private function servicios(Client $cliente, string $query = ''): array
    {
        $response = $this->actingAs($this->admin())->get(route('clients.show', $cliente) . $query);
        $response->assertOk();

        return $response->inertiaProps('servicios');
    }

    public function test_solo_incluye_las_ordenes_del_cliente_actual(): void
    {
        $cliente = Client::factory()->create();
        $otro = Client::factory()->create();

        $propia1 = $this->orden($cliente);
        $propia2 = $this->orden($cliente);
        $ajena = $this->orden($otro);

        $ids = collect($this->servicios($cliente)['data'])->pluck('id')->sort()->values()->all();

        $this->assertSame([$propia1->id, $propia2->id], $ids);
        $this->assertNotContains($ajena->id, $ids);
    }

    public function test_ordena_por_created_at_y_no_por_id(): void
    {
        $cliente = Client::factory()->create();

        // Id menor pero creada antes: debe ir al final aunque tenga id menor
        $vieja = $this->orden($cliente, ['created_at' => now()->subDays(3)]);
        $reciente = $this->orden($cliente, ['created_at' => now()]);

        $ids = collect($this->servicios($cliente)['data'])->pluck('id')->all();

        $this->assertSame([$reciente->id, $vieja->id], $ids);
    }

    public function test_pagina_de_15_con_navegacion_y_query_string(): void
    {
        $cliente = Client::factory()->create();
        for ($i = 0; $i < 17; $i++) {
            $this->orden($cliente);
        }

        $pagina1 = $this->servicios($cliente);
        $pagina2 = $this->servicios($cliente, '?page=2');

        $this->assertCount(15, $pagina1['data']);
        $this->assertSame(17, $pagina1['total']);
        $this->assertSame(2, $pagina1['last_page']);
        $this->assertNotNull($pagina1['next_page_url']);
        $this->assertNull($pagina1['prev_page_url']);

        $this->assertCount(2, $pagina2['data']);
        $this->assertSame(2, $pagina2['current_page']);
        $this->assertNotNull($pagina2['prev_page_url']);
        $this->assertNull($pagina2['next_page_url']);
    }

    public function test_muestra_marca_modelo_año_y_placa_del_vehiculo(): void
    {
        $cliente = Client::factory()->create();
        $this->orden($cliente, [
            'vehicle_id' => $this->vehiculo($cliente, [
                'brand' => 'Nissan', 'model' => 'Versa', 'year' => 2020, 'plate' => 'ABC-123',
            ])->id,
        ]);

        $vehiculo = $this->servicios($cliente)['data'][0]['vehiculo'];

        $this->assertSame('Nissan', $vehiculo['marca']);
        $this->assertSame('Versa', $vehiculo['modelo']);
        $this->assertEquals(2020, $vehiculo['year']);
        $this->assertSame('ABC-123', $vehiculo['plate']);
    }

    public function test_mecanico_sin_asignar_no_falla(): void
    {
        $cliente = Client::factory()->create();
        $this->orden($cliente, ['mechanic_id' => null]);

        $fila = $this->servicios($cliente)['data'][0];

        $this->assertNull($fila['mecanico']);
    }

    public function test_muestra_el_mecanico_asignado(): void
    {
        $cliente = Client::factory()->create();
        $usuario = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $mecanico = Mechanic::create([
            'name'             => 'Mecánico de prueba',
            'user_id'          => $usuario->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);
        $this->orden($cliente, ['mechanic_id' => $mecanico->id]);

        $this->assertSame('Mecánico de prueba', $this->servicios($cliente)['data'][0]['mecanico']);
    }

    public function test_el_estado_financiero_coincide_con_el_lector_en_lote(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 1000);
        $q = $this->autorizar($o, 1000);
        $this->pagar($o, 400, $q->id);
        $this->concepto($o, 'labor', 500); // sin autorizar

        $fila = $this->servicios($cliente)['data'][0];
        $esperado = app(EstadosFinancierosOrdenesAction::class)->execute([$o->id])[$o->id];

        foreach (['total_tecnico', 'total_autorizado', 'total_pagado', 'saldo_autorizado', 'pendiente_autorizacion'] as $clave) {
            $this->assertEquals($esperado[$clave], $fila['financiero'][$clave], "Campo {$clave}");
        }
        $this->assertEquals(600.0, $fila['financiero']['saldo_autorizado']);
    }

    public function test_pendiente_de_autorizacion_se_refleja_con_iva_por_orden(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'labor', 100); // sin cotización: 100 + 16 % = 116

        $fila = $this->servicios($cliente)['data'][0];

        $this->assertEquals(116.0, $fila['financiero']['pendiente_autorizacion']);
        $this->assertEquals(0.0, $fila['financiero']['saldo_autorizado']);
    }

    public function test_el_excedente_no_altera_el_saldo_de_la_orden(): void
    {
        $cliente = Client::factory()->create();
        $o = $this->orden($cliente);
        $this->concepto($o, 'part', 1000);
        $q = $this->autorizar($o, 1000);
        $this->pagar($o, 1500, $q->id); // excedente 500

        $fila = $this->servicios($cliente)['data'][0];

        $this->assertEquals(0.0, $fila['financiero']['saldo_autorizado']);
        $this->assertEquals(1500.0, $fila['financiero']['total_pagado']);
    }

    public function test_mecanico_no_accede_a_la_pestana_servicios(): void
    {
        $cliente = Client::factory()->create();
        $this->orden($cliente);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_MECHANIC]))
            ->get(route('clients.show', $cliente))
            ->assertForbidden();
    }

    public function test_cliente_sin_ordenes_recibe_payload_vacio(): void
    {
        $cliente = Client::factory()->create();

        $servicios = $this->servicios($cliente);

        $this->assertSame([], $servicios['data']);
        $this->assertSame(0, $servicios['total']);
    }

    public function test_el_numero_de_consultas_no_crece_con_el_numero_de_ordenes_de_la_pagina(): void
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

        $this->orden($cliente);
        $conUna = $contar();

        for ($i = 0; $i < 14; $i++) {
            $o = $this->orden($cliente);
            $this->concepto($o, 'part', 100);
            $this->autorizar($o, 100);
        }
        $conQuince = $contar();

        $this->assertSame($conUna, $conQuince, 'La pestaña Servicios no debe introducir N+1');
    }
}
