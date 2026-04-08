<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SellerProfileController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $seller = Seller::with(['user'])
            ->where(function($q) {
                $q->where('is_active', true)->orWhere('is_verified', true);
            })
            ->findOrFail($id);

        // Produits approuvés et en stock
        $products = $seller->products()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_digital', true)
                  ->orWhere('stock_quantity', '>', 0);
            })
            ->with(['images' => fn($q) => $q->where('is_primary', true)])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'slug'             => $p->slug,
                'name'             => $p->name,
                'price'            => (float) $p->price,
                'compare_at_price' => $p->compare_at_price ? (float) $p->compare_at_price : null,
                'rating'           => round($p->reviews_avg_rating ?? 0, 1),
                'reviews_count'    => $p->reviews_count ?? 0,
                'is_digital'       => (bool) $p->is_digital,
                'image'            => $this->buildUrl($p->images->first()?->image_path),
            ]);

        // Stats calculées depuis order_items — uniquement commandes payées
        $productIds = $seller->products()->pluck('id');

        $totalSales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $productIds)
            ->where('orders.payment_status', 'paid')
            ->sum('order_items.quantity');

        $totalReviews = DB::table('reviews')
            ->whereIn('product_id', $productIds)
            ->count();

        $avgRating = DB::table('reviews')
            ->whereIn('product_id', $productIds)
            ->avg('rating');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $seller->id,
                'user_id'     => $seller->user_id,
                'store_name'  => $seller->store_name,
                'slug'        => $seller->slug,
                'description' => $seller->description,
                'logo'        => $this->buildUrl($seller->logo),
                'banner'      => $this->buildUrl($seller->banner),
                'city'        => $seller->city,
                'country'     => $seller->country,
                'is_verified' => (bool) $seller->is_verified,
                'joined'      => $seller->created_at->locale('fr')->isoFormat('MMMM YYYY'),
                'stats'       => [
                    'products' => $products->count(),
                    'sales'    => (int) $totalSales,
                    'reviews'  => (int) $totalReviews,
                    'rating'   => round($avgRating ?? 0, 1),
                ],
                'products'    => $products,
            ],
        ]);
    }

    private function buildUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return config('app.url') . '/storage/' . ltrim($path, '/');
    }
}
