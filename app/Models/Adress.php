<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AddressType; 

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_type',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'country',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'address_type' => AddressType::class,
            'is_default' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Shipping orders (where this address is used as shipping)
    public function shippingOrders()
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }

    // Billing orders
    public function billingOrders()
    {
        return $this->hasMany(Order::class, 'billing_address_id');
    }
}