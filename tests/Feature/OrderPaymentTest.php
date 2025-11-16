<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\OrderStatus;
use App\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function puede_crear_un_pedido()
    {
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Marco Polo',
            'amount' => 100.50,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer_name',
                    'amount',
                    'status',
                    'payments_count',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Marco Polo',
            'amount' => 100.50,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function puede_procesar_un_pago_exitoso()
    {
        // Fake HTTP para simular respuesta exitosa del gateway
        Http::fake([
            '*lp-test-api-v1.free.beeceptor.com*' => Http::response([
                'id' => 123,
                'createdAt' => now()->toIso8601String(),
            ], 201)
        ]);

        $order = Order::factory()->create([
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'order_id' => $order->id,
                    'amount' => '100.00',
                    'status' => 'success',
                ]
            ]);

        // Verificar que el pedido cambió a paid
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        // Verificar que se creó el pago
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => 'success',
        ]);
    }

    /** @test */
    public function puede_procesar_un_pago_fallido()
    {
        // Fake HTTP para simular respuesta fallida del gateway
        Http::fake([
            '*lp-test-api-v1.free.beeceptor.com*' => Http::response([], 500)
        ]);

        $order = Order::factory()->create([
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ]);

        $response->assertStatus(201);

        // Verificar que el pedido cambió a failed
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'failed',
        ]);

        // Verificar que se creó el pago fallido
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function permite_nuevos_intentos_de_pago_si_falla()
    {
        Http::fake([
            '*lp-test-api-v1.free.beeceptor.com*' => Http::response([
                'id' => 123,
                'createdAt' => now()->toIso8601String(),
            ], 201)
        ]);

        $order = Order::factory()->create([
            'amount' => 100.00,
            'status' => OrderStatus::FAILED,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::FAILED,
            'amount' => 100.00,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertEquals(2, Payment::where('order_id', $order->id)->count());
    }

    /** @test */
    public function puede_listar_pedidos_con_sus_pagos()
    {
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();

        Payment::factory()->create(['order_id' => $order1->id]);
        Payment::factory()->create(['order_id' => $order1->id]);
        Payment::factory()->create(['order_id' => $order2->id]);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'customer_name',
                        'amount',
                        'status',
                        'payments_count',
                        'payments',
                    ]
                ]
            ]);
    }

    /** @test */
    public function valida_que_el_monto_del_pago_coincida_con_el_pedido()
    {
        $order = Order::factory()->create([
            'amount' => 100.00,
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 50.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function retorna_404_si_el_pedido_no_existe()
    {
        $response = $this->postJson('/api/orders/999/payments', [
            'amount' => 100.00,
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function procesa_pago_con_idempotency_key()
    {
        Http::fake([
            '*lp-test-api-v1.free.beeceptor.com*' => Http::response([
                'id' => 123,
                'createdAt' => now()->toIso8601String(),
            ], 201)
        ]);

        $order = Order::factory()->create([
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);

        $idempotencyKey = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(201);

        // Verificar que se guardó el idempotency_key
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'success',
        ]);
    }

    /** @test */
    public function no_duplica_pago_con_mismo_idempotency_key()
    {
        Http::fake([
            '*lp-test-api-v1.free.beeceptor.com*' => Http::response([
                'id' => 123,
                'createdAt' => now()->toIso8601String(),
            ], 201)
        ]);

        $order = Order::factory()->create([
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);

        $idempotencyKey = '550e8400-e29b-41d4-a716-446655440000';

        // Primera petición
        $response1 = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response1->assertStatus(201);
        $paymentId = $response1->json('data.id');

        // Segunda petición con el mismo idempotency key
        $response2 = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response2->assertStatus(200);
        $response2->assertJson([
            'data' => [
                'id' => $paymentId,
            ]
        ]);

        // Verificar que solo hay UN pago en la base de datos
        $this->assertEquals(1, Payment::where('idempotency_key', $idempotencyKey)->count());
    }

    /** @test */
    public function no_permite_pago_en_pedido_ya_pagado()
    {
        Http::fake([
            '*lp-test-api-v1.free.beeceptor.com*' => Http::response([
                'id' => 123,
                'createdAt' => now()->toIso8601String(),
            ], 201)
        ]);

        $order = Order::factory()->create([
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);

        // Primer pago exitoso
        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ])->assertStatus(201);

        // Intentar segundo pago (con key diferente) -> debe fallar
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 100.00,
        ], [
            'Idempotency-Key' => '661e9511-f3ac-52e5-b827-557766551111'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Este pedido ya ha sido pagado exitosamente.',
                'errors' => [
                    'order' => ['No se pueden procesar pagos adicionales para un pedido ya pagado.']
                ]
            ]);

        // Verificar que solo hay UN pago
        $this->assertEquals(1, Payment::where('order_id', $order->id)->count());
    }
}
