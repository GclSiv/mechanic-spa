<?php

namespace Database\Factories;

use App\Models\RepairOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_order_id' => RepairOrder::factory(),
            'amount'          => $this->faker->randomFloat(2, 100, 2000),
            'payment_method'  => 'Efectivo',
            'notes'           => null,
        ];
    }
}
