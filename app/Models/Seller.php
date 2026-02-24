<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'description',
        'logo',
        'banner',
        'address',
        'city',
        'postal_code',
        'country',
        'is_verified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}