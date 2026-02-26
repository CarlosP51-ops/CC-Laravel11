<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal = $this->items->sum(function ($item) {
            $price = $item->variant?->price ?? $item->product->price;
            return $price * $item->quantity;
        });

        $discount = $this->discount ?? 0;
        $taxRate = 0.20; // 20% TVA (à configurer selon les besoins)
        $subtotalAfterDiscount = $subtotal - $discount;
        $tax = $subtotalAfterDiscount * $taxRate;
        $total = $subtotalAfterDiscount + $tax;

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items->count(),
            'summary' => [
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'tax' => round($tax, 2),
                'tax_rate' => $taxRate,
                'total' => round($total, 2),
                'coupon_code' => $this->coupon_code ?? null,
            ],
            'recommendations' => $this->when(
                isset($this->recommendations),
                fn() => ProductRecommendationResource::collection($this->recommendations)
            ),
            'stats' => [
                'satisfaction_rate' => 98,
                'support_response_time' => '2h',
                'active_clients' => '50k+',
            ],
        ];
    }
}
