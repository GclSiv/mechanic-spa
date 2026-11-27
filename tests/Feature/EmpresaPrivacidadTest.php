<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\Recepcion;
use App\Models\RepairOrder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EMPRESA-1.2 (cierre de privacidad): listas blancas explícitas de información empresarial.
 */
class EmpresaPrivacidadTest extends TestCase
{
    use RefreshDatabase;

    /** Branding global: lo único que reciben todos los usuarios autenticados. */
    private const BRANDING = ['company_name', 'logo_url', 'primary_color', 'secondary_color'];

    /** Contacto público: lo que puede ver un visitante sin sesión en la portada. */
    private const PUBLICO = ['company_name', 'logo_url', 'phone', 'email', 'address'];

    /** Campos que nunca deben viajar a una página de usuario (salvo admin en /configuracion). */
    private const PRIVADOS = [
        'rfc', 'razon_social', 'regimen_fiscal', 'codigo_postal_fiscal', 'domicilio_fiscal',
        'iva', 'tax_type', 'clauses', 'warranty_service_enabled', 'warranty_service_days',
        'warranty_service_km', 'warranty_parts_enabled', 'warranty_parts_days', 'warranty_notes',
        'singleton_key', 'logo_path', 'created_at', 'updated_at', 'id', 'license_number',
    ];

    private function configurarEmpresa(): void
    {
        Setting::current()->update([
            'company_name'         => 'Taller Real',
            'primary_color'        => '#123456',
            'secondary_color'      => '#654321',
            'phone'                => '9511112222',
            'whatsapp'             => '9511113333',
            'email'                => 'contacto@taller.example',
            'website'              => 'https://taller.example',
            'address'              => 'Calle Pública 1',
            'razon_social'         => 'Razón Social Privada SA',
            'rfc'                  => 'XAXX010101000',
            'regimen_fiscal'       => 'Régimen privado',
            'codigo_postal_fiscal' => '68287',
            'domicilio_fiscal'     => 'Domicilio fiscal privado',
            'license_number'       => 'LIC-PRIVADA-99',
            'clauses'              => 'Cláusula privada',
            'iva'                  => 16,
            'tax_type'             => 'MX',
            'warranty_notes'       => 'Nota de garantía privada',
        ]);
    }

    /** @return array{0: User, 1: Mechanic} */
    private function mechanic(): array
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

    /** Compara claves ignorando el orden. */
    private function assertClaves(array $esperadas, array $recibido): void
    {
        $this->assertEqualsCanonicalizing($esperadas, array_keys($recibido));
    }

    private function assertSinClavesPrivadas(array $recibido): void
    {
        foreach (self::PRIVADOS as $campo) {
            $this->assertArrayNotHasKey($campo, $recibido, "El campo [{$campo}] no debe viajar aquí.");
        }
    }

    public function test_prop_global_de_mechanic_contiene_solo_branding(): void
    {
        $this->configurarEmpresa();
        [$user] = $this->mechanic();

        $settings = $this->actingAs($user)->get(route('repair-orders.index'))
            ->assertOk()
            ->inertiaProps('settings');

        $this->assertClaves(self::BRANDING, $settings);
        $this->assertSinClavesPrivadas($settings);
        $this->assertArrayNotHasKey('phone', $settings);
        $this->assertArrayNotHasKey('address', $settings);
    }

    public function test_repair_order_show_de_mechanic_no_recibe_datos_fiscales_ni_modelo_completo(): void
    {
        $this->configurarEmpresa();
        [$user, $mechanic] = $this->mechanic();
        $order = RepairOrder::factory()->create(['mechanic_id' => $mechanic->id]);

        $settings = $this->actingAs($user)->get(route('repair-orders.show', $order))
            ->assertOk()
            ->inertiaProps('settings');

        $this->assertClaves(self::BRANDING, $settings);
        $this->assertArrayNotHasKey('regimen_fiscal', $settings);
        $this->assertArrayNotHasKey('codigo_postal_fiscal', $settings);
        $this->assertArrayNotHasKey('domicilio_fiscal', $settings);
        $this->assertArrayNotHasKey('rfc', $settings);
        $this->assertArrayNotHasKey('razon_social', $settings);
        $this->assertSinClavesPrivadas($settings);
    }

    public function test_portada_sin_sesion_recibe_solo_contacto_publico(): void
    {
        $this->configurarEmpresa();

        $settings = $this->get('/')
            ->assertOk()
            ->inertiaProps('settings');

        $this->assertClaves(self::PUBLICO, $settings);
        $this->assertSinClavesPrivadas($settings);
        $this->assertSame('Taller Real', $settings['company_name']);
        $this->assertSame('9511112222', $settings['phone']);
        $this->assertArrayNotHasKey('website', $settings);
        $this->assertArrayNotHasKey('whatsapp', $settings);
        $this->assertSame('contacto@taller.example', $settings['email']);
        $this->assertSame('Calle Pública 1', $settings['address']);
    }

    public function test_configuracion_admin_conserva_todos_los_campos_para_editar(): void
    {
        $this->configurarEmpresa();

        $settings = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('settings.edit'))
            ->assertOk()
            ->inertiaProps('settings');

        foreach (['rfc', 'razon_social', 'regimen_fiscal', 'codigo_postal_fiscal', 'domicilio_fiscal',
                  'iva', 'clauses', 'license_number', 'singleton_key', 'phone', 'email'] as $campo) {
            $this->assertArrayHasKey($campo, $settings, "Admin debe seguir viendo [{$campo}] para editarlo.");
        }
    }

    public function test_seguimiento_publico_recibe_solo_datos_publicos_de_contacto(): void
    {
        $this->configurarEmpresa();

        $client    = Client::factory()->create();
        $vehicle   = Vehicle::factory()->create(['client_id' => $client->id]);
        $recepcion = Recepcion::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id]);
        $order     = RepairOrder::factory()->create(['recepcion_id' => $recepcion->id]);
        $order->forceFill(['tracking_token' => Str::random(48)])->save();

        $settings = $this->get(route('tracking.show', ['token' => $order->tracking_token]))
            ->assertOk()
            ->inertiaProps('settings');

        // Lista exacta: nombre comercial y contacto público (teléfono y WhatsApp). Nada más de empresa.
        $this->assertClaves(['company_name', 'phone', 'whatsapp'], $settings);
        $this->assertSame('Taller Real', $settings['company_name']);
        $this->assertSame('9511112222', $settings['phone']);
        $this->assertSame('9511113333', $settings['whatsapp']);
        $this->assertArrayNotHasKey('email', $settings);
        $this->assertArrayNotHasKey('address', $settings);
        $this->assertArrayNotHasKey('website', $settings);
        $this->assertSinClavesPrivadas($settings);
    }

    public function test_branding_visual_llega_completo_a_las_paginas_autenticadas(): void
    {
        $this->configurarEmpresa();

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('repair-orders.index'))
            ->assertOk();

        $settings = $response->inertiaProps('settings');

        // AuthenticatedLayout debe mostrar la empresa configurada, nunca un valor heredado.
        $this->assertSame('Taller Real', $settings['company_name']);
        $this->assertNotSame('Taller Demo', $settings['company_name']);
        $this->assertSame('#123456', $settings['primary_color']);
        $this->assertSame('#654321', $settings['secondary_color']);
        $this->assertNull($response->inertiaProps('logo_url'));
    }
}
