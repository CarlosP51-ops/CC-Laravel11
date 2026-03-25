<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PaymentStatus;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'transaction_id',
        'amount',
        'fee',
        'status',
        'payment_method',
        'gateway',
        'gateway_transaction_id',
        'gateway_response',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'status' => PaymentStatus::class,
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}