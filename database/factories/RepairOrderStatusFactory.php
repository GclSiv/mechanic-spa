<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RepairOrderStatus>
 */
class RepairOrderStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->word(),
            'slug'        => $this->faker->unique()->slug(2),
            'color_class' => 'bg-gray-500',
        ];
    }
}
