<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display product details with preview data
     * GET /api/products/{id}
     */
    public function show($id)
    {
        $product = Product::with([
            'category',
            'seller',
            'images',
            'variants',
            'reviews' => function ($query) {
                $query->with('user')->latest()->limit(5);
            }
        ])
        ->withAvg('reviews', 'rating')
        ->withCount('reviews')
        ->withCount('orderItems')
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $this->formatProductDetails($product),
                'reviews_preview' => $this->getReviewsPreview($product),
                'related_products' => $this->getRelatedProducts($product, 4),
                'technical_details' => $this->getTechnicalDetails($product),
                'breadcrumb' => $this->getBreadcrumb($product)
            ]
        ]);
    }

    /**
     * Get related products
     * GET /api/products/{id}/related
     */
    public function related($id, Request $request)
    {
        $product = Product::findOrFail($id);
        $limit = $request->limit ?? 4;

        $related = $this->getRelatedProducts($product, $limit);

        return response()->json([
            'success' => true,
            'data' => $related
        ]);
    }

    /**
     * Format product details for frontend
     */
    private function formatProductDetails($product)
    {
        $discountPercentage = null;
        if ($product->compare_at_price && $product->compare_at_price > $product->price) {
            $discountPercentage = round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100);
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'discount_percentage' => $discountPercentage,
            'sku' => $product->sku,
            'stock_quantity' => $product->stock_quantity,
            'sales_count' => $product->order_items_count ?? 0,
            'rating' => round($product->reviews_avg_rating ?? 0, 1),
            'reviews_count' => $product->reviews_count ?? 0,
            'last_updated' => $product->updated_at->format('Y-m-d'),
            'is_active' => $product->is_active,
            'images' => $product->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'is_primary' => $image->is_primary ?? false
                ];
            }),
            'category' => [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug
            ],
            'seller' => [
                'id' => $product->seller->id,
                'store_name' => $product->seller->store_name,
                'slug' => $product->seller->slug,
                'is_verified' => $product->seller->is_verified ?? false,
                'logo' => $product->seller->logo,
                'description' => $product->seller->description
            ],
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price' => (float) $variant->price,
                    'stock_quantity' => $variant->stock_quantity
                ];
            }),
            'features' => [
                'Téléchargement instantané',
                'Paiement sécurisé',
                'Garantie 30 jours',
                'Mises à jour gratuites',
                'Support prioritaire'
            ]
        ];
    }

    /**
     * Get reviews preview (first 5 reviews + summary)
     */
    private function getReviewsPreview($product)
    {
        // Calculate rating distribution
        $ratingDistribution = DB::table('reviews')
            ->select('rating', DB::raw('count(*) as count'))
            ->where('product_id', $product->id)
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Fill missing ratings with 0
        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribution[$i] = $ratingDistribution[$i] ?? 0;
        }

        return [
            'summary' => [
                'average_rating' => round($product->reviews_avg_rating ?? 0, 1),
                'total_reviews' => $product->reviews_count ?? 0,
                'rating_distribution' => $distribution
            ],
            'reviews' => $product->reviews->map(function ($review) {
                return $this->formatReview($review);
            }),
            'has_more' => $product->reviews_count > 5
        ];
    }

    /**
     * Format single review
     */
    private function formatReview($review)
    {
        $userName = $review->user->fullname ?? 'Utilisateur';
        $words = explode(' ', $userName);
        $initials = count($words) >= 2 
            ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
            : strtoupper(substr($userName, 0, 2));

        return [
            'id' => $review->id,
            'user_name' => $userName,
            'user_initials' => $initials,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at->format('Y-m-d'),
            'helpful_count' => $review->helpful_count ?? 0,
            'is_verified_purchase' => $this->isVerifiedPurchase($review)
        ];
    }

    /**
     * Check if review is from verified purchase
     */
    private function isVerifiedPurchase($review)
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $review->user_id)
            ->where('order_items.product_id', $review->product_id)
            ->where('orders.payment_status', 'paid')
            ->exists();
    }

    /**
     * Get related products
     */
    private function getRelatedProducts($product, $limit = 4)
    {
        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with(['category', 'seller', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withCount('orderItems')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return $related->map(function ($item) {
            $primaryImage = $item->images->where('is_primary', true)->first();
            
            return [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'price' => (float) $item->price,
                'compare_at_price' => $item->compare_at_price ? (float) $item->compare_at_price : null,
                'image' => $primaryImage ? $primaryImage->image_path : ($item->images->first()?->image_path ?? null),
                'rating' => round($item->reviews_avg_rating ?? 0, 1),
                'reviews_count' => $item->reviews_count ?? 0,
                'sales_count' => $item->order_items_count ?? 0,
                'is_popular' => ($item->order_items_count ?? 0) > 50,
                'category' => [
                    'id' => $item->category->id,
                    'name' => $item->category->name,
                    'slug' => $item->category->slug
                ],
                'seller' => [
                    'id' => $item->seller->id,
                    'store_name' => $item->seller->store_name,
                    'is_verified' => $item->seller->is_verified ?? false
                ]
            ];
        });
    }

    /**
     * Get technical details
     */
    private function getTechnicalDetails($product)
    {
        return [
            'sku' => $product->sku,
            'format' => 'Digital Download', // À adapter selon vos besoins
            'file_size' => 'Variable', // À ajouter dans la table products si nécessaire
            'compatibility' => 'Tous navigateurs modernes', // À adapter
            'license' => 'Licence standard', // À adapter
            'version' => '1.0.0', // À ajouter dans la table products si nécessaire
            'last_update' => $product->updated_at->format('Y-m-d'),
            'included' => [
                'Fichiers source',
                'Documentation',
                'Support technique'
            ]
        ];
    }

    /**
     * Get breadcrumb navigation
     */
    private function getBreadcrumb($product)
    {
        return [
            [
                'name' => 'Accueil',
                'url' => '/',
                'active' => false
            ],
            [
                'name' => 'Produits',
                'url' => '/products',
                'active' => false
            ],
            [
                'name' => $product->category->name,
                'url' => '/products?category=' . $product->category->slug,
                'active' => false
            ],
            [
                'name' => $product->name,
                'url' => null,
                'active' => true
            ]
        ];
    }
}
