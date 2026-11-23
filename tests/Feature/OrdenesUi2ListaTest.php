<?php

namespace Tests\Feature;

use App\Models\RepairOrder;
use App\Models\RepairOrderItem;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * IMPLEMENTACIÓN-UI-2 · Órdenes (listado admin/owner).
 * Indicadores del encabezado: conteos del taller completo, independientes de los filtros.
 * Autorización pendiente: métrica derivada, no es estado de la orden.
 */
class OrdenesUi2ListaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orden(string $slug): RepairOrder
    {
        $status = RepairOrderStatus::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500']
        );

        return RepairOrder::factory()->create(['tax_rate' => 16, 'status_id' => $status->id]);
    }

    private function conceptoPendiente(RepairOrder $orden): void
    {
        RepairOrderItem::create([
            'repair_order_id' => $orden->id, 'type' => 'labor', 'description' => 'Mano de obra',
            'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500,
        ]);
    }

    public function test_resumen_cuenta_los_cinco_estados_operativos_del_taller(): void
    {
        $this->orden('recibido');
        $this->orden('diagnostico');
        $this->orden('diagnostico');
        $this->orden('espera-piezas');
        $this->orden('reparado');
        $this->orden('entregado'); // fuera del resumen operativo

        $this->actingAs($this->admin())
            ->get(route('repair-orders.lista'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('resumen', [
                    'recibido'      => 1,
                    'diagnostico'   => 2,
                    'espera-piezas' => 1,
                    'en-reparacion' => 0,
                    'reparado'      => 1,
                ])
            );
    }

    public function test_autorizacion_pendiente_es_derivada_y_excluye_cerradas(): void
    {
        $pendiente = $this->orden('en-reparacion');
        $this->conceptoPendiente($pendiente);
        $this->conceptoPendiente($pendiente); // dos conceptos: cuenta una sola orden

        $entregada = $this->orden('entregado');
        $this->conceptoPendiente($entregada);

        $this->orden('recibido'); // sin conceptos: no pendiente

        $this->actingAs($this->admin())
            ->get(route('repair-orders.lista'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('autorizacionPendiente', 1)
            );
    }

    public function test_resumen_y_autorizacion_no_cambian_con_los_filtros(): void
    {
        $this->orden('espera-piezas');
        $this->orden('espera-piezas');
        $this->orden('reparado');
        $this->conceptoPendiente($this->orden('diagnostico'));

        $this->actingAs($this->admin())
            ->get(route('repair-orders.lista', ['estado' => 'reparado']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('resumen.espera-piezas', 2)
                ->where('resumen.reparado', 1)
                ->where('resumen.diagnostico', 1)
                ->where('autorizacionPendiente', 1)
                ->where('filtros.estado', 'reparado')
                ->has('ordenes.data', 1)
            );
    }

    public function test_filtro_de_estado_sigue_funcionando_con_query_string_existente(): void
    {
        $this->orden('espera-piezas');
        $this->orden('espera-piezas');
        $this->orden('reparado');

        $this->actingAs($this->admin())
            ->get(route('repair-orders.lista', ['estado' => 'espera-piezas', 'buscar' => '']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('ordenes.data', 2)
                ->where('filtros.estado', 'espera-piezas')
            );
    }
}
