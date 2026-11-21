<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EMPRESA-1.2: pantalla visual de configuración de empresa en /configuracion.
 */
class EmpresaConfiguracionPantallaTest extends TestCase
{
    use RefreshDatabase;

    private function datosValidos(array $extra = []): array
    {
        return array_merge([
            'company_name'        => 'Taller de prueba',
            'address'             => 'Dirección comercial de prueba',
            'phone'               => '9510000000',
            'license_number'      => 'LIC-1',
            'primary_color'       => '#10213E',
            'secondary_color'     => '#EE2857',
            'clauses'             => 'Términos de prueba',
            'warranty_service_days' => 30,
            'warranty_parts_days'   => 90,
        ], $extra);
    }

    public function test_admin_ve_la_pantalla_de_empresa_con_la_configuracion(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Edit')
                ->has('settings')
                ->where('settings.singleton_key', Setting::SINGLETON_KEY)
            );
    }

    public function test_owner_ve_la_pantalla_de_empresa(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Edit')->has('settings'));
    }

    public function test_mecanico_no_puede_abrir_la_pantalla_de_empresa(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MECHANIC]))
            ->get(route('settings.edit'))
            ->assertForbidden();
    }

    public function test_rechaza_sitio_web_invalido(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('settings.update'), $this->datosValidos(['website' => 'no es una url']))
            ->assertSessionHasErrors('website');
    }

    public function test_acepta_sitio_web_valido(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('settings.update'), $this->datosValidos(['website' => 'https://taller.example.com']))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://taller.example.com', Setting::current()->website);
    }

    public function test_rechaza_codigo_postal_fiscal_de_longitud_incorrecta(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('settings.update'), $this->datosValidos(['codigo_postal_fiscal' => '1234']))
            ->assertSessionHasErrors('codigo_postal_fiscal');
    }

    public function test_rechaza_codigo_postal_fiscal_con_letras(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('settings.update'), $this->datosValidos(['codigo_postal_fiscal' => '6828A']))
            ->assertSessionHasErrors('codigo_postal_fiscal');
    }

    public function test_acepta_codigo_postal_fiscal_de_cinco_digitos(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('settings.update'), $this->datosValidos(['codigo_postal_fiscal' => '68287']))
            ->assertSessionHasNoErrors();

        $this->assertSame('68287', Setting::current()->codigo_postal_fiscal);
    }
}
