<?php

namespace Tests\Feature;

use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UI-6A: listado de recepciones con paginación de servidor y vista de detalle sin orden.
 */
class RecepcionesUi6aTest extends TestCase
{
    use RefreshDatabase;

    private const PER_PAGE = 25;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function filasDe($respuesta): array
    {
        return $respuesta->viewData('page')['props']['recepciones']['data'];
    }

    private function conOrden(Recepcion $recepcion): RepairOrder
    {
        $estado = RepairOrderStatus::firstOrCreate(['slug' => 'diagnostico'], ['name' => 'Diagnóstico', 'color_class' => 'x']);

        return RepairOrder::factory()->create([
            'recepcion_id' => $recepcion->id,
            'status_id'    => $estado->id,
        ]);
    }

    // ── Acceso ──────────────────────────────────────────────────────────

    public function test_admin_puede_acceder_al_listado(): void
    {
        $this->actingAs($this->admin())
            ->get(route('recepcion.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Recepciones/Index'));
    }

    public function test_owner_puede_acceder_al_listado(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('recepcion.index'))
            ->assertOk();
    }

    public function test_mecanico_recibe_403_en_el_listado(): void
    {
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)
            ->get(route('recepcion.index'))
            ->assertForbidden();
    }

    // ── Paginación ──────────────────────────────────────────────────────

    public function test_listado_entrega_solo_una_pagina_con_metadatos_del_paginator(): void
    {
        Recepcion::factory()->count(self::PER_PAGE + 5)->create();

        $respuesta = $this->actingAs($this->admin())->get(route('recepcion.index'))->assertOk();

        $props = $respuesta->viewData('page')['props']['recepciones'];

        $this->assertCount(self::PER_PAGE, $props['data']);
        $this->assertSame(1, $props['current_page']);
        $this->assertSame(2, $props['last_page']);
        $this->assertSame(self::PER_PAGE + 5, $props['total']);
        $this->assertSame(self::PER_PAGE, $props['per_page']);
        $this->assertNotNull($props['next_page_url']);
        $this->assertNull($props['prev_page_url']);
    }

    public function test_pagina_siguiente_devuelve_registros_distintos_y_orden_descendente(): void
    {
        Recepcion::factory()->count(self::PER_PAGE + 5)->create();
        $admin = $this->admin();

        $pagina1 = $this->filasDe($this->actingAs($admin)->get(route('recepcion.index')));
        $pagina2 = $this->filasDe($this->actingAs($admin)->get(route('recepcion.index', ['page' => 2])));

        $ids1 = array_column($pagina1, 'id');
        $ids2 = array_column($pagina2, 'id');

        $this->assertCount(self::PER_PAGE, $ids1);
        $this->assertCount(5, $ids2);
        $this->assertEmpty(array_intersect($ids1, $ids2));
        $this->assertSame($ids1, collect($ids1)->sortDesc()->values()->all());
        $this->assertLessThan(min($ids1), max($ids2));
    }

    public function test_numero_de_consultas_no_crece_con_el_numero_de_registros(): void
    {
        $admin = $this->admin();

        // Ambas mediciones dejan una orden en la página 1, para que las mismas relaciones se carguen en los dos casos.
        Recepcion::factory()->count(3)->create();
        $this->conOrden(Recepcion::latest('id')->first());
        $this->actingAs($admin)->get(route('recepcion.index'))->assertOk(); // calentamiento de sesión y configuración

        $pocos = $this->consultasDelListado($admin);

        Recepcion::factory()->count(30)->create();
        $this->conOrden(Recepcion::latest('id')->first());

        $muchos = $this->consultasDelListado($admin);

        $this->assertSame($pocos, $muchos);
    }

    private function consultasDelListado(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('recepcion.index'))->assertOk();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    // ── Relación con la orden ───────────────────────────────────────────

    public function test_recepcion_sin_orden_trae_repair_order_nulo(): void
    {
        $sin = Recepcion::factory()->create();

        $filas = $this->filasDe($this->actingAs($this->admin())->get(route('recepcion.index')));
        $fila = collect($filas)->firstWhere('id', $sin->id);

        $this->assertNotNull($fila);
        $this->assertNull($fila['repair_order']);
    }

    public function test_recepcion_con_orden_trae_estado_suficiente_para_enlazar(): void
    {
        $con = Recepcion::factory()->create();
        $orden = $this->conOrden($con);

        $filas = $this->filasDe($this->actingAs($this->admin())->get(route('recepcion.index')));
        $fila = collect($filas)->firstWhere('id', $con->id);

        $this->assertSame($orden->id, $fila['repair_order']['id']);
        $this->assertSame('diagnostico', $fila['repair_order']['status']['slug']);
    }

    // ── Show ────────────────────────────────────────────────────────────

    public function test_show_sin_orden_renderiza_el_detalle(): void
    {
        $sin = Recepcion::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('recepcion.show', $sin))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Recepciones/Show')
                ->where('recepcion.id', $sin->id));
    }

    public function test_show_con_orden_redirige_al_expediente_sin_crear_otra_orden(): void
    {
        $con = Recepcion::factory()->create();
        $orden = $this->conOrden($con);

        $this->actingAs($this->admin())
            ->get(route('recepcion.show', $con))
            ->assertRedirect(route('repair-orders.show', $orden));

        $this->assertSame(1, RepairOrder::where('recepcion_id', $con->id)->count());
    }

    public function test_generate_order_no_duplica_la_orden_existente(): void
    {
        $con = Recepcion::factory()->create();
        $orden = $this->conOrden($con);

        $this->actingAs($this->admin())
            ->post(route('recepcion.generate-order', $con))
            ->assertRedirect(route('repair-orders.show', $orden));

        $this->assertSame(1, RepairOrder::where('recepcion_id', $con->id)->count());
    }
}
