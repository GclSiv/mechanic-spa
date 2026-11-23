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

class HistorialBitacoraTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Mechanic} */
    private function mecanico(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MECHANIC]);

        $mechanic = Mechanic::create([
            'name'              => 'Mecánico de prueba',
            'email'             => 'mecanico@prueba.test',
            'phone'             => '9511234567',
            'commission_rate'   => 12.5,
            'user_id'           => $user->id,
            'gender_id'         => Gender::query()->firstOrCreate(['name' => 'Masculino'])->id,
            'mechanic_type_id'  => MechanicType::query()->firstOrCreate(['name' => 'General'])->id,
        ]);

        return [$user, $mechanic];
    }

    private function ordenEn(string $slug, ?int $mechanicId = null): RepairOrder
    {
        $status = RepairOrderStatus::firstOrCreate(['slug' => $slug], ['name' => $slug, 'color_class' => 'bg-gray-500']);

        return RepairOrder::factory()->create(['status_id' => $status->id, 'mechanic_id' => $mechanicId]);
    }

    public function test_mecanico_ve_solo_el_nombre_del_autor_de_cada_nota(): void
    {
        [$user, $mechanic] = $this->mecanico();
        $orden = $this->ordenEn('en-reparacion', $mechanic->id);
        FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $mechanic->id, 'description' => 'Mía', 'date' => '2026-11-22 10:00:00']);

        $this->actingAs($user)
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.follow_ups.0.mechanic.name', 'Mecánico de prueba')
                ->missing('orden.follow_ups.0.mechanic.email')
                ->missing('orden.follow_ups.0.mechanic.phone')
                ->missing('orden.follow_ups.0.mechanic.commission_rate')
                ->missing('orden.follow_ups.0.mechanic.user_id'));
    }

    public function test_admin_recibe_la_ficha_completa_del_autor(): void
    {
        [, $mechanic] = $this->mecanico();
        $orden = $this->ordenEn('en-reparacion', $mechanic->id);
        FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $mechanic->id, 'description' => 'Nota', 'date' => '2026-11-22 10:00:00']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.follow_ups.0.mechanic.name', 'Mecánico de prueba')
                ->has('orden.follow_ups.0.mechanic.commission_rate')
                ->has('orden.follow_ups.0.mechanic.email'));
    }

    public function test_las_notas_se_entregan_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        [, $mechanic] = $this->mecanico();
        $orden = $this->ordenEn('en-reparacion', $mechanic->id);
        FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $mechanic->id, 'description' => 'Antigua', 'date' => '2026-11-01 09:00:00']);
        FollowUp::create(['repair_order_id' => $orden->id, 'mechanic_id' => $mechanic->id, 'description' => 'Reciente', 'date' => '2026-11-20 09:00:00']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('repair-orders.show', $orden))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orden.follow_ups.0.description', 'Reciente')
                ->where('orden.follow_ups.1.description', 'Antigua'));
    }
}
