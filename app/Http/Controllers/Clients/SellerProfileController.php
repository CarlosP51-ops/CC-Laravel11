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
            ->where('is_active', true)
            ->findOrFail($id);

        // Produits approuvés et en stock
        $products = $seller->products()
            ->where('status', 'approved')
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
                'price'            => $p->price,
                'compare_at_price' => $p->compare_at_price,
                'rating'           => round($p->reviews_avg_rating ?? 0, 1),
                'reviews_count'    => $p->reviews_count ?? 0,
                'is_digital'       => $p->is_digital,
                'image'            => $p->images->first()?->image_path,
            ]);

        // Stats calculées depuis order_items (pas de colonne sales_count)
        $productIds = $seller->products()->pluck('id');

        $totalSales = DB::table('order_items')
            ->whereIn('product_id', $productIds)
            ->sum('quantity');

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
                'logo'        => $seller->logo,
                'banner'      => $seller->banner,
                'city'        => $seller->city,
                'country'     => $seller->country,
                'is_verified' => $seller->is_verified,
                'joined'      => $seller->created_at->format('F Y'),
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
}
