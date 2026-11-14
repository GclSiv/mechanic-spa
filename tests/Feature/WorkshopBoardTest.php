<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_repair_status_exists_once(): void
    {
        $this->assertDatabaseHas('repair_order_statuses', ['slug' => 'en-reparacion']);
        $this->assertSame(1, \App\Models\RepairOrderStatus::where('slug', 'en-reparacion')->count());
    }

    public function test_admin_can_open_the_workshop_board(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('repair-orders.index'))
            ->assertOk();
    }
}
