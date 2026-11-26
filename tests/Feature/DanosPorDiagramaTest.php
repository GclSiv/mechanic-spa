<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ClientStatus;
use App\Models\Client;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use App\Support\DiagramaDano;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Diagrama de daños por tipo de unidad: resolución por tipo, snapshot y validación de zonas.
 */
class DanosPorDiagramaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function tipo(string $code): VehicleType
    {
        return VehicleType::where('code', $code)->firstOrFail();
    }

    private function datosNueva(string $apellido, string $tipoCode, array $danos = [], array $extra = []): array
    {
        ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);
        $marca = Brand::firstOrCreate(['name' => 'Genérica']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Modelo']);

        return array_merge([
            'first_name' => 'Cliente',
            'last_name' => $apellido,
            'phone' => '5551234567',
            'brand_id' => $marca->id,
            'vehicle_model_id' => $modelo->id,
            'year' => 2020,
            'plate' => 'DD-' . strtoupper(substr($apellido, 0, 4)),
            'fuel_level' => '1/2',
            'vehicle_type_id' => $this->tipo($tipoCode)->id,
            'damages' => $danos,
        ], $extra);
    }

    private function registrar(string $apellido, string $tipoCode, array $danos): Recepcion
    {
        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNueva($apellido, $tipoCode, $danos))
            ->assertSessionHasNoErrors();

        return Recepcion::latest('id')->firstOrFail();
    }

    private function danio(string $zona): array
    {
        return ['zone' => $zona, 'severity' => 'leve', 'note' => ''];
    }

    // ── 1-4 · Tipo de unidad => diagrama ──────────────────────────────────

    public function test_motocicleta_registra_diagrama_motorcycle(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $r = $this->registrar('Moto', 'motorcycle', [$this->danio('moto_tanque')]);

        $this->assertSame(DiagramaDano::MOTORCYCLE, $r->damage_diagram);
    }

    public function test_montacargas_registra_diagrama_forklift(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $r = $this->registrar('Montacargas', 'forklift', [$this->danio('fork_mastil')]);

        $this->assertSame(DiagramaDano::FORKLIFT, $r->damage_diagram);
    }

    public function test_camion_y_tractocamion_registran_diagrama_truck(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $camion = $this->registrar('Camion', 'truck', [$this->danio('camion_frontal')]);
        $tractocamion = $this->registrar('Tractocamion', 'tractor_truck', [$this->danio('camion_techo')]);

        $this->assertSame(DiagramaDano::TRUCK, $camion->damage_diagram);
        $this->assertSame(DiagramaDano::TRUCK, $tractocamion->damage_diagram);
    }

    public function test_tractor_y_maquinaria_pesada_registran_diagrama_heavy_generic(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $tractor = $this->registrar('Tractor', 'tractor', [$this->danio('maq_frontal')]);
        $maquinaria = $this->registrar('Maquina', 'heavy_machinery', [$this->danio('maq_cabina')]);

        $this->assertSame(DiagramaDano::HEAVY, $tractor->damage_diagram);
        $this->assertSame(DiagramaDano::HEAVY, $maquinaria->damage_diagram);
    }

    // ── 5 · Automóvil conserva car ────────────────────────────────────────

    public function test_automovil_conserva_diagrama_car(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $r = $this->registrar('Auto', 'automobile', [$this->danio('capo')]);

        $this->assertSame(DiagramaDano::CAR, $r->damage_diagram);
    }

    public function test_sin_danos_no_hay_snapshot(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $r = $this->registrar('Limpio', 'motorcycle', []);

        $this->assertNull($r->damage_diagram);
    }

    // ── 6 · Histórico sin snapshot ────────────────────────────────────────

    public function test_historico_con_danos_legacy_y_sin_snapshot_usa_car(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
        $vehiculo = Vehicle::factory()->create(['vehicle_type_id' => $this->tipo('motorcycle')->id]);
        $r = Recepcion::factory()->create([
            'client_id' => $vehiculo->client_id,
            'vehicle_id' => $vehiculo->id,
            'damages' => [['zone' => 'capo', 'severity' => 'leve', 'note' => '']],
            'damage_diagram' => null,
        ]);

        $this->assertSame(DiagramaDano::CAR, DiagramaDano::deRecepcion($r, $vehiculo->vehicleType));
    }

    public function test_historico_sin_danos_y_sin_snapshot_usa_tipo_actual_sin_inferir_de_danos(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
        $vehiculo = Vehicle::factory()->create(['vehicle_type_id' => $this->tipo('forklift')->id]);
        $r = Recepcion::factory()->create([
            'client_id' => $vehiculo->client_id,
            'vehicle_id' => $vehiculo->id,
            'damages' => null,
            'damage_diagram' => null,
        ]);

        $this->assertSame(DiagramaDano::FORKLIFT, DiagramaDano::deRecepcion($r, $vehiculo->vehicleType));
    }

    // ── 7 · Cambiar tipo: zonas incompatibles se rechazan ─────────────────

    public function test_zona_de_automovil_no_se_acepta_en_motocicleta(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNueva('Mix', 'motorcycle', [$this->danio('capo')]))
            ->assertSessionHasErrors('damages.0.zone');

        $this->assertDatabaseCount('recepcions', 0);
    }

    public function test_zona_de_motocicleta_no_se_acepta_en_camion(): void
    {
        $this->seed(VehicleCatalogSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNueva('Mix2', 'truck', [$this->danio('moto_tanque')]))
            ->assertSessionHasErrors('damages.0.zone');
    }

    // ── 8 · Daños guardados conservan su diagrama ─────────────────────────

    public function test_danos_guardados_conservan_su_diagrama_aunque_cambie_el_tipo_del_vehiculo(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
        $r = $this->registrar('Conserva', 'motorcycle', [$this->danio('moto_rueda_trasera')]);

        $r->vehicle->update(['vehicle_type_id' => $this->tipo('automobile')->id]);

        $this->assertSame(DiagramaDano::MOTORCYCLE, $r->fresh()->damage_diagram);
    }

    // ── 9 · Editar usa el snapshot, no el tipo actual ─────────────────────

    public function test_editar_muestra_el_snapshot_aunque_el_tipo_actual_difiera(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
        $r = $this->registrar('Snap', 'motorcycle', [$this->danio('moto_frontal')]);
        $r->vehicle->update(['vehicle_type_id' => $this->tipo('automobile')->id]);

        $props = $this->actingAs($this->admin())
            ->get(route('recepcion.edit', $r))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(DiagramaDano::MOTORCYCLE, $props['damageDiagram']);
    }

    public function test_editar_historico_con_danos_legacy_usa_car(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
        $vehiculo = Vehicle::factory()->create(['vehicle_type_id' => $this->tipo('motorcycle')->id]);
        $r = Recepcion::factory()->create([
            'client_id' => $vehiculo->client_id,
            'vehicle_id' => $vehiculo->id,
            'damages' => [['zone' => 'puerta_fi', 'severity' => 'medio', 'note' => '']],
            'damage_diagram' => null,
        ]);

        $props = $this->actingAs($this->admin())
            ->get(route('recepcion.edit', $r))
            ->viewData('page')['props'];

        $this->assertSame(DiagramaDano::CAR, $props['damageDiagram']);
    }

    // ── Paridad PHP ↔ JS: mismas claves de zona en ambos lados ────────────

    public function test_claves_de_zona_coinciden_con_el_diagrama_de_javascript(): void
    {
        $js = file_get_contents(resource_path('js/Components/DamageDiagrams/diagramas.js'));

        foreach (DiagramaDano::ZONAS as $diagrama => $zonas) {
            foreach ($zonas as $zona) {
                $this->assertStringContainsString("Z('{$zona}'", $js, "Falta en JS: {$diagrama}/{$zona}");
            }
        }
    }

    public function test_no_hay_claves_repetidas_entre_diagramas(): void
    {
        $todas = array_merge(...array_values(DiagramaDano::ZONAS));

        $this->assertCount(count($todas), array_unique($todas));
    }
}
