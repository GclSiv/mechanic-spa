<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\RepairOrderStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RepairOrder>
 */
class RepairOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio'                => 'OR-' . $this->faker->unique()->numerify('######'),
            'client_id'            => Client::factory(),
            'vehicle_id'           => Vehicle::factory(),
            'mechanic_id'          => null,
            'recepcion_id'         => null,
            'status_id'            => RepairOrderStatus::factory(),
            'problem_description'  => $this->faker->sentence(),
            'estimated_cost'       => 0,
            'entry_date'           => now()->toDateString(),
            'delivery_date'        => null,
            'needs_quote'          => false,
            'is_paid'              => false,
        ];
    }
}
