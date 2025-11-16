<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentAttemptResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\OrderStatus;
use App\PaymentStatus;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayService $paymentGateway
    ) {}

    public function show(string $id)
    {
        $payment = Payment::findOrFail($id);
        return new PaymentResource($payment);
    }

    /**
     * Obtener intentos de pago de un pedido (para auditoría)
     */
    public function attempts(string $order)
    {
        $orderModel = Order::findOrFail($order);
        
        $attempts = PaymentAttempt::where('order_id', $orderModel->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return PaymentAttemptResource::collection($attempts);
    }

    /**
     * Procesar un pago para un pedido
     */
    public function store(StorePaymentRequest $request, string $order): JsonResponse|PaymentResource
    {
        // Obtener el pedido o lanzar excepción si no existe (esto retornará 404 automáticamente)
        $orderModel = Order::findOrFail($order);

        // Validar que el monto coincida exactamente con el del pedido
        if ((float) $request->amount !== (float) $orderModel->amount) {
            // Registrar intento fallido
            PaymentAttempt::create([
                'order_id' => $orderModel->id,
                'amount' => $request->amount,
                'status' => 'error',
                'idempotency_key' => $request->header('Idempotency-Key'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_payload' => $request->all(),
                'error_message' => 'El monto del pago no coincide con el monto del pedido',
            ]);

            return response()->json([
                'message' => 'El monto del pago no es válido.',
                'errors' => [
                    'amount' => ['El monto del pago debe coincidir exactamente con el monto del pedido.'],
                ],
            ], 422);
        }

        // Verificar si existe un idempotency key en el header
        $idempotencyKey = $request->header('Idempotency-Key');

        // Si hay idempotency key, verificar si ya existe un pago con esa key
        if ($idempotencyKey) {
            $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();
            
            if ($existingPayment) {
                // Devolver el pago existente sin procesar de nuevo (comportamiento de idempotencia)
                return new PaymentResource($existingPayment);
            }
        }

        // Validar que el pedido no esté ya pagado (evitar pagos duplicados con diferentes idempotency keys)
        // Esta validación se hace DESPUÉS de verificar idempotency para permitir reintentos del mismo pago
        if ($orderModel->status === OrderStatus::PAID) {
            // Registrar intento duplicado
            PaymentAttempt::create([
                'order_id' => $orderModel->id,
                'amount' => $request->amount,
                'status' => 'error',
                'idempotency_key' => $idempotencyKey,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_payload' => $request->all(),
                'error_message' => 'Intento de pago en pedido ya pagado',
            ]);

            return response()->json([
                'message' => 'Este pedido ya ha sido pagado exitosamente.',
                'errors' => [
                    'order' => ['No se pueden procesar pagos adicionales para un pedido ya pagado.'],
                ],
            ], 422);
        }

        try {
            // Procesar pago con el gateway externo
            $gatewayResponse = $this->paymentGateway->process([
                'order_id' => $orderModel->id,
                'amount' => $request->amount,
                'customer_name' => $orderModel->customer_name,
            ]);

            $status = $gatewayResponse['status'] === 'success' 
                ? PaymentStatus::SUCCESS 
                : PaymentStatus::FAILED;

            // Crear registro de pago
            $payment = Payment::create([
                'order_id' => $orderModel->id,
                'amount' => $request->amount,
                'status' => $status,
                'gateway_response' => $gatewayResponse['response'],
                'idempotency_key' => $idempotencyKey,
            ]);

            // Registrar intento exitoso
            PaymentAttempt::create([
                'order_id' => $orderModel->id,
                'amount' => $request->amount,
                'status' => $status === PaymentStatus::SUCCESS ? 'success' : 'failed',
                'payment_id' => $payment->id,
                'idempotency_key' => $idempotencyKey,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_payload' => $request->all(),
                'response_payload' => $gatewayResponse['response'],
            ]);

            // Actualizar estado del pedido
            $orderModel->update([
                'status' => $status === PaymentStatus::SUCCESS
                    ? OrderStatus::PAID
                    : OrderStatus::FAILED,
            ]);

            return (new PaymentResource($payment))->response()->setStatusCode(201);

        } catch (\Exception $e) {
            // Registrar intento con error
            PaymentAttempt::create([
                'order_id' => $orderModel->id,
                'amount' => $request->amount,
                'status' => 'error',
                'idempotency_key' => $idempotencyKey,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_payload' => $request->all(),
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error procesando el pago',
                'errors' => [
                    'payment' => [$e->getMessage()],
                ],
            ], 500);
        }
    }
}
