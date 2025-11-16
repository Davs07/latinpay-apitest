<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    /**
     * Procesar pago a través de API externa simulada
     *
     * @param array $payload
     * @return array
     */
    public function process(array $payload): array
    {
        try {
            Log::channel('payments')->info('Procesando pago', [
                'payload' => $payload,
                'timestamp' => now(),
            ]);

             // Llamar a API externa simulada (reqres.in)
            $response = Http::timeout(10)->post('https://lp-test-api-v1.free.beeceptor.com', [
                'order_id' => $payload['order_id'],
                'amount' => $payload['amount'],
                'customer_name' => $payload['customer_name'] ?? null,
            ]);

            // Simular lógica de éxito/fallo basado en el código de respuesta
            $isSuccess = $response->successful();

            $result = [
                'status' => $isSuccess ? 'success' : 'failed',
                'response' => $response->json(),
            ];

            Log::channel('payments')->info('Respuesta del gateway', [
                'result' => $result,
                'timestamp' => now(),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::channel('payments')->error('Error procesando pago', [
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ]);

            return [
                'status' => 'failed',
                'response' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }
}
