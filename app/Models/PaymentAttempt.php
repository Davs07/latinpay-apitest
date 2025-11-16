<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'amount',
        'status',
        'payment_id',
        'idempotency_key',
        'ip_address',
        'user_agent',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
