<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Part;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * INVENTARIO-2: herramientas y activos, separados de refacciones.
 */
class ToolsTest extends TestCase
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

    private function datos(array $extra = []): array
    {
        return array_merge([
            'category_id'   => Category::where('type', Category::TYPE_TOOL)->value('id'),
            'location_id'   => Location::where('name', 'Almacén')->value('id'),
            'name'          => 'Multímetro digital',
            'brand'         => 'Fluke',
            'model'         => '117',
            'serial_number' => 'SN-0001',
            'internal_code' => 'HER-0001',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 2500.50,
            'status'        => 'disponible',
            'condition'     => 'buena',
            'notes'         => 'Nota de prueba',
        ], $extra);
    }

    public function test_catalogos_iniciales_de_herramientas_y_ubicaciones_existen(): void
    {
        $this->assertGreaterThanOrEqual(5, Category::where('type', Category::TYPE_TOOL)->count());
        $this->assertGreaterThanOrEqual(6, Location::count());
    }

    public function test_las_refacciones_solo_ofrecen_categorias_de_tipo_refaccion(): void
    {
        $categorias = $this->actingAs($this->admin())->get(route('parts.create'))
            ->assertOk()
            ->inertiaProps('categories');

        $this->assertNotEmpty($categorias);
        foreach ($categorias as $categoria) {
            $this->assertSame(Category::TYPE_PART, $categoria['type']);
        }
    }

    public function test_la_pantalla_de_categorias_de_refacciones_no_muestra_categorias_de_herramientas(): void
    {
        $categorias = $this->actingAs($this->admin())->get(route('categories.index'))
            ->assertOk()
            ->inertiaProps('categories');

        $this->assertNotEmpty($categorias);
        foreach ($categorias as $categoria) {
            $this->assertSame(Category::TYPE_PART, $categoria['type']);
        }
        $this->assertSame(
            Category::where('type', Category::TYPE_PART)->count(),
            count($categorias)
        );
    }

    public function test_el_formulario_de_herramientas_recibe_solo_categorias_de_herramienta(): void
    {
        $categorias = $this->actingAs($this->admin())->get(route('tools.create'))
            ->assertOk()
            ->inertiaProps('categories');

        $this->assertCount(Category::where('type', Category::TYPE_TOOL)->count(), $categorias);
        $this->assertGreaterThanOrEqual(5, count($categorias));
        foreach ($categorias as $categoria) {
            $this->assertArrayNotHasKey('type', $categoria, 'El formulario de herramientas solo necesita id y nombre.');
        }
    }

    public function test_admin_puede_listar_crear_y_consultar(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tools.index'))->assertOk();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos())
            ->assertRedirect(route('tools.index'));

        $herramienta = Tool::where('internal_code', 'HER-0001')->firstOrFail();

        $this->actingAs($admin)->get(route('tools.show', $herramienta))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tools/Show'));
    }

    public function test_owner_puede_crear_herramientas(): void
    {
        $this->actingAs($this->owner())->post(route('tools.store'), $this->datos())
            ->assertRedirect(route('tools.index'));

        $this->assertDatabaseHas('tools', ['internal_code' => 'HER-0001', 'status' => 'disponible', 'active' => true]);
    }

    public function test_mecanico_no_puede_administrar_herramientas(): void
    {
        $mecanico = $this->mecanico();

        $this->actingAs($mecanico)->get(route('tools.index'))->assertForbidden();
        $this->actingAs($mecanico)->post(route('tools.store'), $this->datos())->assertForbidden();

        $this->assertDatabaseCount('tools', 0);
    }

    public function test_el_codigo_interno_se_guarda_en_mayusculas(): void
    {
        $this->actingAs($this->admin())->post(route('tools.store'), $this->datos(['internal_code' => ' her-0002 ']));

        $this->assertDatabaseHas('tools', ['internal_code' => 'HER-0002']);
    }

    public function test_rechaza_codigo_interno_duplicado_y_formato_invalido(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos());

        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['serial_number' => 'SN-0002']))
            ->assertSessionHasErrors('internal_code');

        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['internal_code' => 'HER 0009', 'serial_number' => 'SN-0003']))
            ->assertSessionHasErrors('internal_code');
    }

    public function test_rechaza_numero_de_serie_duplicado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos());

        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['internal_code' => 'HER-0002']))
            ->assertSessionHasErrors('serial_number');
    }

    public function test_rechaza_estado_desconocido(): void
    {
        $this->actingAs($this->admin())->post(route('tools.store'), $this->datos(['status' => 'vendida']))
            ->assertSessionHasErrors('status');
    }

    public function test_rechaza_una_categoria_de_refacciones(): void
    {
        $categoriaRefaccion = Category::where('type', Category::TYPE_PART)->value('id');

        $this->actingAs($this->admin())->post(route('tools.store'), $this->datos(['category_id' => $categoriaRefaccion]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_rechaza_costo_negativo_y_fecha_futura(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['purchase_cost' => -10]))
            ->assertSessionHasErrors('purchase_cost');

        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['purchase_date' => now()->addYear()->toDateString()]))
            ->assertSessionHasErrors('purchase_date');
    }

    public function test_actualizar_cambia_ubicacion_y_estado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos());
        $herramienta = Tool::firstOrFail();
        $otraUbicacion = Location::where('name', 'Bahía 1')->value('id');

        $this->actingAs($admin)->put(route('tools.update', $herramienta), $this->datos([
            'location_id' => $otraUbicacion,
            'status'      => 'en_uso',
        ]))->assertRedirect(route('tools.index'));

        $this->assertDatabaseHas('tools', ['id' => $herramienta->id, 'location_id' => $otraUbicacion, 'status' => 'en_uso']);
    }

    public function test_dar_de_baja_es_logico_y_se_puede_revertir(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos());
        $herramienta = Tool::firstOrFail();

        $this->actingAs($admin)->delete(route('tools.destroy', $herramienta))->assertRedirect(route('tools.index'));

        $this->assertDatabaseCount('tools', 1);
        $this->assertDatabaseHas('tools', ['id' => $herramienta->id, 'status' => 'baja', 'active' => false]);

        $this->actingAs($admin)->put(route('tools.update', $herramienta), $this->datos(['status' => 'disponible']));

        $this->assertDatabaseHas('tools', ['id' => $herramienta->id, 'status' => 'disponible', 'active' => true]);
    }

    public function test_el_listado_oculta_las_bajas_salvo_que_se_pidan(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tools.store'), $this->datos());
        $this->actingAs($admin)->post(route('tools.store'), $this->datos(['internal_code' => 'HER-0002', 'serial_number' => 'SN-0002']));
        $this->actingAs($admin)->delete(route('tools.destroy', Tool::where('internal_code', 'HER-0002')->firstOrFail()));

        $activas = $this->actingAs($admin)->get(route('tools.index'))->assertOk()->inertiaProps('tools')['data'];
        $todas   = $this->actingAs($admin)->get(route('tools.index', ['mostrar_bajas' => 1]))->assertOk()->inertiaProps('tools')['data'];

        $this->assertCount(1, $activas);
        $this->assertCount(2, $todas);
    }

    public function test_las_herramientas_no_tocan_refacciones_ni_tienen_stock(): void
    {
        $antes = Part::count();

        $this->actingAs($this->admin())->post(route('tools.store'), $this->datos());

        $this->assertSame($antes, Part::count());
        $this->assertFalse(Schema::hasColumn('tools', 'stock'));
        $this->assertFalse(Schema::hasColumn('tools', 'sale_price'));
        $this->assertFalse(Schema::hasColumn('tools', 'low_stock_threshold'));
    }
}
