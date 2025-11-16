# LatinPay - API de Gestión de Pedidos y Pagos

API REST desarrollada en Laravel para gestionar pedidos y pagos con integración a gateway externo simulado.

## Características

- Crear pedidos con nombre de cliente y monto
- Procesar pagos mediante una API externa proveniente de Beeceptor
- Listar pedidos con sus pagos y contador de intentos
- Validación estricta del monto (debe coincidir exactamente)
- Reintentos permitidos en pedidos failed
- Soporte para idempotencia mediante headers
- Protección contra pagos duplicados en órdenes ya pagadas
- Auditoría de intentos de pago
- Logging en `storage/logs/payments.log`
- Tests con Http::fake()

## Tecnologías

- Laravel 12.x (PHP 8.3+)
- MySQL 8.0
- Docker & Docker Compose
- PHPUnit
- Swagger UI para documentación

## Instalación Rápida

```powershell
git clone https://github.com/Davs07/latinpay-apitest.git
cd latinpay-test
./setup.ps1
```

### Opción con make

```bash
git clone https://github.com/Davs07/latinpay-apitest.git
cd latinpay-test
make setup
```

### Opción manual
```powershell
git clone https://github.com/Davs07/latinpay-apitest.git
cd latinpay-test
cp .env.example .env
docker-compose up -d --build
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan db:seed
```

**Para probar la API:**
- URL: http://localhost:8000/api
- Documentación con Swagger: http://localhost:8000/api/documentation

## Endpoints

### POST `/api/orders`
Crear pedido.

```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{"customer_name":"Marco Polo","amount":150.50}'
```

**Respuesta:**
```json
{
  "data": {
    "id": 1,
    "customer_name": "Marco Polo",
    "amount": "150.50",
    "status": "pending",
    "payments_count": 0
  }
}
```

---

### POST `/api/orders/{order}/payments`
Procesar pago.

```bash
curl -X POST http://localhost:8000/api/orders/1/payments \
  -H "Content-Type: application/json" \
  -d '{"amount":150.50}'
```

**Respuesta (Exitoso):**
```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "amount": "150.50",
    "status": "success",
    "gateway_response": {...}
  }
}
```

---

### GET `/api/orders`
Listar todos los pedidos.

```bash
curl http://localhost:8000/api/orders
```

---

### GET `/api/orders/{id}`
Ver pedido específico.

```bash
curl http://localhost:8000/api/orders/1
```

---

### GET `/api/orders/{order}/payment-attempts`
Obtener historial de intentos de pago para auditoría.

```bash
curl http://localhost:8000/api/orders/1/payment-attempts
```

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "order_id": 1,
      "payment_id": 1,
      "amount": "150.50",
      "status": "success",
      "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "request_payload": {...},
      "response_payload": {...},
      "error_message": null,
      "created_at": "2025-11-16T10:30:00.000000Z"
    }
  ]
}
```

---

## Idempotencia en los Pagos

Para evitar cobros duplicados ante reintentos automáticos o fallos de red el endpoint:

POST `/api/orders/{order}/payments`

acepta el header opcional:

```
Idempotency-Key: <uuid>
```

### Generación de Idempotency Key

Puedes usar un generador online (también se puede usar la terminal o una variable dinámica en Postman para obtener un UUID)
- https://www.uuidgenerator.net/version4
- Copia el UUID generado (ej: `7c9e6679-7425-40de-944b-e07fc1f90ae7`)

### Comportamiento

Sin header de idempotencia:
- Funciona normal ya que solo permite un pago exitoso, después la orden queda en estado PAID

Con header (por primera vez):
- Procesa el pago normalmente
- Almacena la idempotency key en la base de datos

Con header (reintento con misma key):
- NO se crea un nuevo registro de pago
- NO se vuelve a llamar al gateway externo
- Se devuelve exactamente la misma respuesta del pago original

Protección contra duplicados:
- Una vez el pedido está en estado PAID, no acepta más pagos (independientemente de si tienen keys diferentes)
- Retorna HTTP 422 con mensaje: "Este pedido ya ha sido pagado exitosamente."
- Previene cobros duplicados por error del cliente o aplicación

Nota: La idempotencia permite reintentar de forma segura con la misma key, pero una vez el pedido está PAID ningún pago adicional se acepta.

### Ejemplos de Uso

Sin Idempotency (comportamiento normal):
```bash
curl -X POST http://localhost:8000/api/orders/1/payments \
  -H "Content-Type: application/json" \
  -d '{"amount":150.50}'
```

Con Idempotency (protección contra duplicados):
```bash
curl -X POST http://localhost:8000/api/orders/1/payments \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{"amount":150.50}'
  
# Reintento con la misma key -> Devuelve el mismo pago, sin duplicar
curl -X POST http://localhost:8000/api/orders/1/payments \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{"amount":150.50}'
```

## Testing

```bash
# Ejecutar todos los tests
docker-compose exec app php artisan test

# Test específico
docker-compose exec app php artisan test --filter OrderPaymentTest

# Ejecutar todos los tests (con Make)
make test
```

Tests incluidos:
1. Crear pedido
2. Procesar pago exitoso
3. Procesar pago fallido
4. Reintentos en pedidos failed
5. Listar pedidos con pagos
6. Validar monto exacto
7. Error 404 pedido inexistente
8. Idempotencia: Procesar pago con idempotency key
9. Idempotencia: No duplicar pago con mismo key
10. Protección: No permitir pagos en pedidos ya pagados

## Estructura del Proyecto

```
app/
  ├── Http/
  │   ├── Controllers/
  │   │   ├── OrderController.php
  │   │   └── PaymentController.php
  │   ├── Requests/
  │   │   ├── StoreOrderRequest.php
  │   │   └── StorePaymentRequest.php
  │   └── Resources/
  │       ├── OrderResource.php
  │       ├── PaymentResource.php
  │       └── PaymentAttemptResource.php
  ├── Models/
  │   ├── Order.php
  │   ├── Payment.php
  │   └── PaymentAttempt.php
  ├── Services/
  │   └── PaymentGatewayService.php
  ├── OrderStatus.php (enum)
  └── PaymentStatus.php (enum)
```

## Auditoría de Pagos

Cada intento de pago se registra en la tabla `payment_attempts` con IP, user agent, payloads, respuestas del gateway y errores. Incluye intentos exitosos, fallidos, rechazados y duplicados.

## Decisiones Técnicas

### Arquitectura
- **Form Requests** para validación centralizada.
- **API Resources** para respuestas JSON consistentes.
- **Service `PaymentGatewayService`** para aislar la lógica de integración con el gateway externo.
- **Enums** (`OrderStatus`, `PaymentStatus`) para manejar estados de forma tipada.
- **Relaciones**:
  - `Order` tiene muchos `Payment`.

### API Externa
Se usa **Beeceptor** (`lp-test-api-v1.free.beeceptor.com`) como mock:
- Respuesta 2xx = success
- Error HTTP = failed
- Configurable en `app/Services/PaymentGatewayService.php`
- Los tests usan `Http::fake()` para simular respuestas

### Logging
Todos los intentos en `storage/logs/payments.log`:
- Timestamp
- Payload
- Respuesta del gateway
- Errores

### Validaciones
- **Monto exacto:** El monto del pago debe coincidir exactamente con el del pedido (validado en el controlador)
- **Customer name:** Requerido, string, máximo 255 caracteres
- **Amount:** Numérico, mínimo 0.01
- **Model Binding:** Manejo automático de errores 404 cuando el pedido no existe

## Variables de Entorno

```env
APP_NAME=LatinPay
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=latinpay
DB_USERNAME=latinpay
DB_PASSWORD=secret
```

## Comandos útiles (con Docker)

```bash
# Ver logs de pagos
docker-compose exec app tail -f storage/logs/payments.log

# Recrear base de datos
docker-compose exec app php artisan migrate:fresh --seed

# Limpiar caché
docker-compose exec app php artisan cache:clear
```

## Comandos útiles (con Make)

```bash
# Ver todos los comandos disponibles
make help

# Levantar/detener contenedores
make up
make down

# Base de datos
make migrate
make seed
make fresh        # para recerar desde cero

# Testing y logs
make test
make logs

# Limpieza total
make clean
```

## Documentación OpenAPI (Swagger)

El archivo `openapi.yaml` incluye:
- Especificación completa de los endpoints
- Esquema de Orders y Payments
- Estados posibles (pending, paid, failed, success)
- Ejemplos de peticiones y respuestas
- Documentación de idempotencia
- Validaciones y errores

### Visualizar la documentación

Accede directamente desde tu navegador:

```
http://localhost:8000/api/documentation
```

La interfaz de Swagger UI está completamente integrada en la aplicación.

## Autor

Davs07 (Davy Rodríguez) - Noviembre 2025
