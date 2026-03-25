<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerRestriction extends Model
{
    protected $fillable = [
        'seller_id', 'type', 'label', 'reason', 'applied_by', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function seller()   { return $this->belongsTo(Seller::class); }
    public function appliedBy(){ return $this->belongsTo(User::class, 'applied_by'); }
}
