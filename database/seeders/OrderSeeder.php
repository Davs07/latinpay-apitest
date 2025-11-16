<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\OrderStatus;
use App\PaymentStatus;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear 5 pedidos pendientes
        Order::factory(5)->create([
            'status' => OrderStatus::PENDING,
        ]);

        // Crear 3 pedidos pagados con un pago exitoso
        Order::factory(3)->create([
            'status' => OrderStatus::PAID,
        ])->each(function ($order) {
            Payment::factory()->create([
                'order_id' => $order->id,
                'amount' => $order->amount,
                'status' => PaymentStatus::SUCCESS,
            ]);
        });

        // Crear 2 pedidos fallidos con intentos de pago fallidos
        Order::factory(2)->create([
            'status' => OrderStatus::FAILED,
        ])->each(function ($order) {
            Payment::factory(2)->create([
                'order_id' => $order->id,
                'amount' => $order->amount,
                'status' => PaymentStatus::FAILED,
            ]);
        });
    }
}
