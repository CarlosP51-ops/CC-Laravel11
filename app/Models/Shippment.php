<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\ShipmentStatus; // pending, shipped, delivered

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'carrier',
        'tracking_number',
        'shipped_at',
        'delivered_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'status' => ShipmentStatus::class,
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}