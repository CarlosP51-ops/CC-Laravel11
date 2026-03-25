<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'store_name', 'slug', 'description', 'logo', 'banner',
        'address', 'city', 'postal_code', 'country', 'is_verified', 'is_active',
        'quality_score', 'warnings_count',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'    => 'boolean',
            'is_active'      => 'boolean',
            'quality_score'  => 'integer',
            'warnings_count' => 'integer',
        ];
    }

    public function user()         { return $this->belongsTo(User::class); }
    public function products()     { return $this->hasMany(Product::class); }
    public function orders()       { return $this->hasMany(Order::class); }
    public function restrictions() { return $this->hasMany(SellerRestriction::class)->where('is_active', true); }
}