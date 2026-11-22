<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function configurar(array $datos): Setting
    {
        $settings = Setting::current();
        $settings->update($datos);

        return $settings->fresh();
    }

    private function conLogo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/prueba.png', base64_decode(self::PNG_1X1));
        $this->configurar(['logo_path' => 'logos/prueba.png']);
    }

    public function test_sin_configuracion_el_nombre_neutral_es_taller(): void
    {
        Setting::query()->delete();

        $this->assertSame('Taller', Setting::current()->company_name);
    }

    public function test_branding_compartido_usa_el_nombre_configurado(): void
    {
        $this->configurar(['company_name' => 'Taller Norte']);

        $response = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();

        $this->assertSame('Taller Norte', $response->inertiaProps('settings')['company_name']);
    }

    public function test_welcome_no_contiene_marcas_heredadas(): void
    {
        $this->configurar(['company_name' => 'Taller Norte']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Taller Norte')
            ->assertDontSee('CarbaFix')
            ->assertDontSee('JK Automotive')
            ->assertDontSee('JKAutomotive');
    }

    public function test_paginas_publicas_no_contienen_jk_automotive(): void
    {
        $this->configurar(['company_name' => 'Taller Norte']);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('JK Automotive')
            ->assertDontSee('CarbaFix');
    }

    public function test_props_publicos_y_compartidos_solo_incluyen_branding_y_contacto(): void
    {
        $this->configurar([
            'company_name'   => 'Taller Norte',
            'license_number' => 'LIC-SECRETA-123',
            'clauses'        => 'CLAUSULA-INTERNA-XYZ',
        ]);

        $welcome = $this->get('/')->assertOk()->assertDontSee('LIC-SECRETA-123')->assertDontSee('CLAUSULA-INTERNA-XYZ');
        $this->assertSame(
            ['company_name', 'logo_url', 'phone', 'email', 'address'],
            array_keys($welcome->inertiaProps('settings'))
        );

        $dashboard = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk()->assertDontSee('LIC-SECRETA-123');
        $this->assertSame(
            ['company_name', 'logo_url', 'primary_color', 'secondary_color'],
            array_keys($dashboard->inertiaProps('settings'))
        );
    }

    public function test_guest_recibe_logo_y_nombre_del_branding(): void
    {
        $this->configurar(['company_name' => 'Taller Norte']);

        $sinLogo = $this->get('/login')->assertOk();
        $this->assertNull($sinLogo->inertiaProps('logo_url'));
        $this->assertSame('Taller Norte', $sinLogo->inertiaProps('settings')['company_name']);

        $this->conLogo();

        $conLogo = $this->get('/login')->assertOk();
        $this->assertStringContainsString('/logo-taller', (string) $conLogo->inertiaProps('logo_url'));
    }

    public function test_el_logo_solo_se_sirve_si_existe(): void
    {
        $this->get('/logo-taller')->assertNotFound();

        $this->conLogo();

        $this->get('/logo-taller')->assertOk();
    }

    public function test_sin_logo_no_hay_logo_por_defecto(): void
    {
        $this->assertNull(Setting::current()->logoFilePath());
        $this->assertNull(Setting::current()->logoUrl());
    }
}
