<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RepairOrderItem>
 */
class RepairOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 100, 5000);
    $qty = $this->faker->numberBetween(1, 3);

    return [
        'repair_order_id' => 1, // Asumimos que la orden #1 existe
        'description' => $this->faker->sentence(3),
        'quantity' => $qty,
        'unit_price' => $price,
        'subtotal' => $price * $qty,
    ];
    }
}