<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductRecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => round($this->price, 2),
            'compare_at_price' => $this->compare_at_price ? round($this->compare_at_price, 2) : null,
            'image' => $this->primary_image ? asset('storage/' . $this->primary_image) : null,
            'category' => $this->category?->name,
            'rating' => [
                'average' => round($this->reviews()->avg('rating') ?? 0, 1),
                'count' => $this->reviews()->count(),
            ],
        ];
    }
}
