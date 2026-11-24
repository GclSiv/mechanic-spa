<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * QA-FIX-1: marca y modelo se leen con Vehicle::displayBrand/displayModel (catálogo primero,
 * texto histórico después) en Dashboard, Taller y Expediente. Legacy y normalizado.
 */
class QaFix1MarcaModeloTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Vehículo legacy: solo texto en marca y modelo, sin FK de catálogo. */
    private function vehiculoLegacy(Client $cliente): Vehicle
    {
        return Vehicle::factory()->create([
            'client_id' => $cliente->id,
            'brand' => 'Ford', 'model' => 'Hilux', 'brand_id' => null, 'model_id' => null,
            'year' => 2013,
        ]);
    }

    /** Vehículo normalizado: FK al catálogo; el texto histórico debe quedar por detrás. */
    private function vehiculoNormalizado(Client $cliente): Vehicle
    {
        $marca = Brand::create(['name' => 'Toyota']);
        $modelo = VehicleModel::create(['name' => 'Corolla', 'brand_id' => $marca->id]);

        return Vehicle::factory()->create([
            'client_id' => $cliente->id,
            'brand' => 'Texto viejo', 'model' => 'Modelo viejo',
            'brand_id' => $marca->id, 'model_id' => $modelo->id,
            'year' => 2020,
        ]);
    }

    private function orden(Vehicle $vehiculo, Client $cliente): RepairOrder
    {
        $status = RepairOrderStatus::firstOrCreate(['slug' => 'recibido'], ['name' => 'Recibido', 'color_class' => 'bg-gray-500']);
        $recepcion = Recepcion::factory()->create(['client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id]);

        return RepairOrder::factory()->create([
            'client_id' => $cliente->id,
            'vehicle_id' => $vehiculo->id,
            'recepcion_id' => $recepcion->id,
            'status_id' => $status->id,
        ]);
    }

    // ---------- Dashboard ----------

    public function test_dashboard_muestra_marca_y_modelo_legacy(): void
    {
        $cliente = Client::factory()->create();
        $this->recepcionCon($this->vehiculoLegacy($cliente), $cliente);

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recentRecepcions.data.0.vehicle.display_brand', 'Ford')
                ->where('recentRecepcions.data.0.vehicle.display_model', 'Hilux'));
    }

    public function test_dashboard_prioriza_catalogo_sobre_texto_legacy(): void
    {
        $cliente = Client::factory()->create();
        $this->recepcionCon($this->vehiculoNormalizado($cliente), $cliente);

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recentRecepcions.data.0.vehicle.display_brand', 'Toyota')
                ->where('recentRecepcions.data.0.vehicle.display_model', 'Corolla'));
    }

    // ---------- Taller ----------

    public function test_taller_muestra_marca_y_modelo_legacy(): void
    {
        $cliente = Client::factory()->create();
        $this->orden($this->vehiculoLegacy($cliente), $cliente);

        $response = $this->actingAs($this->admin())->get(route('repair-orders.index'))->assertOk();

        $ordenes = collect($response->viewData('page')['props']['ordersByStatus'])->flatten(1);
        $this->assertSame('Ford', $ordenes->first()['recepcion']['vehicle']['display_brand']);
        $this->assertSame('Hilux', $ordenes->first()['recepcion']['vehicle']['display_model']);
    }

    public function test_taller_prioriza_catalogo_sobre_texto_legacy(): void
    {
        $cliente = Client::factory()->create();
        $this->orden($this->vehiculoNormalizado($cliente), $cliente);

        $response = $this->actingAs($this->admin())->get(route('repair-orders.index'))->assertOk();

        $ordenes = collect($response->viewData('page')['props']['ordersByStatus'])->flatten(1);
        $this->assertSame('Toyota', $ordenes->first()['recepcion']['vehicle']['display_brand']);
        $this->assertSame('Corolla', $ordenes->first()['recepcion']['vehicle']['display_model']);
    }

    // ---------- Expediente ----------

    public function test_expediente_muestra_marca_y_modelo_legacy(): void
    {
        $cliente = Client::factory()->create();
        $orden = $this->orden($this->vehiculoLegacy($cliente), $cliente);

        $this->actingAs($this->admin())->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recepcion.vehicle.display_brand', 'Ford')
                ->where('recepcion.vehicle.display_model', 'Hilux'));
    }

    public function test_expediente_prioriza_catalogo_sobre_texto_legacy(): void
    {
        $cliente = Client::factory()->create();
        $orden = $this->orden($this->vehiculoNormalizado($cliente), $cliente);

        $this->actingAs($this->admin())->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recepcion.vehicle.display_brand', 'Toyota')
                ->where('recepcion.vehicle.display_model', 'Corolla'));
    }

    // ---------- Recepciones ----------

    public function test_recepciones_muestra_marca_y_modelo_legacy(): void
    {
        $cliente = Client::factory()->create();
        $this->recepcionCon($this->vehiculoLegacy($cliente), $cliente);

        $this->actingAs($this->admin())->get(route('recepcion.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recepciones.data.0.vehicle.display_brand', 'Ford')
                ->where('recepciones.data.0.vehicle.display_model', 'Hilux'));
    }

    public function test_recepciones_prioriza_catalogo_sobre_texto_legacy(): void
    {
        $cliente = Client::factory()->create();
        $this->recepcionCon($this->vehiculoNormalizado($cliente), $cliente);

        $this->actingAs($this->admin())->get(route('recepcion.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recepciones.data.0.vehicle.display_brand', 'Toyota')
                ->where('recepciones.data.0.vehicle.display_model', 'Corolla'));
    }

    // ---------- Clientes: datos que alimentan las pestañas ----------

    public function test_clientes_expediente_conserva_vehiculos_legacy_y_catalogo_en_sus_pestanas(): void
    {
        $cliente = Client::factory()->create();
        $this->vehiculoLegacy($cliente);
        $this->vehiculoNormalizado($cliente);

        $this->actingAs($this->admin())->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vehiculos', 2)
                ->where('vehiculos.0.marca', fn ($marca) => in_array($marca, ['Ford', 'Toyota'], true))
                ->where('vehiculos.1.marca', fn ($marca) => in_array($marca, ['Ford', 'Toyota'], true))
                ->where('vehiculos', fn ($vehiculos) => collect($vehiculos)->pluck('marca')->sort()->values()->all() === ['Ford', 'Toyota']));
    }

    private function recepcionCon(Vehicle $vehiculo, Client $cliente): Recepcion
    {
        return Recepcion::factory()->create(['client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id]);
    }
}
