<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientStatus;
use App\Models\FuelType;
use App\Models\Recepcion;
use App\Models\Transmission;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\VehicleSubtype;
use App\Models\VehicleType;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Fase B · Catálogos de clasificación, marca/modelo inline y recepción con clasificación.
 */
class VehiculoFaseBCatalogosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    private function mecanico(): User
    {
        return User::factory()->create(['role' => User::ROLE_MECHANIC]);
    }

    private function catalogos(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
    }

    private function tipo(string $code): VehicleType
    {
        return VehicleType::where('code', $code)->firstOrFail();
    }

    /** Recepción nueva con vehículo nuevo (sin vehicle_id). */
    private function datosNuevaRecepcion(string $apellido, array $extra = []): array
    {
        ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);
        $marca = Brand::firstOrCreate(['name' => 'Nissan']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Versa']);

        return array_merge([
            'first_name'       => 'Cliente',
            'last_name'        => $apellido,
            'phone'            => '5551234567',
            'brand_id'         => $marca->id,
            'vehicle_model_id' => $modelo->id,
            'year'             => 2019,
            'plate'            => 'FB-' . strtoupper(substr($apellido, 0, 3)),
            'fuel_level'       => '1/2',
        ], $extra);
    }

    /** Recepción existente con vehículo ya registrado (para Editar). */
    private function recepcionExistente(array $vehiculo = []): Recepcion
    {
        $marca = Brand::firstOrCreate(['name' => 'Nissan']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Versa']);
        $cliente = Client::factory()->create();
        $auto = Vehicle::factory()->create(array_merge([
            'client_id' => $cliente->id,
            'brand_id'  => $marca->id,
            'model_id'  => $modelo->id,
            'brand'     => 'Nissan',
            'model'     => 'Versa',
            'vehicle_type_id' => null,
            'vehicle_subtype_id' => null,
            'fuel_type_id' => null,
            'transmission_id' => null,
        ], $vehiculo));

        return Recepcion::factory()->create([
            'client_id' => $cliente->id,
            'vehicle_id' => $auto->id,
            'miles' => '50000',
        ]);
    }

    private function datosEdicion(Recepcion $recepcion, array $extra = []): array
    {
        $cliente = $recepcion->client;

        return array_merge([
            'first_name' => $cliente->first_name,
            'last_name'  => $cliente->last_name,
            'country'    => 'MX',
            'fuel_level' => '1/2',
            'miles'      => '50000',
        ], $extra);
    }

    // ── 4-7 · Catálogos sembrados ──────────────────────────────────────────

    public function test_catalogos_sembrados_tienen_tipos_principales(): void
    {
        $this->catalogos();

        foreach (['automobile', 'suv', 'pickup', 'van', 'motorcycle', 'truck', 'tractor_truck', 'tractor', 'forklift', 'heavy_machinery', 'other'] as $code) {
            $this->assertDatabaseHas('vehicle_types', ['code' => $code]);
        }
        $this->assertSame(11, VehicleType::count());
    }

    public function test_seeder_es_idempotente(): void
    {
        $this->catalogos();
        $this->catalogos();

        $this->assertSame(11, VehicleType::count());
        $this->assertSame(8, VehicleSubtype::count());
        $this->assertSame(9, FuelType::count());
        $this->assertSame(8, Transmission::count());
    }

    public function test_hidrostatica_existe_en_transmisiones(): void
    {
        $this->catalogos();

        $this->assertDatabaseHas('transmissions', ['code' => 'hydrostatic', 'name' => 'Hidrostática']);
    }

    public function test_subtipos_de_motocicleta_pertenecen_a_motocicleta(): void
    {
        $this->catalogos();

        $motocicleta = $this->tipo('motorcycle');
        $subtipos = VehicleSubtype::all();

        $this->assertCount(8, $subtipos);
        $this->assertTrue($subtipos->every(fn ($s) => $s->vehicle_type_id === $motocicleta->id));
    }

    // ── 9 · Subtipo de otro tipo ───────────────────────────────────────────

    public function test_subtipo_de_otro_tipo_se_rechaza_al_crear_recepcion(): void
    {
        $this->catalogos();
        $subtipoMoto = VehicleSubtype::where('code', 'motorcycle_scooter')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNuevaRecepcion('Subtipo', [
                'vehicle_type_id' => $this->tipo('automobile')->id,
                'vehicle_subtype_id' => $subtipoMoto->id,
            ]))
            ->assertSessionHasErrors('vehicle_subtype_id');

        $this->assertDatabaseCount('recepcions', 0);
    }

    // ── 10-13 · Marca inline ───────────────────────────────────────────────

    public function test_admin_crea_marca_y_recibe_registro_para_seleccion(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('brands.store'), ['name' => 'Kenworth'])
            ->assertCreated()
            ->assertJsonPath('brand.name', 'Kenworth')
            ->assertJsonStructure(['brand' => ['id', 'name', 'vehicle_models']]);

        $this->assertDatabaseHas('brands', ['name' => 'Kenworth', 'name_key' => 'kenworth']);
    }

    public function test_owner_crea_marca(): void
    {
        $this->actingAs($this->owner())
            ->postJson(route('brands.store'), ['name' => 'Volvo'])
            ->assertCreated();

        $this->assertDatabaseHas('brands', ['name' => 'Volvo']);
    }

    public function test_mecanico_no_puede_crear_marca(): void
    {
        $this->actingAs($this->mecanico())
            ->postJson(route('brands.store'), ['name' => 'Hino'])
            ->assertForbidden();

        $this->assertDatabaseMissing('brands', ['name' => 'Hino']);
    }

    public function test_marca_duplicada_por_mayusculas_espacios_y_acentos_se_rechaza(): void
    {
        Brand::create(['name' => 'Súzuki']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('brands.store'), ['name' => '  SUZUKI  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson(route('brands.store'), ['name' => 'suzuki'])
            ->assertUnprocessable();

        $this->assertSame(1, Brand::count());
    }

    // ── 14-17 · Modelo inline ──────────────────────────────────────────────

    public function test_admin_crea_modelo_ligado_a_su_marca(): void
    {
        $marca = Brand::create(['name' => 'Mazda']);

        $this->actingAs($this->admin())
            ->postJson(route('brands.models.store', $marca), ['name' => 'CX-5'])
            ->assertCreated()
            ->assertJsonPath('model.brand_id', $marca->id);

        $modelo = VehicleModel::where('name', 'CX-5')->firstOrFail();
        $this->assertSame($marca->id, $modelo->brand_id);
        $this->assertSame('cx-5', $modelo->name_key);
    }

    public function test_modelo_duplicado_en_la_misma_marca_se_rechaza(): void
    {
        $marca = Brand::create(['name' => 'Mazda']);
        VehicleModel::create(['brand_id' => $marca->id, 'name' => 'Mazda 3']);

        $this->actingAs($this->admin())
            ->postJson(route('brands.models.store', $marca), ['name' => '  mazda   3 '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, VehicleModel::where('brand_id', $marca->id)->count());
    }

    public function test_mismo_nombre_de_modelo_en_marcas_distintas_se_permite(): void
    {
        $admin = $this->admin();
        $a = Brand::create(['name' => 'Ford']);
        $b = Brand::create(['name' => 'Mazda']);

        $this->actingAs($admin)->postJson(route('brands.models.store', $a), ['name' => 'Ranger'])->assertCreated();
        $this->actingAs($admin)->postJson(route('brands.models.store', $b), ['name' => 'Ranger'])->assertCreated();

        $this->assertSame(2, VehicleModel::where('name_key', 'ranger')->count());
    }

    // ── 18 · Vehículo histórico sin tipo ───────────────────────────────────

    public function test_vehiculo_historico_sin_tipo_carga_editar_y_aparece_en_historial(): void
    {
        $this->catalogos();
        $recepcion = $this->recepcionExistente();

        $this->actingAs($this->admin())
            ->get(route('recepcion.edit', $recepcion))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Recepciones/Edit')
                ->has('vehicleTypes', 11)
                ->has('fuelTypes', 9)
                ->has('transmissions', 8));

        $this->actingAs($this->admin())
            ->get(route('vehicles.history', $recepcion->vehicle))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Vehicles/History')
                ->where('vehicle.tipo', null));
    }

    // ── 19 · Nueva recepción guarda clasificación ──────────────────────────

    public function test_nueva_recepcion_guarda_tipo_combustible_y_transmision(): void
    {
        $this->catalogos();

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNuevaRecepcion('Clasif', [
                'vehicle_type_id' => $this->tipo('pickup')->id,
                'fuel_type_id' => FuelType::where('code', 'diesel')->value('id'),
                'transmission_id' => Transmission::where('code', 'hydrostatic')->value('id'),
            ]))
            ->assertSessionHasNoErrors();

        $vehiculo = Recepcion::latest('id')->firstOrFail()->vehicle;

        $this->assertSame($this->tipo('pickup')->id, $vehiculo->vehicle_type_id);
        $this->assertSame(FuelType::where('code', 'diesel')->value('id'), $vehiculo->fuel_type_id);
        $this->assertSame(Transmission::where('code', 'hydrostatic')->value('id'), $vehiculo->transmission_id);
        $this->assertNull($vehiculo->vehicle_subtype_id);
    }

    // ── 20 · Editar conserva los campos nuevos ─────────────────────────────

    public function test_editar_conserva_campos_de_clasificacion(): void
    {
        $this->catalogos();
        $transmision = Transmission::where('code', 'manual')->value('id');
        $recepcion = $this->recepcionExistente([
            'vehicle_type_id' => $this->tipo('automobile')->id,
            'transmission_id' => $transmision,
        ]);

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->datosEdicion($recepcion, [
                'vehicle_type_id' => $this->tipo('automobile')->id,
                'transmission_id' => $transmision,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->tipo('automobile')->id, $recepcion->vehicle->fresh()->vehicle_type_id);
        $this->assertSame($transmision, $recepcion->vehicle->fresh()->transmission_id);
    }

    // ── 21 · Cambio de tipo: limpia o rechaza subtipo incompatible ─────────

    public function test_cambiar_tipo_limpia_subtipo_de_motocicleta(): void
    {
        $this->catalogos();
        $subtipo = VehicleSubtype::where('code', 'motorcycle_sport')->value('id');
        $recepcion = $this->recepcionExistente([
            'vehicle_type_id' => $this->tipo('motorcycle')->id,
            'vehicle_subtype_id' => $subtipo,
        ]);

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->datosEdicion($recepcion, [
                'vehicle_type_id' => $this->tipo('automobile')->id,
                'vehicle_subtype_id' => null,
            ]))
            ->assertSessionHasNoErrors();

        $vehiculo = $recepcion->vehicle->fresh();
        $this->assertSame($this->tipo('automobile')->id, $vehiculo->vehicle_type_id);
        $this->assertNull($vehiculo->vehicle_subtype_id);
    }

    public function test_actualizar_con_subtipo_de_otro_tipo_se_rechaza(): void
    {
        $this->catalogos();
        $recepcion = $this->recepcionExistente([
            'vehicle_type_id' => $this->tipo('automobile')->id,
        ]);
        $subtipoMoto = VehicleSubtype::where('code', 'motorcycle_naked')->value('id');

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->datosEdicion($recepcion, [
                'vehicle_type_id' => $this->tipo('automobile')->id,
                'vehicle_subtype_id' => $subtipoMoto,
            ]))
            ->assertSessionHasErrors('vehicle_subtype_id');

        $this->assertNull($recepcion->vehicle->fresh()->vehicle_subtype_id);
    }

    // ── IDs de catálogo inexistentes ───────────────────────────────────────

    public function test_clasificacion_solo_acepta_ids_existentes(): void
    {
        $this->catalogos();

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), $this->datosNuevaRecepcion('Inexistente', [
                'vehicle_type_id' => 99999,
            ]))
            ->assertSessionHasErrors('vehicle_type_id');
    }
}
