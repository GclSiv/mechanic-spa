<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'brand'     => $this->faker->randomElement(['Toyota', 'Nissan', 'Ford', 'Chevrolet']),
            'model'     => $this->faker->randomElement(['Hilux', 'Versa', 'Ranger', 'Aveo']),
            'year'      => $this->faker->numberBetween(2010, 2025),
            'color'     => $this->faker->safeColorName(),
            'plate'     => strtoupper($this->faker->unique()->bothify('???-###')),
            'vin'       => strtoupper($this->faker->unique()->bothify('?????????????????')),
            'notes'     => null,
        ];
    }
}
