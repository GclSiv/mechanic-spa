<?php

namespace Tests\Feature;

use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * HARDENING-UI-5A: órdenes cerradas (entregado/rechazado) no cambian IVA ni estado
 * y no cuentan en "Por cotizar" del Dashboard.
 */
class ClosedOrderHardeningUi5aTest extends TestCase
{
    use RefreshDatabase;

    private function estado(string $slug): RepairOrderStatus
    {
        return RepairOrderStatus::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'color_class' => 'bg-gray-500']
        );
    }

    private function orden(string $slug, bool $needsQuote = false): RepairOrder
    {
        return RepairOrder::factory()->create([
            'status_id'   => $this->estado($slug)->id,
            'needs_quote' => $needsQuote,
            'tax_rate'    => 16,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public static function estadosCerrados(): array
    {
        return [['entregado'], ['rechazado']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('estadosCerrados')]
    public function test_admin_no_cambia_iva_de_orden_cerrada(string $slug): void
    {
        $order = $this->orden($slug);

        $this->actingAs($this->admin())
            ->patch(route('repair-orders.tax.update', $order), ['tax_rate' => 8])
            ->assertForbidden();

        $this->assertEquals(16, (float) $order->fresh()->tax_rate);
    }

    public function test_iva_de_orden_abierta_sigue_cambiando(): void
    {
        $order = $this->orden('diagnostico');

        $this->actingAs($this->admin())
            ->patch(route('repair-orders.tax.update', $order), ['tax_rate' => 8])
            ->assertRedirect();

        $this->assertEquals(8, (float) $order->fresh()->tax_rate);
    }

    public function test_dashboard_por_cotizar_cuenta_orden_abierta(): void
    {
        $this->orden('diagnostico', needsQuote: true);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.porCotizar', 1));
    }

    public function test_dashboard_por_cotizar_excluye_entregada_y_rechazada(): void
    {
        $this->orden('entregado', needsQuote: true);
        $this->orden('rechazado', needsQuote: true);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.porCotizar', 0));
    }
}
