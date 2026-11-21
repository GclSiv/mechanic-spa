<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EMPRESA-1.1: configuración institucional centralizada sobre la tabla settings existente.
 */
class EmpresaConfiguracionTest extends TestCase
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

    public function test_solo_puede_existir_una_configuracion_empresarial(): void
    {
        $primera = Setting::current();
        $segunda = Setting::current();

        $this->assertSame(1, Setting::count());
        $this->assertSame($primera->id, $segunda->id);
    }

    public function test_se_recupera_con_el_mecanismo_central(): void
    {
        $guardada = Setting::query()->first() ?? Setting::create(['company_name' => 'Empresa X', 'tax_type' => 'MX', 'iva' => 16]);
        $guardada->update(['company_name' => 'Empresa X', 'whatsapp' => '9511112233']);

        $actual = Setting::current();

        $this->assertSame($guardada->id, $actual->id);
        $this->assertSame('Empresa X', $actual->company_name);
        $this->assertSame('9511112233', $actual->whatsapp);
    }

    public function test_funciona_cuando_todavia_no_existe_registro(): void
    {
        Setting::query()->delete();

        $config = Setting::current();

        $this->assertSame(1, Setting::count());
        $this->assertSame('Mi taller', $config->company_name);
        $this->assertEquals(16.0, (float) $config->iva);
        $this->assertNull($config->whatsapp);
        $this->assertNull($config->domicilio_fiscal);
    }

    public function test_almacena_datos_comerciales_y_fiscales_cuando_admin_actualiza(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put(route('settings.update'), $this->datosValidos([
            'whatsapp'             => '9513334444',
            'website'              => 'https://ejemplo.mx',
            'razon_social'         => 'Razón social de prueba',
            'rfc'                  => 'XAXX010101000',
            'regimen_fiscal'       => 'Régimen de prueba',
            'codigo_postal_fiscal' => '68287',
            'domicilio_fiscal'     => 'Domicilio fiscal de prueba',
        ]))->assertSessionHasNoErrors();

        $config = Setting::current()->fresh();
        $this->assertSame('9513334444', $config->whatsapp);
        $this->assertSame('https://ejemplo.mx', $config->website);
        $this->assertSame('Razón social de prueba', $config->razon_social);
        $this->assertSame('XAXX010101000', $config->rfc);
        $this->assertSame('Régimen de prueba', $config->regimen_fiscal);
        $this->assertSame('68287', $config->codigo_postal_fiscal);
        $this->assertSame('Domicilio fiscal de prueba', $config->domicilio_fiscal);
    }

    public function test_owner_tambien_puede_actualizar_la_configuracion(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->put(route('settings.update'), $this->datosValidos([
            'codigo_postal_fiscal' => '68287',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('68287', Setting::current()->fresh()->codigo_postal_fiscal);
    }

    public function test_rechaza_codigo_postal_fiscal_que_no_tiene_cinco_digitos(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put(route('settings.update'), $this->datosValidos([
            'codigo_postal_fiscal' => '6828',
        ]))->assertSessionHasErrors('codigo_postal_fiscal');

        $this->assertNull(Setting::current()->fresh()->codigo_postal_fiscal);
    }

    public function test_los_datos_fiscales_no_viajan_en_el_prop_global_compartido(): void
    {
        Setting::current()->update([
            'company_name'         => 'Empresa visible',
            'regimen_fiscal'       => 'Régimen privado',
            'codigo_postal_fiscal' => '68287',
            'domicilio_fiscal'     => 'Domicilio privado',
        ]);
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $settings = $this->actingAs($mecanico)
            ->get(route('repair-orders.index'))
            ->assertOk()
            ->inertiaProps('settings');

        $this->assertSame('Empresa visible', $settings['company_name']);
        $this->assertArrayNotHasKey('regimen_fiscal', $settings);
        $this->assertArrayNotHasKey('codigo_postal_fiscal', $settings);
        $this->assertArrayNotHasKey('domicilio_fiscal', $settings);
    }

    public function test_mecanico_no_puede_modificar_la_configuracion(): void
    {
        Setting::current()->update(['company_name' => 'Nombre original']);
        $mecanico = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $this->actingAs($mecanico)
            ->put(route('settings.update'), $this->datosValidos(['company_name' => 'Cambiado por mecánico']))
            ->assertForbidden();

        $this->assertSame('Nombre original', Setting::current()->fresh()->company_name);
    }

    public function test_la_base_de_datos_rechaza_una_segunda_configuracion(): void
    {
        Setting::current();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        \Illuminate\Support\Facades\DB::table('settings')->insert([
            'company_name'  => 'Segunda configuración',
            'tax_type'      => 'MX',
            'iva'           => 16.00,
            'singleton_key' => 'default',
        ]);
    }

    public function test_singleton_key_no_se_puede_cambiar_desde_actualizaciones_masivas(): void
    {
        $config = Setting::current();

        $config->update(['singleton_key' => 'otra', 'company_name' => 'Nombre nuevo']);

        $fresca = Setting::current()->fresh();
        $this->assertSame('default', $fresca->singleton_key);
        $this->assertSame('Nombre nuevo', $fresca->company_name);
        $this->assertSame(1, Setting::count());
    }
}
