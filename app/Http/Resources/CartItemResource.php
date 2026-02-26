<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $variant = $this->variant;
        
        $price = $variant?->price ?? $product->price;
        $compareAtPrice = $variant?->compare_at_price ?? $product->compare_at_price;
        $savings = $compareAtPrice ? ($compareAtPrice - $price) * $this->quantity : 0;
        $subtotal = $price * $this->quantity;

        return [
            'id' => $this->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'category' => $product->category?->name,
            'seller' => [
                'id' => $product->seller?->id,
                'name' => $product->seller?->business_name ?? 'Vendeur',
            ],
            'image' => $product->primary_image ? asset('storage/' . $product->primary_image) : null,
            'variant' => $variant ? [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
            ] : null,
            'price' => round($price, 2),
            'compare_at_price' => $compareAtPrice ? round($compareAtPrice, 2) : null,
            'quantity' => $this->quantity,
            'subtotal' => round($subtotal, 2),
            'savings' => round($savings, 2),
            'stock_available' => $variant?->stock_quantity ?? $product->stock_quantity,
            'rating' => [
                'average' => round($product->reviews()->avg('rating') ?? 0, 1),
                'count' => $product->reviews()->count(),
            ],
        ];
    }
}
