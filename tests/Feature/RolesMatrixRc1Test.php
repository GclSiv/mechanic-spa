<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RC1: matriz de acceso del backend para pantallas administrativas.
 * Invitado: redirección al login. Mecánico autenticado: 403. Admin/owner: acceso.
 */
class RolesMatrixRc1Test extends TestCase
{
    use RefreshDatabase;

    /** Pantallas solo para admin/owner que no reciben parámetros. */
    public static function rutasSoloAdministracion(): array
    {
        return [
            'clientes' => ['clients.index'],
            'recepciones' => ['recepcion.index'],
            'nueva recepción' => ['recepcion.create'],
            'lista de órdenes' => ['repair-orders.lista'],
            'cuentas' => ['finance.index'],
            'cotizaciones seguimiento' => ['quotes.follow-up'],
            'exportación de órdenes' => ['export.ordenes'],
            'mecánicos' => ['mechanics.index'],
            'servicios' => ['services.index'],
            'herramientas' => ['tools.index'],
            'refacciones' => ['parts.index'],
            'escáner de refacciones' => ['parts.scan'],
            'categorías' => ['categories.index'],
            'configuración' => ['settings.edit'],
            'reportes' => ['reports.index'],
            'reporte PDF de ingresos' => ['reports.pdf'],
            'reporte de mecánicos' => ['reports.mechanics'],
            'importar clientes' => ['clients.import.index'],
            'módulos del dueño' => ['owner.modules.index'],
        ];
    }

    #[DataProvider('rutasSoloAdministracion')]
    public function test_mecanico_no_accede_a_pantallas_administrativas(string $ruta): void
    {
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)->get(route($ruta))->assertForbidden();
    }

    #[DataProvider('rutasSoloAdministracion')]
    public function test_invitado_es_redirigido_al_login(string $ruta): void
    {
        $this->get(route($ruta))->assertRedirectContains('login');
    }

    public function test_mecanico_no_ve_el_listado_de_clientes_ni_recepciones_por_url_directa(): void
    {
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)->get('/clientes')->assertForbidden();
        $this->actingAs($mecanico)->get('/recepciones')->assertForbidden();
        $this->actingAs($mecanico)->get('/cuentas')->assertForbidden();
    }

    public function test_importar_clientes_admin_y_owner_acceden(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($admin)->get(route('clients.import.index'))->assertOk();
        $this->actingAs($owner)->get(route('clients.import.index'))->assertOk();
    }

    public function test_plantilla_de_importacion_requiere_admin_y_no_es_publica(): void
    {
        $this->get(route('clients.import.template'))->assertRedirectContains('login');

        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);
        $this->actingAs($mecanico)->get(route('clients.import.template'))->assertForbidden();
    }
}
