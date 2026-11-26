<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ClientStatus;
use App\Models\Client;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase A · Integridad de vehículos: placa y año opcionales, sin reutilizar vehículos por placa vacía.
 */
class VehiculoFaseAIntegridadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Datos mínimos de recepción de un cliente nuevo con vehículo nuevo (sin vehicle_id). */
    private function datosRecepcion(string $apellido, array $extra = []): array
    {
        // El controlador no envía client_status_id (default 1, FK): el catálogo real siembra ese estado.
        ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);

        $marca = Brand::firstOrCreate(['name' => 'Nissan']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Versa']);

        return array_merge([
            'first_name'       => 'Cliente',
            'phone'            => '5551234567',
            'last_name'        => $apellido,
            'brand_id'         => $marca->id,
            'vehicle_model_id' => $modelo->id,
            'year'             => 2019,
            'plate'            => 'ABC-123',
            'fuel_level'       => '1/2',
        ], $extra);
    }

    // ── Placa ─────────────────────────────────────────────────────────────

    public function test_varios_vehiculos_sin_placa_pueden_coexistir(): void
    {
        $cliente = Client::factory()->create();

        Vehicle::factory()->create(['client_id' => $cliente->id, 'plate' => null]);
        Vehicle::factory()->create(['client_id' => $cliente->id, 'plate' => null]);

        $this->assertSame(2, Vehicle::whereNull('plate')->count());
    }

    public function test_placa_no_nula_sigue_siendo_unica(): void
    {
        $cliente = Client::factory()->create();
        Vehicle::factory()->create(['client_id' => $cliente->id, 'plate' => 'XYZ-999']);

        $this->expectException(QueryException::class);

        Vehicle::factory()->create(['client_id' => $cliente->id, 'plate' => 'XYZ-999']);
    }

    public function test_placa_vacia_se_guarda_como_nula(): void
    {
        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosRecepcion('Uno', ['plate' => '']))
            ->assertSessionHasNoErrors();

        $vehiculo = Recepcion::latest('id')->firstOrFail()->vehicle;

        $this->assertNull($vehiculo->getRawOriginal('plate'));
    }

    public function test_dos_recepciones_sin_placa_crean_dos_vehiculos_distintos(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('recepcion.store'), $this->datosRecepcion('Primero', ['plate' => '']))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('recepcion.store'), $this->datosRecepcion('Segundo', ['plate' => null]))
            ->assertSessionHasNoErrors();

        $recepciones = Recepcion::orderBy('id')->get();

        $this->assertCount(2, $recepciones);
        $this->assertNotSame($recepciones[0]->vehicle_id, $recepciones[1]->vehicle_id);
        $this->assertSame(2, Vehicle::whereNull('plate')->count());
    }

    public function test_recepcion_sin_placa_no_reutiliza_vehiculo_sin_placa_de_otro_cliente(): void
    {
        $ajeno = Vehicle::factory()->create(['client_id' => Client::factory()->create()->id, 'plate' => null]);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosRecepcion('Nuevo', ['plate' => '']))
            ->assertSessionHasNoErrors();

        $recepcion = Recepcion::latest('id')->firstOrFail();

        $this->assertNotSame($ajeno->id, $recepcion->vehicle_id);
        $this->assertSame(
            Client::where('last_name', 'Nuevo')->firstOrFail()->id,
            $recepcion->vehicle->client_id,
        );
    }

    // ── Año ───────────────────────────────────────────────────────────────

    public function test_vehiculo_con_anio_nulo_persiste(): void
    {
        $vehiculo = Vehicle::factory()->create(['year' => null]);

        $this->assertNull($vehiculo->fresh()->year);
    }

    public function test_maquinaria_se_registra_sin_anio(): void
    {
        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosRecepcion('Maquina', ['year' => null]))
            ->assertSessionHasNoErrors();

        $vehiculo = Recepcion::latest('id')->firstOrFail()->vehicle;

        $this->assertNull($vehiculo->fresh()->year);
    }
}
