<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'category_id',
        'subcategory_id',
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'compare_at_price',
        'sku',
        'stock',
        'stock_quantity', // Garder pour compatibilité
        'weight',
        'dimensions',
        'tags',
        'images',
        'is_active',
        'is_promoted',
        'is_digital',
        'digital_file_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
            'is_promoted' => 'boolean',
            'is_digital' => 'boolean',
            'images' => 'array',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    // Accesseur pour l'image principale
    public function getPrimaryImageAttribute()
    {
        return $this->images()->where('is_primary', true)->first()?->image_path;
    }
}