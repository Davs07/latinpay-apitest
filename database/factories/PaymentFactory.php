<?php

namespace Database\Factories;

use App\Models\Order;
use App\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'status' => fake()->randomElement([PaymentStatus::SUCCESS, PaymentStatus::FAILED]),
            'gateway_response' => [
                'id' => fake()->randomNumber(),
                'timestamp' => now()->toIso8601String(),
            ],
            'idempotency_key' => null, // Por defecto null, se puede especificar si es necesario
        ];
    }
}
