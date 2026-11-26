<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Recepción V2: la búsqueda de órdenes encuentra por el valor de cualquier identificador
 * (VIN legado, VIN completo, últimos 8/10, serie, otro), sin cambiar permisos ni alcance.
 */
class RecepcionV2BusquedaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Orden con su recepción y vehículo. Los atributos del vehículo se pasan tal cual. */
    private function ordenConVehiculo(array $vehiculo): RepairOrder
    {
        $status = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => 'recibido'],
            ['name' => 'recibido', 'color_class' => 'bg-gray-500']
        );

        $cliente = Client::factory()->create();
        $vehiculoModelo = Vehicle::factory()->create(array_merge(['client_id' => $cliente->id], $vehiculo));
        $recepcion = Recepcion::create([
            'client_id' => $cliente->id,
            'vehicle_id' => $vehiculoModelo->id,
            'fuel_level' => '1/2',
        ]);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'tax_rate' => 16,
            'status_id' => $status->id,
        ]);
    }

    /** Ids de las órdenes que devuelve la búsqueda. */
    private function encontradas(string $termino): array
    {
        $ids = [];

        $this->actingAs($this->admin())
            ->get(route('repair-orders.lista', ['buscar' => $termino]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['ordenes']['data'])->pluck('id')->all();
            });

        return $ids;
    }

    public function test_encuentra_orden_por_vin_legado(): void
    {
        $orden = $this->ordenConVehiculo(['vin' => 'JN1TANT30U0000001']); // sin identificador nuevo

        $this->assertSame([$orden->id], $this->encontradas('JN1TANT30U0000001'));
    }

    public function test_encuentra_orden_por_vin_completo_nuevo(): void
    {
        $orden = $this->ordenConVehiculo([
            'vin' => 'JN1TANT30U0000002', 'identifier_type' => 'vin', 'identifier_value' => 'JN1TANT30U0000002',
        ]);

        $this->assertSame([$orden->id], $this->encontradas('JN1TANT30U0000002'));
    }

    public function test_encuentra_orden_por_ultimos_8(): void
    {
        $orden = $this->ordenConVehiculo(['identifier_type' => 'vin_last_8', 'identifier_value' => 'AB12CD34']);

        $this->assertSame([$orden->id], $this->encontradas('AB12CD34'));
    }

    public function test_encuentra_orden_por_ultimos_10(): void
    {
        $orden = $this->ordenConVehiculo(['identifier_type' => 'vin_last_10', 'identifier_value' => 'ABC1234567']);

        $this->assertSame([$orden->id], $this->encontradas('ABC1234567'));
    }

    public function test_encuentra_orden_por_numero_de_serie(): void
    {
        $orden = $this->ordenConVehiculo(['identifier_type' => 'serial', 'identifier_value' => 'SN-889-XYZ']);

        $this->assertSame([$orden->id], $this->encontradas('SN-889-XYZ'));
    }

    public function test_encuentra_orden_por_identificador_otro(): void
    {
        $orden = $this->ordenConVehiculo(['identifier_type' => 'other', 'identifier_value' => 'PLACA-VIEJA-7']);

        $this->assertSame([$orden->id], $this->encontradas('PLACA-VIEJA-7'));
    }

    public function test_no_encuentra_por_nombre_del_tipo_sino_por_valor(): void
    {
        $this->ordenConVehiculo(['identifier_type' => 'vin_last_8', 'identifier_value' => 'AB12CD34']);

        $this->assertSame([], $this->encontradas('vin_last_8'), 'La búsqueda es por valor, nunca por el tipo.');
    }

    public function test_termino_sin_coincidencia_no_devuelve_ordenes(): void
    {
        $this->ordenConVehiculo(['identifier_type' => 'serial', 'identifier_value' => 'SN-1']);

        $this->assertSame([], $this->encontradas('NO-EXISTE-999'));
    }

    public function test_cada_termino_devuelve_solo_su_orden(): void
    {
        $serie = $this->ordenConVehiculo(['identifier_type' => 'serial', 'identifier_value' => 'SN-AAA']);
        $otra = $this->ordenConVehiculo(['identifier_type' => 'serial', 'identifier_value' => 'SN-BBB']);

        $this->assertSame([$serie->id], $this->encontradas('SN-AAA'));
        $this->assertSame([$otra->id], $this->encontradas('SN-BBB'));
    }

    public function test_mecanico_sigue_sin_acceso_al_listado_de_ordenes(): void
    {
        $this->ordenConVehiculo(['identifier_type' => 'serial', 'identifier_value' => 'SN-SEG']);
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)
            ->get(route('repair-orders.lista', ['buscar' => 'SN-SEG']))
            ->assertForbidden();
    }
}
