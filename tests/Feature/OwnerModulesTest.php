<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_open_the_owner_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('owner.modules.index'))->assertForbidden();
    }

    public function test_owner_can_open_the_owner_panel(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get(route('owner.modules.index'))->assertOk();
    }

    public function test_owner_counts_as_admin_for_the_workshop(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->assertTrue($owner->isAdmin());
        $this->assertTrue($owner->isOwner());
    }

    public function test_modules_are_enabled_by_default(): void
    {
        $this->assertTrue(Modules::enabled('caja'));
        $this->assertTrue(Modules::enabled('reportes'));
    }

    public function test_disabling_a_module_hides_its_route_for_admins(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)->put(route('owner.modules.update'), [
            'modules' => ['caja' => false, 'reportes' => true, 'importar_clientes' => true],
        ])->assertRedirect();

        $this->assertFalse(Modules::enabled('caja'));

        $this->actingAs($admin)->get('/cuentas')->assertNotFound();
    }

    public function test_core_modules_are_not_affected_by_the_panel(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        // "recepcion" no está en config/modules.php: siempre activo
        $this->assertTrue(Modules::enabled('recepcion'));
        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }
}
