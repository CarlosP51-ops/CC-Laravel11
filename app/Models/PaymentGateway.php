<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'is_active', 'is_test_mode',
        'public_key', 'secret_key', 'webhook_secret',
        'supported_methods', 'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
        'supported_methods' => 'array',
        'settings' => 'array',
    ];

    protected $hidden = ['secret_key', 'webhook_secret'];
}
