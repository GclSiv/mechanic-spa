<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Gender;
use App\Models\Mechanic;
use App\Models\MechanicType;
use App\Models\RepairOrder;
use App\Models\RepairOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SharedAuthPropsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mechanic} */
    private function mecanico(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'             => 'Mecánico de prueba',
            'email'            => 'mecanico@prueba.test',
            'phone'            => '9511234567',
            'commission_rate'  => 12.5,
            'user_id'          => $user->id,
            'gender_id'        => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id' => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function ordenEn(string $slug, ?int $mechanicId = null): RepairOrder
    {
        $status = RepairOrderStatus::firstOrCreate(['slug' => $slug], ['name' => $slug, 'color_class' => 'bg-gray-500']);

        return RepairOrder::factory()->create(['status_id' => $status->id, 'mechanic_id' => $mechanicId]);
    }

    public function test_sesion_mecanico_no_recibe_su_ficha_ni_comision_en_auth_user(): void
    {
        [$user, $mechanic] = $this->mecanico();
        $orden = $this->ordenEn('en-reparacion', $mechanic->id);

        // Cargar la relación mechanic en la petición era lo que la filtraba a auth.user.
        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', $user->name)
                ->missing('auth.user.mechanic')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }

    public function test_sesion_mecanico_ve_solo_los_campos_minimos_del_mecanico_asignado(): void
    {
        [$user, $mechanic] = $this->mecanico();
        $orden = $this->ordenEn('en-reparacion', $mechanic->id);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.mechanic.name', 'Mecánico de prueba')
                ->missing('orden.mechanic.commission_rate')
                ->missing('orden.mechanic.email')
                ->missing('orden.mechanic.phone'));
    }

    public function test_admin_conserva_identidad_de_usuario_sin_password(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $orden = $this->ordenEn('en-reparacion');

        $this->actingAs($admin)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.id', $admin->id)
                ->where('auth.user.email', $admin->email)
                ->missing('auth.user.password'));
    }

    public function test_historial_conserva_fotos_de_notas_antiguas_para_el_mecanico(): void
    {
        [$user, $mechanic] = $this->mecanico();
        $orden = $this->ordenEn('en-reparacion', $mechanic->id);
        FollowUp::create([
            'repair_order_id' => $orden->id,
            'mechanic_id'     => $mechanic->id,
            'description'     => 'Nota con evidencia antigua',
            'date'            => '2026-11-01 09:00:00',
            'evidence_photos' => ['follow_ups/antigua.jpg'],
        ]);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.follow_ups.0.evidence_photos.0', 'follow_ups/antigua.jpg'));
    }
}
