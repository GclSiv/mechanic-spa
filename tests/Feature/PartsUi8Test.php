<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Part;
use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UI-8: Refacciones (Index, Create, Edit y borrado protegido).
 * Permisos: rutas parts.* bajo role:admin (admin y owner); mechanic recibe 403 en administración global.
 */
class PartsUi8Test extends TestCase
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

    private function mecanico(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $mechanic = Mechanic::create([
            'name'             => 'Mecánico de prueba',
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    /** Categoría de refacciones (tipo part). La columna type no es fillable, por eso forceFill. */
    private function categoriaParte(string $nombre): Category
    {
        $categoria = new Category();
        $categoria->forceFill(['name' => $nombre, 'slug' => 'p-' . uniqid(), 'type' => Category::TYPE_PART])->save();

        return $categoria;
    }

    private function refaccion(array $datos = []): Part
    {
        return Part::create($datos + [
            'name' => 'Balata', 'sku' => 'SKU-' . uniqid(), 'cost_price' => 80, 'sale_price' => 150,
            'stock' => 5, 'low_stock_threshold' => 2,
        ]);
    }

    private function datosValidos(array $cambios = []): array
    {
        return $cambios + [
            'category_id' => '', 'name' => 'Filtro de aceite', 'sku' => 'FIL-' . uniqid(),
            'cost_price' => 40, 'sale_price' => 75, 'stock' => 3, 'low_stock_threshold' => 1,
        ];
    }

    /** Cuenta consultas SQL del callback (con calentamiento previo para estabilizar). */
    private function contarConsultas(callable $fn): int
    {
        $fn();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    // ---------- Permisos ----------

    public function test_admin_y_owner_acceden_al_catalogo(): void
    {
        foreach ([$this->admin(), $this->owner()] as $usuario) {
            $this->actingAs($usuario)->get(route('parts.index'))->assertOk();
            $this->actingAs($usuario)->get(route('parts.create'))->assertOk();
        }
    }

    public function test_mecanico_recibe_403_en_administracion_de_refacciones(): void
    {
        [$mecanico] = $this->mecanico();
        $parte = $this->refaccion();

        $this->actingAs($mecanico)->get(route('parts.index'))->assertForbidden();
        $this->actingAs($mecanico)->get(route('parts.create'))->assertForbidden();
        $this->actingAs($mecanico)->get(route('parts.edit', $parte))->assertForbidden();
        $this->actingAs($mecanico)->post(route('parts.store'), $this->datosValidos())->assertForbidden();
        $this->actingAs($mecanico)->delete(route('parts.destroy', $parte))->assertForbidden();

        $this->assertDatabaseHas('parts', ['id' => $parte->id]);
    }

    // ---------- Index ----------

    public function test_index_pagina_de_25_y_la_segunda_pagina_trae_el_resto(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->refaccion(['name' => 'Parte ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $this->actingAs($this->admin())->get(route('parts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('parts.total', 30)
                ->where('parts.per_page', 25)
                ->has('parts.data', 25));

        $this->actingAs($this->admin())->get(route('parts.index', ['page' => 2]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('parts.current_page', 2)
                ->has('parts.data', 5));
    }

    public function test_busqueda_por_nombre_y_por_sku(): void
    {
        $this->refaccion(['name' => 'Balata delantera', 'sku' => 'BAL-100']);
        $this->refaccion(['name' => 'Filtro de aceite', 'sku' => 'FIL-200']);

        $this->actingAs($this->admin())->get(route('parts.index', ['search' => 'balata']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('parts.total', 1)
                ->where('parts.data.0.name', 'Balata delantera'));

        $this->actingAs($this->admin())->get(route('parts.index', ['search' => 'FIL-200']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('parts.total', 1)
                ->where('parts.data.0.sku', 'FIL-200'));
    }

    public function test_comodines_del_usuario_no_cambian_la_busqueda(): void
    {
        $this->refaccion(['name' => 'Balata', 'sku' => 'BAL-1']);

        $this->actingAs($this->admin())->get(route('parts.index', ['search' => '%']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('parts.total', 0));
    }

    public function test_filtro_de_categoria_y_query_string_al_paginar(): void
    {
        $frenos = $this->categoriaParte('Frenos');
        $filtros = $this->categoriaParte('Filtros');
        for ($i = 1; $i <= 26; $i++) {
            $this->refaccion(['name' => 'Balata ' . $i, 'category_id' => $frenos->id]);
        }
        $this->refaccion(['name' => 'Filtro aire', 'category_id' => $filtros->id]);

        $this->actingAs($this->admin())->get(route('parts.index', ['category_id' => $frenos->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('parts.total', 26)
                ->where('filters.category_id', (string) $frenos->id)
                ->where('parts.next_page_url', fn ($url) => str_contains((string) $url, 'category_id=' . $frenos->id)));

        $this->actingAs($this->admin())->get(route('parts.index', ['search' => 'Balata']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('parts.total', 26)
                ->where('parts.next_page_url', fn ($url) => str_contains((string) $url, 'search=Balata')));
    }

    public function test_index_carga_solo_categoria_y_no_crece_con_el_catalogo(): void
    {
        $categoria = $this->categoriaParte('Motor');
        $this->refaccion(['category_id' => $categoria->id]);
        $pocas = $this->contarConsultas(fn () => $this->actingAs($this->admin())->get(route('parts.index')));

        for ($i = 0; $i < 20; $i++) {
            $this->refaccion(['category_id' => $categoria->id]);
        }
        $muchas = $this->contarConsultas(fn () => $this->actingAs($this->admin())->get(route('parts.index')));

        $this->assertSame($pocas, $muchas, 'El listado no debe ejecutar consultas por cada refacción.');
    }

    // ---------- Create ----------

    public function test_create_valido_guarda_la_refaccion(): void
    {
        $datos = $this->datosValidos(['name' => 'Bujía', 'sku' => 'BUJ-9']);

        $this->actingAs($this->admin())->post(route('parts.store'), $datos)
            ->assertRedirect(route('parts.index'));

        $this->assertDatabaseHas('parts', ['sku' => 'BUJ-9', 'name' => 'Bujía', 'stock' => 3]);
    }

    public function test_create_rechaza_sku_duplicado_stock_y_umbral_negativos(): void
    {
        $this->refaccion(['sku' => 'DUP-1']);

        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosValidos(['sku' => 'DUP-1']))
            ->assertSessionHasErrors('sku');

        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosValidos(['stock' => -1]))
            ->assertSessionHasErrors('stock');

        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosValidos(['low_stock_threshold' => -1]))
            ->assertSessionHasErrors('low_stock_threshold');
    }

    public function test_create_rechaza_precios_negativos_nombre_faltante_y_categoria_que_no_es_de_refacciones(): void
    {
        $servicio = new Category();
        $servicio->forceFill(['name' => 'Mano de obra', 'slug' => 'srv-' . uniqid(), 'type' => 'service'])->save();

        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosValidos(['cost_price' => -1]))
            ->assertSessionHasErrors('cost_price');

        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosValidos(['sale_price' => -5]))
            ->assertSessionHasErrors('sale_price');

        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosValidos(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin())->post(route('parts.store'), $this->datosValidos(['category_id' => $servicio->id]))
            ->assertSessionHasErrors('category_id');
    }

    // ---------- Edit ----------

    public function test_edit_valido_actualiza_la_refaccion(): void
    {
        $parte = $this->refaccion(['name' => 'Original']);

        $this->actingAs($this->admin())->put(route('parts.update', $parte), $this->datosValidos([
            'name' => 'Editada', 'sku' => $parte->sku, 'stock' => 9,
        ]))->assertRedirect(route('parts.index'));

        $this->assertDatabaseHas('parts', ['id' => $parte->id, 'name' => 'Editada', 'stock' => 9]);
    }

    public function test_edit_permite_conservar_el_propio_sku_y_rechaza_el_de_otra_refaccion(): void
    {
        $propia = $this->refaccion(['sku' => 'PROPIO-1']);
        $otra = $this->refaccion(['sku' => 'AJENO-1']);

        $this->actingAs($this->admin())->put(route('parts.update', $propia), $this->datosValidos([
            'sku' => 'PROPIO-1',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin())->put(route('parts.update', $propia), $this->datosValidos([
            'sku' => 'AJENO-1',
        ]))->assertSessionHasErrors('sku');

        $this->assertDatabaseHas('parts', ['id' => $otra->id, 'sku' => 'AJENO-1']);
    }

    public function test_edit_rechaza_stock_negativo(): void
    {
        $parte = $this->refaccion(['stock' => 4]);

        $this->actingAs($this->admin())->put(route('parts.update', $parte), $this->datosValidos([
            'sku' => $parte->sku, 'stock' => -2,
        ]))->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('parts', ['id' => $parte->id, 'stock' => 4]);
    }

    // ---------- Delete ----------

    public function test_delete_sin_uso_elimina_la_refaccion(): void
    {
        $parte = $this->refaccion();

        $this->actingAs($this->admin())->delete(route('parts.destroy', $parte))
            ->assertRedirect(route('parts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('parts', ['id' => $parte->id]);
    }

    public function test_delete_con_historial_bloquea_sin_error_sql_y_conserva_el_item(): void
    {
        $parte = $this->refaccion(['stock' => 4]);
        $orden = RepairOrder::factory()->create([
            'status_id' => RepairOrderStatus::firstOrCreate(['slug' => 'recibido'], ['name' => 'Recibido', 'color_class' => 'bg-gray-500'])->id,
        ]);
        $item = RepairOrderItem::factory()->create([
            'repair_order_id' => $orden->id, 'part_id' => $parte->id, 'type' => 'part',
            'quantity' => 1, 'unit_price' => 150, 'subtotal' => 150,
        ]);

        $this->actingAs($this->admin())->delete(route('parts.destroy', $parte))
            ->assertRedirect(route('parts.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('parts', ['id' => $parte->id]);
        $this->assertDatabaseHas('repair_order_items', ['id' => $item->id, 'part_id' => $parte->id]);
    }

    // ---------- Regresión de precios para mecánico ----------

    public function test_mecanico_en_su_orden_no_recibe_costos_de_refacciones(): void
    {
        [$mecanico, $mechanic] = $this->mecanico();
        $this->refaccion(['stock' => 5, 'cost_price' => 80, 'sale_price' => 150]);
        $orden = RepairOrder::factory()->create([
            'mechanic_id' => $mechanic->id,
            'status_id' => RepairOrderStatus::firstOrCreate(['slug' => 'recibido'], ['name' => 'Recibido', 'color_class' => 'bg-gray-500'])->id,
        ]);

        $response = $this->actingAs($mecanico)->get(route('repair-orders.show', $orden))->assertOk();

        $partes = $response->viewData('page')['props']['parts'];
        $this->assertNotEmpty($partes);
        $this->assertArrayNotHasKey('cost_price', $partes[0]);
        $this->assertArrayNotHasKey('sale_price', $partes[0]);
    }
}
