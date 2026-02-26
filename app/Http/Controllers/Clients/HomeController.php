<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Seller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Get all data for homepage
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'hero_stats' => $this->getHeroStats(),
                'categories' => $this->getCategories(),
                'featured_products' => $this->getFeaturedProducts(),
                'new_products' => $this->getNewProducts(),
                'best_sellers' => $this->getBestSellers(),
                'platform_stats' => $this->getPlatformStats()
            ]
        ]);
    }

    /**
     * Get hero section statistics
     */
    private function getHeroStats()
    {
        return [
            'total_products' => Product::where('is_active', true)->count(),
            'total_sellers' => Seller::where('is_active', true)->count(),
            'satisfaction_rate' => 98, // Pourcentage fixe ou calculé depuis reviews
            'total_users' => User::where('role', 'client')->count()
        ];
    }

    /**
     * Get categories with product count
     */
    private function getCategories()
    {
        return Category::where('is_active', true)
            ->whereNull('parent_id') // Seulement les catégories principales
            ->withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('products_count', 'desc')
            ->limit(8)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'products_count' => $category->products_count
                ];
            });
    }

    /**
     * Get featured products (top rated)
     */
    private function getFeaturedProducts()
    {
        return Product::with(['category', 'seller', 'images', 'reviews'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderBy('reviews_avg_rating', 'desc')
            ->limit(8)
            ->get()
            ->map(function ($product) {
                return $this->formatProduct($product);
            });
    }

    /**
     * Get newest products
     */
    private function getNewProducts()
    {
        return Product::with(['category', 'seller', 'images', 'reviews'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(function ($product) {
                return $this->formatProduct($product);
            });
    }

    /**
     * Get best selling products
     */
    private function getBestSellers()
    {
        return Product::with(['category', 'seller', 'images', 'reviews'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->withCount('orderItems')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderBy('order_items_count', 'desc')
            ->limit(8)
            ->get()
            ->map(function ($product) {
                return $this->formatProduct($product);
            });
    }

    /**
     * Get platform statistics for WhyUs section
     */
    private function getPlatformStats()
    {
        return [
            'monthly_transactions' => Order::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'average_rating' => round(DB::table('reviews')->avg('rating') ?? 4.5, 1),
            'support_hours' => '24/7',
            'countries_served' => Seller::distinct('country')->count('country'),
            'total_sales' => Order::where('payment_status', 'paid')->sum('total'),
            'verified_sellers' => Seller::where('is_verified', true)->where('is_active', true)->count()
        ];
    }

    /**
     * Format product data for frontend
     */
    private function formatProduct($product)
    {
        $primaryImage = $product->images->where('is_primary', true)->first();
        
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->short_description ?? substr($product->description, 0, 100) . '...',
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'rating' => round($product->reviews_avg_rating ?? 0, 1),
            'reviews_count' => $product->reviews_count ?? 0,
            'sales_count' => $product->order_items_count ?? 0,
            'stock_quantity' => $product->stock_quantity,
            'image' => $primaryImage ? $primaryImage->image_path : ($product->images->first()?->image_path ?? null),
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug
            ] : null,
            'seller' => [
                'id' => $product->seller->id,
                'store_name' => $product->seller->store_name,
                'slug' => $product->seller->slug,
                'is_verified' => $product->seller->is_verified ?? false,
                'logo' => $product->seller->logo
            ]
        ];
    }

    /**
     * Get categories for CategoryGrid section
     */
    public function categories()
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id') // Seulement les catégories principales
            ->withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('order', 'asc')
            ->orderBy('products_count', 'desc')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'products_count' => $category->products_count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Newsletter subscription for SellerCTA
     */
    public function subscribeNewsletter(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // Logique d'inscription newsletter à implémenter
        // Vous pouvez créer une table newsletter_subscriptions si nécessaire

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie ! Vous recevrez bientôt nos actualités.'
        ]);
    }

    /**
     * Get popular categories for EmptyCart page
     */
    public function popularCategories()
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('products_count', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'products_count' => $category->products_count,
                    'icon' => $this->getCategoryIcon($category->name),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get trending products for EmptyCart page
     */
    public function trendingProducts()
    {
        $products = Product::with(['category', 'seller', 'images', 'reviews'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->withCount('orderItems')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderBy('order_items_count', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($product) {
                return $this->formatProduct($product);
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get icon name for category (à personnaliser selon vos besoins)
     */
    private function getCategoryIcon($categoryName)
    {
        $icons = [
            'Électronique' => 'Laptop',
            'Mode' => 'Shirt',
            'Maison' => 'Home',
            'Sport' => 'Dumbbell',
            'Livres' => 'Book',
            'Jouets' => 'Gamepad2',
        ];

        return $icons[$categoryName] ?? 'Package';
    }
}
