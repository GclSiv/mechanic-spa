<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ClientStatus;
use App\Models\Client;
use App\Models\Recepcion;
use App\Models\RecepcionItem;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Recepción V2: identificador flexible, lectura con unidad, medida de llanta, elementos recibidos,
 * compatibilidad con datos históricos, PDF y seguridad.
 */
class RecepcionV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ClientStatus::firstOrCreate(['name' => 'Active'], ['color' => 'bg-green-500']);
    }

    private const MIGRACION = 'database/migrations/2026_11_30_000001_add_recepcion_v2_to_recepcions_and_vehicles.php';

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Payload válido de alta con vehículo nuevo. Cada prueba sobrescribe lo que necesita. */
    private function payload(array $extra = []): array
    {
        $marca = Brand::firstOrCreate(['name' => 'Nissan']);
        $modelo = VehicleModel::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Versa']);

        return array_merge([
            // Cliente nuevo en cada alta: el alta rechaza un nombre de cliente repetido.
            'first_name'      => 'Ana ' . bin2hex(random_bytes(3)),
            'last_name'       => 'López',
            'phone'           => '5512345678',
            'brand_id'        => $marca->id,
            'vehicle_model_id'=> $modelo->id,
            'year'            => 2019,
            'plate'           => 'ABC-123',
            'fuel_level'      => '1/2',
            'witnesses'       => [],
            'damages'         => [],
        ], $extra);
    }

    private function registrar(array $extra = [])
    {
        return $this->actingAs($this->admin())->post(route('recepcion.store'), $this->payload($extra));
    }

    private function recepcionCreada(): Recepcion
    {
        return Recepcion::latest('id')->firstOrFail();
    }

    // ── Identificador ─────────────────────────────────────────────────────

    public function test_vin_completo_se_guarda_como_identificador_y_en_vin_legado(): void
    {
        $this->registrar(['identifier_type' => 'vin', 'identifier_value' => 'jn1tant30u0000001']);

        $vehiculo = $this->recepcionCreada()->vehicle;
        $this->assertSame('vin', $vehiculo->identifier_type);
        $this->assertSame('JN1TANT30U0000001', $vehiculo->identifier_value);
        $this->assertSame('JN1TANT30U0000001', $vehiculo->vin);
    }

    public function test_vin_invalido_con_letra_i_se_rechaza(): void
    {
        $this->registrar(['identifier_type' => 'vin', 'identifier_value' => 'JI1TANT30U0000001'])
            ->assertSessionHasErrors('vin');

        $this->assertSame(0, Recepcion::count());
    }

    public function test_vin_con_longitud_incorrecta_se_rechaza(): void
    {
        $this->registrar(['identifier_type' => 'vin', 'identifier_value' => 'ABC123'])
            ->assertSessionHasErrors('vin');
    }

    public function test_ultimos_8_acepta_alfanumerico_y_no_escribe_vin(): void
    {
        $this->registrar(['identifier_type' => 'vin_last_8', 'identifier_value' => 'ab12cd34']);

        $vehiculo = $this->recepcionCreada()->vehicle;
        $this->assertSame('vin_last_8', $vehiculo->identifier_type);
        $this->assertSame('AB12CD34', $vehiculo->identifier_value);
        $this->assertNull($vehiculo->vin);
    }

    public function test_ultimos_10_exige_diez_caracteres(): void
    {
        $this->registrar(['identifier_type' => 'vin_last_10', 'identifier_value' => 'ABC123'])
            ->assertSessionHasErrors('identifier_value');

        $this->registrar(['identifier_type' => 'vin_last_10', 'identifier_value' => 'ABC1234567', 'plate' => 'ZZZ-999'])
            ->assertSessionHasNoErrors();
    }

    public function test_numero_de_serie_y_otro_aceptan_texto_libre(): void
    {
        $this->registrar(['identifier_type' => 'serial', 'identifier_value' => 'SN-889-XYZ'])->assertSessionHasNoErrors();
        $this->assertSame('serial', $this->recepcionCreada()->vehicle->identifier_type);
        $this->assertSame('SN-889-XYZ', $this->recepcionCreada()->vehicle->identifier_value);
    }

    public function test_tipo_sin_identificador_no_guarda_valor(): void
    {
        $this->registrar(['identifier_type' => 'none', 'identifier_value' => 'algo que no debe guardarse']);

        $vehiculo = $this->recepcionCreada()->vehicle;
        $this->assertSame('none', $vehiculo->identifier_type);
        $this->assertNull($vehiculo->identifier_value);
    }

    public function test_tipo_fuera_de_lista_se_rechaza(): void
    {
        $this->registrar(['identifier_type' => 'matricula', 'identifier_value' => '123'])
            ->assertSessionHasErrors('identifier_type');
    }

    public function test_duplicado_por_vin_sigue_rechazado_solo_para_tipo_vin(): void
    {
        $this->registrar(['identifier_type' => 'vin', 'identifier_value' => 'JN1TANT30U0000001']);

        $this->registrar([
            'plate' => 'ZZZ-999',
            'identifier_type' => 'vin',
            'identifier_value' => 'JN1TANT30U0000001',
        ])->assertSessionHasErrors('vin');
    }

    public function test_serial_repetido_no_dispara_duplicado_por_vin(): void
    {
        $this->registrar(['identifier_type' => 'serial', 'identifier_value' => 'SN-1']);

        $this->registrar(['plate' => 'ZZZ-999', 'identifier_type' => 'serial', 'identifier_value' => 'SN-1'])
            ->assertSessionHasNoErrors();
    }

    public function test_vin_legado_sin_tipo_se_trata_como_identificador_vin(): void
    {
        $this->registrar(['vin' => 'JN1TANT30U0000002']);

        $vehiculo = $this->recepcionCreada()->vehicle;
        $this->assertSame('vin', $vehiculo->identifier_type);
        $this->assertSame('JN1TANT30U0000002', $vehiculo->identifier_value);
    }

    // ── Lectura y medida de llanta ────────────────────────────────────────

    public function test_lectura_se_guarda_con_su_unidad_sin_convertir(): void
    {
        $this->registrar(['reading_type' => 'mi', 'reading_value' => '45210.5']);

        $r = $this->recepcionCreada();
        $this->assertSame('mi', $r->reading_type);
        $this->assertEqualsWithDelta(45210.5, (float) $r->reading_value, 0.001);
        $this->assertNull($r->miles, 'El formulario nuevo no escribe la columna legacy miles.');
        $this->assertNull($r->kilometrajeKm(), 'Millas no es comparable con km.');
    }

    public function test_lectura_en_horas_no_cuenta_como_kilometraje(): void
    {
        $this->registrar(['reading_type' => 'hr', 'reading_value' => '1200']);

        $this->assertNull($this->recepcionCreada()->kilometrajeKm());
    }

    public function test_lectura_en_km_es_comparable(): void
    {
        $this->registrar(['reading_type' => 'km', 'reading_value' => '98765']);

        $this->assertSame(98765, $this->recepcionCreada()->kilometrajeKm());
    }

    public function test_valor_de_lectura_sin_unidad_se_rechaza(): void
    {
        $this->registrar(['reading_value' => '1000'])->assertSessionHasErrors('reading_type');
    }

    public function test_unidad_de_lectura_fuera_de_lista_se_rechaza(): void
    {
        $this->registrar(['reading_type' => 'ft', 'reading_value' => '10'])->assertSessionHasErrors('reading_type');
    }

    public function test_lectura_negativa_se_rechaza(): void
    {
        $this->registrar(['reading_type' => 'km', 'reading_value' => '-5'])->assertSessionHasErrors('reading_value');
    }

    public function test_medida_de_llanta_se_guarda_y_vacia_se_guarda_nula(): void
    {
        $this->registrar(['tire_size' => '  205/55 R16  ']);
        $this->assertSame('205/55 R16', $this->recepcionCreada()->tire_size);

        $this->registrar(['plate' => 'ZZZ-999', 'tire_size' => '   ']);
        $this->assertNull($this->recepcionCreada()->tire_size);
    }

    // ── Elementos recibidos ───────────────────────────────────────────────

    public function test_elementos_se_guardan_en_orden_con_cantidad_y_unidad(): void
    {
        $this->registrar(['items' => [
            ['name' => 'Llanta de refacción', 'quantity' => 1, 'unit' => '', 'observation' => 'con rin'],
            ['name' => 'Gato', 'quantity' => 1, 'unit' => null, 'observation' => null],
            ['name' => 'Tapetes', 'quantity' => 4, 'unit' => 'pza', 'observation' => 'hule'],
        ]])->assertSessionHasNoErrors();

        $items = $this->recepcionCreada()->items;
        $this->assertCount(3, $items);
        $this->assertSame(['Llanta de refacción', 'Gato', 'Tapetes'], $items->pluck('name')->all());
        $this->assertSame(4, $items[2]->quantity);
        $this->assertSame('pza', $items[2]->unit);
        $this->assertNull($items[0]->unit);
    }

    public function test_elemento_sin_nombre_se_rechaza(): void
    {
        $this->registrar(['items' => [['name' => '', 'quantity' => 1]]])
            ->assertSessionHasErrors('items.0.name');
    }

    public function test_cantidad_debe_ser_entera_positiva(): void
    {
        $this->registrar(['items' => [['name' => 'Gato', 'quantity' => 0]]])
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_actualizar_reemplaza_elementos_y_sin_items_no_los_toca(): void
    {
        $this->registrar(['items' => [
            ['name' => 'Gato', 'quantity' => 1],
            ['name' => 'Estéreo', 'quantity' => 1],
        ]]);
        $recepcion = $this->recepcionCreada();

        $this->actingAs($this->admin())->put(route('recepcion.update', $recepcion->id), [
            'first_name' => 'Ana', 'last_name' => 'López', 'fuel_level' => '1/2',
            'items' => [['name' => 'Herramienta', 'quantity' => 2, 'unit' => 'set']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Herramienta'], $recepcion->fresh()->items->pluck('name')->all());

        $this->actingAs($this->admin())->put(route('recepcion.update', $recepcion->id), [
            'first_name' => 'Ana', 'last_name' => 'López', 'fuel_level' => '1/4', 'symptoms' => 'ruido',
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $recepcion->fresh()->items, 'Sin items en la petición, la lista se conserva.');
    }

    public function test_editar_con_elementos_repetidos_reemplaza_la_lista_sin_mezclar_filas_anteriores(): void
    {
        $this->registrar(['items' => [['name' => 'Gato', 'quantity' => 1]]]);
        $recepcion = $this->recepcionCreada();

        $this->actingAs($this->admin())->put(route('recepcion.update', $recepcion->id), [
            'first_name' => 'Ana', 'last_name' => 'López', 'fuel_level' => '1/2',
            'items' => [['name' => 'Gato', 'quantity' => 1], ['name' => 'Gato', 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertCount(2, RecepcionItem::where('recepcion_id', $recepcion->id)->get());
    }

    // ── Compatibilidad con datos históricos ───────────────────────────────

    public function test_actualizar_no_escribe_miles_ni_inventario_legados(): void
    {
        $this->registrar();
        $recepcion = $this->recepcionCreada();
        $recepcion->forceFill(['miles' => '12500', 'inventory' => ['llanta', 'gato']])->save();

        $this->actingAs($this->admin())->put(route('recepcion.update', $recepcion->id), [
            'first_name' => 'Ana', 'last_name' => 'López', 'fuel_level' => '1/2',
            'miles' => '99999', 'inventory' => ['estereo'],
        ]);

        $fresca = $recepcion->fresh();
        $this->assertSame('12500', $fresca->miles);
        $this->assertSame(['llanta', 'gato'], $fresca->inventory);
    }

    public function test_migracion_copia_historico_sin_perder_columnas_y_es_idempotente(): void
    {
        $this->registrar();
        $recepcion = $this->recepcionCreada();

        // Simula un registro anterior: sin identificador, lectura ni elementos; con vin, miles e inventory legados.
        $recepcion->vehicle->forceFill([
            'vin' => 'JN1TANT30U0000003', 'identifier_type' => null, 'identifier_value' => null,
        ])->save();
        $recepcion->forceFill([
            'miles' => '12500', 'reading_type' => null, 'reading_value' => null,
            'inventory' => ['llanta', 'gato', 'desconocido'],
        ])->save();
        RecepcionItem::where('recepcion_id', $recepcion->id)->delete();

        $migracion = require base_path(self::MIGRACION);
        $copiar = new ReflectionMethod($migracion, 'copiarHistorico');
        $copiar->setAccessible(true);
        $copiar->invoke($migracion);
        $copiar->invoke($migracion); // segunda vez: no debe duplicar

        $fresca = $recepcion->fresh();
        $this->assertNull($fresca->vehicle->identifier_type, 'La migración no reclasifica el VIN histórico.');
        $this->assertNull($fresca->vehicle->identifier_value, 'La migración no escribe identifier_value desde vin.');
        $this->assertSame('JN1TANT30U0000003', $fresca->vehicle->vin, 'La columna vin no cambia.');
        $this->assertNull($fresca->reading_type, 'La migración no inventa la unidad del histórico.');
        $this->assertNull($fresca->reading_value, 'Sin unidad conocida no se copia a reading_value.');
        $this->assertSame('12500', $fresca->miles, 'La columna miles no cambia.');
        $this->assertNull($fresca->kilometrajeKm(), 'Un histórico sin unidad no es kilometraje comparable.');
        $this->assertSame(['llanta', 'gato', 'desconocido'], $fresca->inventory, 'La columna inventory no cambia.');
        $this->assertSame(['Llanta de refacción', 'Gato'], $fresca->items->pluck('name')->all(), 'Solo códigos conocidos.');
        $this->assertSame(['llanta', 'gato'], $fresca->items->pluck('code')->all());
    }

    public function test_historico_con_miles_no_numerico_no_se_convierte_en_km(): void
    {
        $this->registrar();
        $recepcion = $this->recepcionCreada();
        $recepcion->forceFill(['miles' => 'aprox 12 mil', 'reading_type' => null, 'reading_value' => null])->save();

        $migracion = require base_path(self::MIGRACION);
        $copiar = new ReflectionMethod($migracion, 'copiarHistorico');
        $copiar->setAccessible(true);
        $copiar->invoke($migracion);

        $fresca = $recepcion->fresh();
        $this->assertNull($fresca->reading_type);
        $this->assertNull($fresca->reading_value);
        $this->assertSame('aprox 12 mil', $fresca->miles);
    }

    /** Ejecuta la copia de histórico de la migración, igual que al migrar. */
    private function ejecutarCopiaHistorica(): void
    {
        $migracion = require base_path(self::MIGRACION);
        $copiar = new ReflectionMethod($migracion, 'copiarHistorico');
        $copiar->setAccessible(true);
        $copiar->invoke($migracion);
    }

    /** Vehículo histórico: solo vin, sin identificador nuevo. */
    private function vehiculoHistorico(string $vin): Vehicle
    {
        // Placa única por llamada: varios históricos en una misma prueba no deben chocar por placa duplicada.
        $this->registrar(['plate' => 'H' . bin2hex(random_bytes(3))]);
        $vehiculo = $this->recepcionCreada()->vehicle;
        $vehiculo->forceFill(['vin' => $vin, 'identifier_type' => null, 'identifier_value' => null])->save();

        return $vehiculo->fresh();
    }

    public function test_vin_historico_de_17_caracteres_conserva_identifier_type_nulo(): void
    {
        $vehiculo = $this->vehiculoHistorico('JN1TANT30U0000006');

        $this->ejecutarCopiaHistorica();

        $fresco = $vehiculo->fresh();
        $this->assertNull($fresco->identifier_type, 'No se reescribe automáticamente un VIN de 17 caracteres.');
        $this->assertNull($fresco->identifier_value);
        $this->assertSame('JN1TANT30U0000006', $fresco->vin, 'vin intacto.');
    }

    public function test_vin_historico_corto_o_invalido_conserva_identifier_type_nulo(): void
    {
        $vehiculo = $this->vehiculoHistorico('PLACA-VIEJA');

        $this->ejecutarCopiaHistorica();

        $fresco = $vehiculo->fresh();
        $this->assertNull($fresco->identifier_type);
        $this->assertNull($fresco->identifier_value);
        $this->assertSame('PLACA-VIEJA', $fresco->vin, 'vin intacto.');
    }

    public function test_ningun_vin_historico_se_reclasifica_como_other(): void
    {
        $this->vehiculoHistorico('JN1TANT30U0000007');
        $this->vehiculoHistorico('PLACA-VIEJA-2');
        $this->vehiculoHistorico('ABC');

        $this->ejecutarCopiaHistorica();
        $this->ejecutarCopiaHistorica(); // dos veces: sigue sin reclasificar

        $this->assertSame(0, Vehicle::where('identifier_type', 'other')->count());
        $this->assertSame(3, Vehicle::whereNull('identifier_type')->whereNotNull('vin')->count());
    }

    public function test_vin_historico_se_sigue_mostrando_por_fallback_legacy_en_detalle_y_pdf(): void
    {
        $largo = $this->vehiculoHistorico('JN1TANT30U0000008');
        $this->ejecutarCopiaHistorica();

        $recepcion = Recepcion::where('vehicle_id', $largo->id)->firstOrFail();
        $this->actingAs($this->admin())->get(route('recepcion.show', $recepcion->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recepcion.vehicle.vin', 'JN1TANT30U0000008')
                ->where('recepcion.vehicle.identifier_type', null));

        $this->assertStringContainsString('VIN: JN1TANT30U0000008', $this->vistaPdf($recepcion));
    }

    public function test_vin_historico_corto_se_sigue_mostrando_por_fallback_legacy_en_pdf(): void
    {
        $corto = $this->vehiculoHistorico('PLACA-VIEJA-3');
        $this->ejecutarCopiaHistorica();

        $recepcion = Recepcion::where('vehicle_id', $corto->id)->firstOrFail();
        $this->assertStringContainsString('VIN: PLACA-VIEJA-3', $this->vistaPdf($recepcion));
    }

    public function test_datos_nuevos_usan_identifier_type_y_value_sin_tocar_historicos(): void
    {
        $historico = $this->vehiculoHistorico('JN1TANT30U0000009');

        $this->registrar(['identifier_type' => 'vin_last_10', 'identifier_value' => 'ABC1234567', 'plate' => 'NEW-777']);
        $nuevo = $this->recepcionCreada()->vehicle;

        $this->ejecutarCopiaHistorica();

        $this->assertSame('vin_last_10', $nuevo->fresh()->identifier_type);
        $this->assertSame('ABC1234567', $nuevo->fresh()->identifier_value);
        $this->assertNull($nuevo->fresh()->vin, 'Un identificador que no es VIN completo no escribe vin.');

        $this->assertNull($historico->fresh()->identifier_type, 'El histórico sigue sin reclasificar.');
        $this->assertSame('JN1TANT30U0000009', $historico->fresh()->vin);
    }

    public function test_historico_sin_unidad_no_es_kilometraje_ni_se_convierte(): void
    {
        $this->registrar();
        $recepcion = $this->recepcionCreada();
        $recepcion->forceFill(['miles' => '52500', 'reading_type' => null, 'reading_value' => null])->save();
        $fresca = $recepcion->fresh();

        $this->assertNull($fresca->kilometrajeKm(), 'No cuenta como km comparable.');
        $this->assertSame('52,500 · unit not specified', $fresca->lecturaPdf(), 'Visible, con la unidad marcada como desconocida.');
        $this->assertSame('52500', $fresca->miles, 'miles no cambia.');
    }

    public function test_historico_con_decimal_se_muestra_tal_cual_sin_redondear(): void
    {
        $this->registrar();
        $recepcion = $this->recepcionCreada();
        $recepcion->forceFill(['miles' => '52500.5', 'reading_type' => null, 'reading_value' => null])->save();

        $this->assertSame('52500.5 · unit not specified', $recepcion->fresh()->lecturaPdf());
    }

    public function test_lectura_nueva_en_millas_no_se_convierte_a_km_ni_horas(): void
    {
        $this->registrar(['reading_type' => 'mi', 'reading_value' => '52500']);
        $r = $this->recepcionCreada();

        $this->assertNull($r->kilometrajeKm(), 'Millas no es km comparable.');
        $this->assertSame('52,500 mi', $r->lecturaPdf(), 'Se muestra en millas, sin convertir.');
    }

    public function test_lectura_nueva_en_horas_no_se_convierte_a_km(): void
    {
        $this->registrar(['reading_type' => 'hr', 'reading_value' => '52500']);
        $r = $this->recepcionCreada();

        $this->assertNull($r->kilometrajeKm());
        $this->assertSame('52,500 hr', $r->lecturaPdf());
    }

    public function test_ficha_de_cliente_no_muestra_como_kilometraje_un_historico_sin_unidad(): void
    {
        $vehiculo = Vehicle::factory()->create();
        Recepcion::create([
            'client_id' => $vehiculo->client_id, 'vehicle_id' => $vehiculo->id, 'fuel_level' => '1/2',
            'miles' => '52500', 'reading_type' => null, 'reading_value' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('clients.show', $vehiculo->client_id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehiculos.0.kilometraje', null));
    }

    public function test_metricas_de_kilometraje_solo_cuentan_lecturas_en_km_explicitas(): void
    {
        $vehiculo = Vehicle::factory()->create();
        $base = ['client_id' => $vehiculo->client_id, 'vehicle_id' => $vehiculo->id, 'fuel_level' => '1/2'];

        Recepcion::create($base + ['miles' => '40000']); // histórico sin unidad
        Recepcion::create($base + ['miles' => '52500', 'reading_type' => 'mi', 'reading_value' => '52500']);

        $this->assertNull(
            Recepcion::where('vehicle_id', $vehiculo->id)->latest('id')->first()->kilometrajeKm(),
            'La lectura más reciente en millas no es comparable.'
        );

        Recepcion::create($base + ['miles' => null, 'reading_type' => 'km', 'reading_value' => '60000']);
        $this->assertSame(60000, Recepcion::where('vehicle_id', $vehiculo->id)->latest('id')->first()->kilometrajeKm());
    }

    // ── Vistas y PDF ──────────────────────────────────────────────────────

    public function test_detalle_muestra_identificador_lectura_y_elementos(): void
    {
        $this->registrar([
            'identifier_type' => 'vin_last_8', 'identifier_value' => 'AB12CD34',
            'reading_type' => 'mi', 'reading_value' => '45000',
            'tire_size' => '205/55 R16',
            'items' => [['name' => 'Gato', 'quantity' => 1]],
        ]);
        $recepcion = $this->recepcionCreada();

        $this->actingAs($this->admin())->get(route('recepcion.show', $recepcion->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Recepciones/Show')
                ->where('recepcion.vehicle.identifier_type', 'vin_last_8')
                ->where('recepcion.vehicle.identifier_value', 'AB12CD34')
                ->where('recepcion.reading_type', 'mi')
                ->where('recepcion.tire_size', '205/55 R16')
                ->has('recepcion.items', 1)
                ->where('recepcion.items.0.name', 'Gato'));
    }

    public function test_pdf_muestra_identificador_lectura_llanta_y_elementos(): void
    {
        $this->registrar([
            'identifier_type' => 'vin', 'identifier_value' => 'JN1TANT30U0000004',
            'reading_type' => 'km', 'reading_value' => '52000',
            'tire_size' => '215/60 R17',
            'items' => [['name' => 'Llanta de refacción', 'quantity' => 1, 'unit' => 'pza', 'observation' => 'con rin']],
        ]);

        $html = $this->vistaPdf($this->recepcionCreada());

        $this->assertStringContainsString('VIN: JN1TANT30U0000004', $html);
        $this->assertStringContainsString('52,000 km', $html);
        $this->assertStringContainsString('215/60 R17', $html);
        $this->assertStringContainsString('Items Received', $html);
        $this->assertStringContainsString('Llanta de refacción', $html);
        $this->assertStringContainsString('con rin', $html);
    }

    public function test_pdf_de_registro_historico_sigue_renderizando(): void
    {
        $this->registrar();
        $recepcion = $this->recepcionCreada();
        $recepcion->vehicle->forceFill(['vin' => 'JN1TANT30U0000005', 'identifier_type' => null, 'identifier_value' => null])->save();
        $recepcion->forceFill(['miles' => '30000', 'reading_type' => null, 'reading_value' => null, 'tire_size' => null])->save();

        $html = $this->vistaPdf($recepcion->fresh());

        $this->assertStringContainsString('VIN: JN1TANT30U0000005', $html, 'Sin tipo propio, el VIN legado se muestra como VIN.');
        $this->assertStringContainsString('30,000 · unit not specified', $html, 'La unidad histórica queda marcada como desconocida.');
        $this->assertStringNotContainsString('30,000 km', $html, 'No se imprime como km.');
        $this->assertStringNotContainsString('Items Received', $html, 'Sin elementos no se imprime la sección.');
        $this->assertStringNotContainsString('Tire size', $html);
    }

    public function test_pdf_no_imprime_identificador_cuando_es_none(): void
    {
        $this->registrar(['identifier_type' => 'none']);

        $html = $this->vistaPdf($this->recepcionCreada());

        $this->assertStringContainsString('N/A /', $html);
    }

    private function vistaPdf(Recepcion $recepcion): string
    {
        $recepcion->load(['client', 'vehicle.brand', 'vehicle.vehicleModel', 'items']);

        return view('pdf.recepcion', [
            'recepcion' => $recepcion,
            'settings'  => Setting::current(),
            'testigos'  => collect(),
        ])->render();
    }

    // ── Seguridad ─────────────────────────────────────────────────────────

    public function test_mecanico_no_puede_registrar_ni_ver_recepciones_v2(): void
    {
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)->post(route('recepcion.store'), $this->payload([
            'identifier_type' => 'serial', 'identifier_value' => 'SN-X',
            'items' => [['name' => 'Gato', 'quantity' => 1]],
        ]))->assertForbidden();

        $this->assertSame(0, Recepcion::count());
    }

    public function test_mecanico_no_puede_ver_el_pdf_de_una_recepcion(): void
    {
        $this->registrar(['identifier_type' => 'serial', 'identifier_value' => 'SN-Y']);
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)->get(route('recepcion.pdf', $this->recepcionCreada()->id))->assertForbidden();
    }
}
