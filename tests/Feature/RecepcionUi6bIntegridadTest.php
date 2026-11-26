<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientFiscalProfile;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UI-6B: integridad Cliente → Vehículo en store/update, compatibilidad legacy en Editar y RFC coherente.
 */
class RecepcionUi6bIntegridadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Vehículo sin FK de marca/modelo: solo texto, como en registros anteriores al catálogo. */
    private function vehiculoLegacy(Client $cliente, array $extra = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'client_id' => $cliente->id,
            'brand' => 'Ford',
            'model' => 'Hilux',
            'brand_id' => null,
            'model_id' => null,
            'year' => 2013,
            'plate' => 'NAR-430',
            'vin' => 'SUFHELZXJXNXRTOKL',
            'engine' => null,
        ], $extra));
    }

    /** Vehículo normalizado con marca y modelo consistentes. */
    private function vehiculoNormalizado(Client $cliente): array
    {
        $marca = Brand::create(['name' => 'Nissan']);
        $modelo = VehicleModel::create(['brand_id' => $marca->id, 'name' => 'Versa']);
        $vehiculo = Vehicle::factory()->create([
            'client_id' => $cliente->id,
            'brand_id' => $marca->id,
            'model_id' => $modelo->id,
            'brand' => 'Nissan',
            'model' => 'Versa',
            'year' => 2019,
            'vin' => 'JN1TANT30U0000001', // 17 caracteres válidos (sin I, O ni Q)
        ]);

        return [$vehiculo, $marca, $modelo];
    }

    private function recepcionDe(Client $cliente, Vehicle $vehiculo): Recepcion
    {
        return Recepcion::factory()->create([
            'client_id' => $cliente->id,
            'vehicle_id' => $vehiculo->id,
            'fuel_level' => '1/2',
            'miles' => '50000',
            'symptoms' => 'Ruido al frenar',
            'witnesses' => [],
            'inventory' => [],
            'photos' => ['recepciones/foto-previa.png'],
        ]);
    }

    /** Payload completo como lo envía Editar. */
    private function payloadEdicion(Client $cliente, array $cambios = []): array
    {
        return array_merge([
            'first_name' => $cliente->first_name,
            'last_name' => $cliente->last_name,
            'phone' => $cliente->phone,
            'address' => $cliente->address,
            'rfc' => '',
            'country' => 'MX',
            'brand_id' => '',
            'vehicle_model_id' => '',
            'year' => '2013',
            'plate' => 'NAR-430',
            'vin' => 'SUFHELZXJXNXRTOKL',
            'engine' => '',
            'fuel_level' => '1/2',
            'miles' => '50000',
            'symptoms' => 'Ruido al frenar',
            'witnesses' => [],
            'inventory' => [],
        ], $cambios);
    }

    // ── Store: pertenencia Cliente → Vehículo ───────────────────────────

    public function test_store_acepta_vehiculo_del_mismo_cliente(): void
    {
        $cliente = Client::factory()->create();
        $vehiculo = Vehicle::factory()->create(['client_id' => $cliente->id]);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), [
                'client_id' => $cliente->id,
                'vehicle_id' => $vehiculo->id,
                'fuel_level' => '1/2',
                'miles' => '1000',
            ])
            ->assertRedirect(route('recepcion.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('recepcions', [
            'client_id' => $cliente->id,
            'vehicle_id' => $vehiculo->id,
        ]);
    }

    public function test_store_rechaza_vehiculo_de_otro_cliente_sin_crear_recepcion(): void
    {
        $clienteA = Client::factory()->create();
        $clienteB = Client::factory()->create();
        $vehiculoB = Vehicle::factory()->create(['client_id' => $clienteB->id]);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), [
                'client_id' => $clienteA->id,
                'vehicle_id' => $vehiculoB->id,
                'fuel_level' => '1/2',
            ])
            ->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('recepcions', 0);
    }

    public function test_store_con_vehiculo_existente_acepta_campos_de_vehiculo_nuevo_vacios(): void
    {
        // Payload del formulario al elegir un vehículo existente: los campos de vehículo nuevo llegan nulos.
        $cliente = Client::factory()->create();
        $vehiculo = Vehicle::factory()->create(['client_id' => $cliente->id]);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), [
                'country' => 'MX', 'client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id,
                'first_name' => $cliente->first_name, 'last_name' => $cliente->last_name,
                'phone' => $cliente->phone, 'address' => $cliente->address,
                'rfc' => '', 'razon_social' => '', 'sin_factura' => false,
                'brand_id' => null, 'vehicle_model_id' => null, 'year' => null,
                'plate' => null, 'vin' => null, 'engine' => null, 'color' => null,
                'miles' => 4321, 'fuel_level' => '1/2', 'witnesses' => [], 'damages' => [], 'inventory' => [], 'photos' => [],
                'symptoms' => 'Prueba',
            ])
            ->assertRedirect(route('recepcion.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('recepcions', ['client_id' => $cliente->id, 'vehicle_id' => $vehiculo->id]);
    }

    /** Fase A: el año es opcional (maquinaria); marca y modelo siguen siendo obligatorios sin vehículo. */
    public function test_store_sin_vehiculo_exige_marca_y_modelo_pero_no_anio(): void
    {
        $cliente = Client::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), [
                'client_id' => $cliente->id, 'fuel_level' => '1/2',
                'brand_id' => null, 'vehicle_model_id' => null, 'year' => null,
            ])
            ->assertSessionHasErrors(['brand_id', 'vehicle_model_id'])
            ->assertSessionDoesntHaveErrors('year');
    }

    public function test_store_rechaza_vehiculo_sin_cliente(): void
    {
        $vehiculo = Vehicle::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), [
                'vehicle_id' => $vehiculo->id,
                'fuel_level' => '1/2',
            ])
            ->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('recepcions', 0);
    }

    public function test_store_valida_campos_principales_de_cliente_nuevo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), ['fuel_level' => '1/2'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'brand_id', 'vehicle_model_id']);

        $this->assertDatabaseCount('recepcions', 0);
    }

    public function test_store_rechaza_rfc_registrado_a_otro_cliente(): void
    {
        $clienteA = Client::factory()->create();
        $clienteB = Client::factory()->create();
        ClientFiscalProfile::create(['client_id' => $clienteB->id, 'rfc' => 'GARL850101ABC', 'razon_social' => 'B SA']);

        $marca = Brand::create(['name' => 'Ford']);
        $modelo = VehicleModel::create(['brand_id' => $marca->id, 'name' => 'Ranger']);

        $this->actingAs($this->admin())
            ->post(route('recepcion.store'), [
                'client_id' => $clienteA->id,
                'fuel_level' => '1/2',
                'rfc' => 'GARL850101ABC',
                'brand_id' => $marca->id,
                'vehicle_model_id' => $modelo->id,
                'year' => '2020',
            ])
            ->assertSessionHasErrors('rfc');
    }

    public function test_mecanico_no_puede_crear_ni_actualizar(): void
    {
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $cliente = Client::factory()->create();
        $recepcion = $this->recepcionDe($cliente, $this->vehiculoLegacy($cliente));

        $this->actingAs($mecanico)->get(route('recepcion.create'))->assertForbidden();
        $this->actingAs($mecanico)->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente))->assertForbidden();
    }

    // ── Editar: carga y compatibilidad legacy ───────────────────────────

    public function test_editar_entrega_marca_y_modelo_historicos_como_texto(): void
    {
        $cliente = Client::factory()->create();
        $recepcion = $this->recepcionDe($cliente, $this->vehiculoLegacy($cliente));

        $this->actingAs($this->admin())
            ->get(route('recepcion.edit', $recepcion))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Recepciones/Edit')
                ->where('legacyVehicle.brand', 'Ford')
                ->where('legacyVehicle.model', 'Hilux')
            );
    }

    public function test_registro_legacy_se_guarda_sin_seleccionar_marca_ni_modelo(): void
    {
        $cliente = Client::factory()->create();
        $vehiculo = $this->vehiculoLegacy($cliente);
        $recepcion = $this->recepcionDe($cliente, $vehiculo);

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente, [
                'symptoms' => 'Cambio solo de síntomas',
                'fuel_level' => '3/4',
            ]))
            ->assertRedirect(route('recepcion.index'))
            ->assertSessionHasNoErrors();

        $recepcion->refresh();
        $vehiculo->refresh();

        $this->assertSame('Cambio solo de síntomas', $recepcion->symptoms);
        $this->assertSame('3/4', $recepcion->fuel_level);
        // Datos legacy intactos: sin FK nuevas y con el texto original.
        $this->assertNull($vehiculo->brand_id);
        $this->assertNull($vehiculo->model_id);
        $this->assertSame('Ford', $vehiculo->brand);
        $this->assertSame('Hilux', $vehiculo->model);
        $this->assertSame('NAR-430', $vehiculo->plate);
        $this->assertSame('SUFHELZXJXNXRTOKL', $vehiculo->vin);
    }

    public function test_editar_vehiculo_normalizado_mantiene_sus_selects_consistentes(): void
    {
        $cliente = Client::factory()->create();
        [$vehiculo, $marca, $modelo] = $this->vehiculoNormalizado($cliente);
        $recepcion = $this->recepcionDe($cliente, $vehiculo);

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente, [
                'brand_id' => $marca->id,
                'vehicle_model_id' => $modelo->id,
                'year' => '2019',
                'plate' => 'ABC-111',
                'vin' => 'JN1TANT30U0000001',
            ]))
            ->assertSessionHasNoErrors();

        $vehiculo->refresh();
        $this->assertSame($marca->id, $vehiculo->brand_id);
        $this->assertSame($modelo->id, $vehiculo->model_id);
        $this->assertSame('ABC-111', $vehiculo->plate);
    }

    public function test_update_rechaza_modelo_de_otra_marca(): void
    {
        $cliente = Client::factory()->create();
        [$vehiculo, $marca] = $this->vehiculoNormalizado($cliente);
        $otraMarca = Brand::create(['name' => 'Toyota']);
        $modeloAjeno = VehicleModel::create(['brand_id' => $otraMarca->id, 'name' => 'Hilux']);
        $recepcion = $this->recepcionDe($cliente, $vehiculo);

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente, [
                'brand_id' => $marca->id,
                'vehicle_model_id' => $modeloAjeno->id,
            ]))
            ->assertSessionHasErrors('vehicle_model_id');
    }

    // ── Update: integridad y side effects ───────────────────────────────

    public function test_update_no_reasigna_cliente_ni_vehiculo_aunque_se_envien(): void
    {
        $clienteA = Client::factory()->create();
        $clienteB = Client::factory()->create();
        $vehiculoA = $this->vehiculoLegacy($clienteA);
        $vehiculoB = $this->vehiculoLegacy($clienteB, ['plate' => 'ZZZ-999', 'vin' => 'WAUZZZ8V0KA000001']);
        $recepcion = $this->recepcionDe($clienteA, $vehiculoA);

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($clienteA, [
                'client_id' => $clienteB->id,
                'vehicle_id' => $vehiculoB->id,
            ]))
            ->assertSessionHasNoErrors();

        $recepcion->refresh();
        $this->assertSame($clienteA->id, $recepcion->client_id);
        $this->assertSame($vehiculoA->id, $recepcion->vehicle_id);
    }

    public function test_update_valida_formato_de_vin_solo_cuando_cambia(): void
    {
        $cliente = Client::factory()->create();
        $recepcion = $this->recepcionDe($cliente, $this->vehiculoLegacy($cliente));

        // VIN histórico sin cambios (contiene una O): se acepta.
        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente))
            ->assertSessionHasNoErrors();

        // VIN nuevo inválido: se rechaza.
        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente, ['vin' => 'ABCDEFGHIJKLMNOQR']))
            ->assertSessionHasErrors('vin');
    }

    public function test_update_valida_nombre_requerido(): void
    {
        $cliente = Client::factory()->create();
        $recepcion = $this->recepcionDe($cliente, $this->vehiculoLegacy($cliente));

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente, ['first_name' => '']))
            ->assertSessionHasErrors('first_name');
    }

    public function test_update_rechaza_rfc_de_otro_cliente(): void
    {
        $clienteA = Client::factory()->create();
        $clienteB = Client::factory()->create();
        ClientFiscalProfile::create(['client_id' => $clienteB->id, 'rfc' => 'GARL850101ABC', 'razon_social' => 'B SA']);
        $recepcion = $this->recepcionDe($clienteA, $this->vehiculoLegacy($clienteA));

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($clienteA, ['rfc' => 'GARL850101ABC']))
            ->assertSessionHasErrors('rfc');

        $this->assertDatabaseMissing('client_fiscal_profiles', ['client_id' => $clienteA->id, 'rfc' => 'GARL850101ABC']);
    }

    public function test_update_sincroniza_perfil_fiscal_del_cliente(): void
    {
        $cliente = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $recepcion = $this->recepcionDe($cliente, $this->vehiculoLegacy($cliente));

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente, ['rfc' => 'ruia900101xy1']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', ['id' => $cliente->id, 'rfc' => 'RUIA900101XY1']);
        $this->assertDatabaseHas('client_fiscal_profiles', [
            'client_id' => $cliente->id,
            'rfc' => 'RUIA900101XY1',
            'razon_social' => 'Ana Ruiz',
        ]);
    }

    public function test_update_no_crea_orden_ni_duplica_cliente_o_vehiculo_ni_toca_fotos(): void
    {
        $cliente = Client::factory()->create();
        $vehiculo = $this->vehiculoLegacy($cliente);
        $recepcion = $this->recepcionDe($cliente, $vehiculo);

        $estado = RepairOrderStatus::firstOrCreate(['slug' => 'diagnostico'], ['name' => 'Diagnóstico', 'color_class' => 'x']);
        $orden = RepairOrder::factory()->create(['recepcion_id' => $recepcion->id, 'status_id' => $estado->id]);

        $clientes = Client::count();
        $vehiculos = Vehicle::count();
        $ordenes = RepairOrder::count();

        $this->actingAs($this->admin())
            ->put(route('recepcion.update', $recepcion), $this->payloadEdicion($cliente, ['symptoms' => 'Nuevo']))
            ->assertSessionHasNoErrors();

        $this->assertSame($clientes, Client::count());
        $this->assertSame($vehiculos, Vehicle::count());
        $this->assertSame($ordenes, RepairOrder::count());
        $this->assertSame($estado->id, $orden->fresh()->status_id);
        $this->assertSame(['recepciones/foto-previa.png'], $recepcion->fresh()->photos);
    }

    // ── Index y Show: marca/modelo legacy, mixta y normalizada ─────────

    public function test_index_y_show_muestran_marca_y_modelo_legacy_sin_escribir_fk(): void
    {
        $cliente = Client::factory()->create();
        $vehiculo = $this->vehiculoLegacy($cliente);
        $recepcion = $this->recepcionDe($cliente, $vehiculo);

        $this->actingAs($this->admin())
            ->get(route('recepcion.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Recepciones/Index')
                ->where('recepciones.data.0.vehicle.display_brand', 'Ford')
                ->where('recepciones.data.0.vehicle.display_model', 'Hilux')
            );

        $this->actingAs($this->admin())
            ->get(route('recepcion.show', $recepcion))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Recepciones/Show')
                ->where('recepcion.vehicle.display_brand', 'Ford')
                ->where('recepcion.vehicle.display_model', 'Hilux')
            );

        // Mostrar no modifica el vehículo histórico.
        $vehiculo->refresh();
        $this->assertNull($vehiculo->brand_id);
        $this->assertNull($vehiculo->model_id);
        $this->assertSame('Ford', $vehiculo->brand);
        $this->assertSame('Hilux', $vehiculo->model);
    }

    public function test_index_y_show_resuelven_combinacion_mixta(): void
    {
        $cliente = Client::factory()->create();
        $marca = Brand::create(['name' => 'Nissan']);
        // Marca normalizada, modelo solo en texto.
        $vehiculo = $this->vehiculoLegacy($cliente, ['brand' => 'Nissan', 'model' => 'Tsuru', 'brand_id' => $marca->id]);
        $recepcion = $this->recepcionDe($cliente, $vehiculo);

        $this->actingAs($this->admin())
            ->get(route('recepcion.show', $recepcion))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('recepcion.vehicle.display_brand', 'Nissan')
                ->where('recepcion.vehicle.display_model', 'Tsuru')
            );
    }

    public function test_index_y_show_usan_catalogo_si_el_vehiculo_esta_normalizado(): void
    {
        $cliente = Client::factory()->create();
        [$vehiculo] = $this->vehiculoNormalizado($cliente);
        $recepcion = $this->recepcionDe($cliente, $vehiculo);

        $this->actingAs($this->admin())
            ->get(route('recepcion.show', $recepcion))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('recepcion.vehicle.display_brand', 'Nissan')
                ->where('recepcion.vehicle.display_model', 'Versa')
            );
    }

}
