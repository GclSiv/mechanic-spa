<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recepcion>
 */
class RecepcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id'  => Client::factory(),
            'vehicle_id' => Vehicle::factory(),
            'fuel_level' => $this->faker->randomElement(['Vacío', '1/4', '1/2', '3/4', 'Lleno']),
            'miles'      => (string) $this->faker->numberBetween(10000, 250000),
            'symptoms'   => $this->faker->sentence(),
            'witnesses'  => [],
            'inventory'  => [],
            'photos'     => null,
            'status'     => 'Pendiente',
        ];
    }
}
