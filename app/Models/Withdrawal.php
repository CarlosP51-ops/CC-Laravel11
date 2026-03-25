<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = [
        'seller_id', 'amount', 'fee', 'net_amount', 'method', 'gateway',
        'payout_details', 'status', 'reference', 'notes', 'rejection_reason',
        'risk_score', 'gateway_payout_id', 'processed_at',
    ];

    protected $casts = [
        'payout_details' => 'array',
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
