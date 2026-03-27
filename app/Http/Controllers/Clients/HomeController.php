<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Seller;
use App\Models\User;
use App\Models\Order;
use App\Models\NewsletterSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    // Vérifie une fois si la colonne status existe
    private function hasStatusColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = Schema::hasColumn('products', 'status');
        }
        return $has;
    }

    private function applyProductFilters($query)
    {
        $query->where('is_active', true);
        if ($this->hasStatusColumn()) {
            $query->where('status', 'approved');
        }
        $query->where(function ($q) {
            $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0);
        });
        return $query;
    }

    public function index()
    {
        try {
            Cache::forget('home_page_data');
            $data = Cache::remember('home_page_data', 3600, function () {
                return [
                    'hero_stats'       => $this->getHeroStats(),
                    'categories'       => $this->getCategories(),
                    'featured_products'=> $this->getFeaturedProducts(),
                    'new_products'     => $this->getNewProducts(),
                    'best_sellers'     => $this->getBestSellers(),
                    'platform_stats'   => $this->getPlatformStats()
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Home page error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la page d\'accueil',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function getHeroStats()
    {
        return [
            'total_products'   => Product::where('is_active', true)->count(),
            'total_sellers'    => Seller::where('is_active', true)->count(),
            'satisfaction_rate'=> 98,
            'total_users'      => User::where('role', 'client')->count()
        ];
    }

    private function getCategories()
    {
        $hasStatus = $this->hasStatusColumn();

        return Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['products' => function ($query) use ($hasStatus) {
                $query->where('is_active', true);
                if ($hasStatus) $query->where('status', 'approved');
                $query->where(function ($q) {
                    $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0);
                });
            }])
            ->orderBy('products_count', 'desc')
            ->limit(8)
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'slug'           => $c->slug,
                'description'    => $c->description,
                'products_count' => $c->products_count,
            ]);
    }

    private function getFeaturedProducts()
    {
        $q = Product::with(['category', 'seller', 'images' => fn($q) => $q->where('is_primary', true)])
            ->where('is_active', true);
        if ($this->hasStatusColumn()) $q->where('status', 'approved');
        $q->where(function ($q) { $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0); });
        return $q->orderBy('created_at', 'desc')->limit(8)->get()->map(fn($p) => $this->formatProduct($p));
    }

    private function getNewProducts()
    {
        $q = Product::with(['category', 'seller', 'images' => fn($q) => $q->where('is_primary', true)])
            ->where('is_active', true);
        if ($this->hasStatusColumn()) $q->where('status', 'approved');
        $q->where(function ($q) { $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0); });
        return $q->orderBy('created_at', 'desc')->limit(8)->get()->map(fn($p) => $this->formatProduct($p));
    }

    private function getBestSellers()
    {
        $q = Product::with(['category', 'seller', 'images' => fn($q) => $q->where('is_primary', true)])
            ->where('is_active', true);
        if ($this->hasStatusColumn()) $q->where('status', 'approved');
        $q->where(function ($q) { $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0); });
        return $q->orderBy('created_at', 'desc')->limit(8)->get()->map(fn($p) => $this->formatProduct($p));
    }

    private function getPlatformStats()
    {
        try {
            return [
                'monthly_transactions' => Order::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
                'average_rating'   => 4.5,
                'support_hours'    => '24/7',
                'countries_served' => 50,
                'total_sales'      => Order::where('payment_status', 'paid')->sum('total_amount') ?? 0,
                'verified_sellers' => Seller::where('is_verified', true)->where('is_active', true)->count()
            ];
        } catch (\Exception $e) {
            return ['monthly_transactions' => 0, 'average_rating' => 4.5, 'support_hours' => '24/7',
                    'countries_served' => 50, 'total_sales' => 0, 'verified_sellers' => 0];
        }
    }

    private function formatProduct($product)
    {
        $image = $product->images?->first()?->image_path ?? null;

        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'slug'             => $product->slug,
            'description'      => $product->short_description ?? substr($product->description ?? '', 0, 100),
            'price'            => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'rating'           => 4.5,
            'reviews_count'    => 0,
            'sales_count'      => 0,
            'stock_quantity'   => $product->stock_quantity ?? 0,
            'image'            => $image,
            'category'         => $product->category ? [
                'id'   => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'seller' => $product->seller ? [
                'id'         => $product->seller->id,
                'user_id'    => $product->seller->user_id,
                'store_name' => $product->seller->store_name ?? 'Vendeur',
                'slug'       => $product->seller->slug ?? '',
                'is_verified'=> $product->seller->is_verified ?? false,
                'logo'       => $product->seller->logo ?? null,
            ] : null,
        ];
    }

    public function categories()
    {
        $hasStatus = $this->hasStatusColumn();
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['products' => function ($query) use ($hasStatus) {
                $query->where('is_active', true);
                if ($hasStatus) $query->where('status', 'approved');
                $query->where(function ($q) {
                    $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0);
                });
            }])
            ->orderBy('products_count', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'slug'           => $c->slug,
                'description'    => $c->description,
                'products_count' => $c->products_count,
            ]);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function subscribeNewsletter(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:newsletter_subscriptions,email'
        ]);

        NewsletterSubscription::create(['email' => $request->email, 'is_active' => true, 'subscribed_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Inscription réussie !']);
    }

    public function popularCategories()
    {
        $hasStatus = $this->hasStatusColumn();
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['products' => function ($query) use ($hasStatus) {
                $query->where('is_active', true);
                if ($hasStatus) $query->where('status', 'approved');
                $query->where(function ($q) {
                    $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0);
                });
            }])
            ->orderBy('products_count', 'desc')
            ->limit(4)
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'slug'           => $c->slug,
                'products_count' => $c->products_count,
                'icon'           => $this->getCategoryIcon($c->name),
            ]);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function trendingProducts()
    {
        $q = Product::with(['category', 'seller', 'images' => fn($q) => $q->where('is_primary', true)])
            ->where('is_active', true);
        if ($this->hasStatusColumn()) $q->where('status', 'approved');
        $q->where(function ($q) { $q->where('is_digital', true)->orWhere('stock_quantity', '>', 0); });
        $products = $q->orderBy('created_at', 'desc')->limit(3)->get()->map(fn($p) => $this->formatProduct($p));

        return response()->json(['success' => true, 'data' => $products]);
    }

    private function getCategoryIcon($categoryName)
    {
        $icons = ['Électronique' => 'Laptop', 'Mode' => 'Shirt', 'Maison' => 'Home',
                  'Sport' => 'Dumbbell', 'Livres' => 'Book', 'Jouets' => 'Gamepad2'];
        return $icons[$categoryName] ?? 'Package';
    }
}
