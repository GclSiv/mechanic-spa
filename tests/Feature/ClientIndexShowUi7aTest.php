<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientFiscalProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UI-7A: directorio de clientes (paginación, búsqueda, marca/modelo) y expediente (permisos y datos).
 * No altera permisos: role:admin ya incluye al owner (User::isAdmin); el mecánico queda fuera.
 */
class ClientIndexShowUi7aTest extends TestCase
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

    private function cliente(array $datos = []): Client
    {
        return Client::factory()->create($datos + ['last_name' => 'Prueba']);
    }

    /** Cuenta consultas SQL ejecutadas durante el callback. */
    private function contarConsultas(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    // ---------- Index: acceso ----------

    public function test_admin_y_owner_abren_el_directorio(): void
    {
        $this->actingAs($this->admin())->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Clients/Index'));

        $this->actingAs($this->owner())->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Clients/Index'));
    }

    public function test_mecanico_recibe_403_en_el_directorio(): void
    {
        $this->actingAs($this->mecanico())->get(route('clients.index'))->assertForbidden();
    }

    // ---------- Index: paginación ----------

    public function test_pagina_de_25_y_la_segunda_pagina_es_distinta(): void
    {
        $this->cliente(['first_name' => 'Cliente']);
        Client::factory()->count(29)->create();

        $primera = $this->actingAs($this->admin())->get(route('clients.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Clients/Index')
                ->has('clients.data', 25)
                ->where('clients.total', 30)
                ->where('clients.last_page', 2)
                ->where('clients.current_page', 1)
            );

        $idsPagina1 = collect($primera->viewData('page')['props']['clients']['data'])->pluck('id')->all();

        $segunda = $this->actingAs($this->admin())->get(route('clients.index', ['page' => 2]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('clients.data', 5)
                ->where('clients.current_page', 2)
            );

        $idsPagina2 = collect($segunda->viewData('page')['props']['clients']['data'])->pluck('id')->all();

        $this->assertEmpty(array_intersect($idsPagina1, $idsPagina2));
    }

    public function test_orden_por_id_descendente_se_mantiene(): void
    {
        $antiguo = $this->cliente();
        $reciente = $this->cliente();

        $this->actingAs($this->admin())->get(route('clients.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('clients.data.0.id', $reciente->id)
                ->where('clients.data.1.id', $antiguo->id)
            );
    }

    // ---------- Index: búsqueda ----------

    public function test_busqueda_por_nombre_y_apellido(): void
    {
        $ana = $this->cliente(['first_name' => 'Ana', 'last_name' => 'Zenteno']);
        $this->cliente(['first_name' => 'Otro', 'last_name' => 'Distinto']);

        $this->actingAs($this->admin())->get(route('clients.index', ['search' => 'ana zenteno']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('clients.data', 1)
                ->where('clients.data.0.id', $ana->id)
                ->where('filters.search', 'ana zenteno')
            );
    }

    public function test_busqueda_por_telefono(): void
    {
        $this->cliente(['phone' => '9571112233']);
        $this->cliente(['phone' => '9570000000']);

        $this->actingAs($this->admin())->get(route('clients.index', ['search' => '1112233']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('clients.data', 1));
    }

    public function test_busqueda_por_rfc_del_cliente_y_del_perfil_fiscal(): void
    {
        $directo = $this->cliente(['rfc' => 'AAAA010101AAA']);
        $conPerfil = $this->cliente();
        ClientFiscalProfile::create([
            'client_id'    => $conPerfil->id,
            'rfc'          => 'BBBB020202BBB',
            'razon_social' => 'Empresa Demo',
        ]);
        $this->cliente(['rfc' => 'CCCC030303CCC']);

        $this->actingAs($this->admin())->get(route('clients.index', ['search' => 'AAAA010101']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('clients.data', 1)->where('clients.data.0.id', $directo->id));

        $this->actingAs($this->admin())->get(route('clients.index', ['search' => 'BBBB020202']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('clients.data', 1)->where('clients.data.0.id', $conPerfil->id));
    }

    public function test_busqueda_por_placa(): void
    {
        $cliente = $this->cliente();
        Vehicle::factory()->create(['client_id' => $cliente->id, 'plate' => 'XYZ-987']);
        $this->cliente();

        $this->actingAs($this->admin())->get(route('clients.index', ['search' => 'XYZ-987']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('clients.data', 1)
                ->where('clients.data.0.id', $cliente->id)
            );
    }

    public function test_los_comodines_del_usuario_no_cambian_la_busqueda(): void
    {
        $this->cliente(['first_name' => 'Ana']);

        // "A_a" se normaliza a "Aa": el guion bajo no comodín y no coincide con "Ana".
        $this->actingAs($this->admin())->get(route('clients.index', ['search' => 'A_a']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('clients.data', 0));

        // "%" solo se quita: sin texto útil el listado vuelve a mostrar todo, sin comodín.
        $this->actingAs($this->admin())->get(route('clients.index', ['search' => '%']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('clients.data', 1));
    }

    public function test_la_busqueda_se_conserva_al_paginar(): void
    {
        Client::factory()->count(30)->create(['first_name' => 'Coincide']);
        Client::factory()->count(5)->create(['first_name' => 'Ajeno']);

        $this->actingAs($this->admin())->get(route('clients.index', ['search' => 'Coincide']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('clients.data', 25)
                ->where('clients.total', 30)
                ->where('clients.next_page_url', fn ($url) => str_contains((string) $url, 'search=Coincide') && str_contains((string) $url, 'page=2'))
            );

        $this->actingAs($this->admin())->get(route('clients.index', ['search' => 'Coincide', 'page' => 2]))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('clients.data', 5)->where('clients.total', 30));
    }

    // ---------- Index: marca/modelo y campos ----------

    public function test_vehiculo_legacy_muestra_el_texto_historico(): void
    {
        $cliente = $this->cliente();
        Vehicle::factory()->create([
            'client_id' => $cliente->id,
            'brand_id'  => null,
            'model_id' => null,
            'brand'     => 'Nissan',
            'model'     => 'Versa',
        ]);

        $this->actingAs($this->admin())->get(route('clients.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('clients.data.0.vehicles.0.display_brand', 'Nissan')
                ->where('clients.data.0.vehicles.0.display_model', 'Versa')
            );
    }

    public function test_vehiculo_normalizado_muestra_el_catalogo(): void
    {
        $marca = Brand::create(['name' => 'Mazda']);
        $modelo = VehicleModel::create(['brand_id' => $marca->id, 'name' => 'CX-5']);
        $cliente = $this->cliente();
        Vehicle::factory()->create([
            'client_id'        => $cliente->id,
            'brand_id'         => $marca->id,
            'model_id' => $modelo->id,
            'brand'            => 'Texto viejo',
            'model'            => 'Modelo viejo',
        ]);

        $this->actingAs($this->admin())->get(route('clients.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('clients.data.0.vehicles.0.display_brand', 'Mazda')
                ->where('clients.data.0.vehicles.0.display_model', 'CX-5')
            );
    }

    public function test_el_listado_no_carga_finanzas_ni_servicios(): void
    {
        $this->cliente();

        $this->actingAs($this->admin())->get(route('clients.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->missing('clients.data.0.repair_orders')
                ->missing('clients.data.0.recepciones')
                ->missing('clients.data.0.photos')
            );
    }

    public function test_el_numero_de_consultas_no_crece_con_clientes_y_vehiculos(): void
    {
        $admin = $this->admin();

        foreach (range(1, 3) as $_) {
            $c = $this->cliente();
            Vehicle::factory()->count(2)->create(['client_id' => $c->id]);
        }
        // Calentamiento: la primera petición carga configuración y sesión; no cuenta para la comparación.
        $this->actingAs($admin)->get(route('clients.index'));
        $pocas = $this->contarConsultas(fn () => $this->actingAs($admin)->get(route('clients.index')));

        foreach (range(1, 12) as $_) {
            $c = $this->cliente();
            Vehicle::factory()->count(2)->create(['client_id' => $c->id]);
        }
        $muchas = $this->contarConsultas(fn () => $this->actingAs($admin)->get(route('clients.index')));

        $this->assertSame($pocas, $muchas, 'El Index no debe crecer en consultas con más clientes o vehículos.');
    }

    // ---------- Show ----------

    public function test_expediente_mecanico_403_y_owner_200(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->mecanico())->get(route('clients.show', $cliente))->assertForbidden();
        $this->actingAs($this->owner())->get(route('clients.show', $cliente))->assertOk();
    }

    public function test_expediente_entrega_datos_generales_y_fiscales_a_admin(): void
    {
        $cliente = $this->cliente([
            'first_name' => 'Laura',
            'phone'      => '9575556677',
            'email'      => 'laura@example.test',
            'address'    => 'Calle Uno 10',
        ]);
        ClientFiscalProfile::create([
            'client_id'    => $cliente->id,
            'rfc'          => 'LAUR800101ABC',
            'razon_social' => 'Laura Demo SA',
        ]);

        $this->actingAs($this->admin())->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Clients/Show')
                ->where('client.phone', '9575556677')
                ->where('client.email', 'laura@example.test')
                ->where('client.address', 'Calle Uno 10')
                ->where('client.fiscal_profiles.0.rfc', 'LAUR800101ABC')
                ->where('client.fiscal_profiles.0.razon_social', 'Laura Demo SA')
                ->has('resumen_financiero')
            );
    }

    public function test_expediente_sin_perfil_fiscal_no_crea_registros(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($this->admin())->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('client.fiscal_profiles', 0));

        $this->assertSame(0, ClientFiscalProfile::count());
    }

    public function test_expediente_vehiculo_legacy_y_normalizado(): void
    {
        $cliente = $this->cliente();
        Vehicle::factory()->create([
            'client_id' => $cliente->id, 'brand_id' => null, 'model_id' => null,
            'brand' => 'Nissan', 'model' => 'Versa',
        ]);
        $marca = Brand::create(['name' => 'Mazda']);
        $modelo = VehicleModel::create(['brand_id' => $marca->id, 'name' => 'CX-5']);
        Vehicle::factory()->create([
            'client_id' => $cliente->id, 'brand_id' => $marca->id, 'model_id' => $modelo->id,
            'brand' => 'Texto viejo', 'model' => 'Modelo viejo',
        ]);

        $this->actingAs($this->admin())->get(route('clients.show', $cliente))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('vehiculos', 2)
                ->where('vehiculos.0.marca', 'Nissan')
                ->where('vehiculos.0.modelo', 'Versa')
                ->where('vehiculos.1.marca', 'Mazda')
                ->where('vehiculos.1.modelo', 'CX-5')
            );
    }
}
