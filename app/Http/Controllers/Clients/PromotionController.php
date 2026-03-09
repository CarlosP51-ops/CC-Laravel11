<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * Récupérer tous les produits en promotion
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 12);
        $sortBy = $request->input('sort_by', 'discount');
        $sortOrder = $request->input('sort_order', 'desc');
        $minDiscount = $request->input('min_discount', 10);

        // Récupérer les produits marqués comme promus ET qui ont un compare_at_price
        $query = Product::where('is_promoted', true)
            ->where('is_active', true)
            ->whereNotNull('compare_at_price')
            ->where('compare_at_price', '>', 'price')
            ->with(['category', 'seller', 'reviews']);

        // Filtrer par pourcentage de réduction minimum
        if ($minDiscount) {
            $query->whereRaw('((compare_at_price - price) / compare_at_price * 100) >= ?', [$minDiscount]);
        }

        // Tri
        switch ($sortBy) {
            case 'discount':
                $query->orderByRaw('((compare_at_price - price) / compare_at_price * 100) ' . $sortOrder);
                break;
            case 'price':
                $query->orderBy('price', $sortOrder);
                break;
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'sales':
                $query->orderBy('sales_count', $sortOrder);
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $products = $query->paginate($perPage);

        // Calculer les statistiques
        $allPromotions = Product::where('is_promoted', true)
            ->where('is_active', true)
            ->whereNotNull('compare_at_price')
            ->where('compare_at_price', '>', 'price')
            ->get();

        $totalProducts = $allPromotions->count();
        $averageDiscount = $allPromotions->avg(function ($product) {
            return (($product->compare_at_price - $product->price) / $product->compare_at_price) * 100;
        });

        $maxSavings = $allPromotions->max(function ($product) {
            return $product->compare_at_price - $product->price;
        });

        // Formater les produits
        $formattedProducts = $products->map(function ($product) {
            $discount = $product->compare_at_price 
                ? round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100)
                : 0;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price' => (float) $product->price,
                'compare_at_price' => (float) $product->compare_at_price,
                'discount' => $discount,
                'savings' => (float) ($product->compare_at_price - $product->price),
                'rating' => (float) $product->average_rating,
                'reviews_count' => $product->reviews_count,
                'sales_count' => $product->sales_count,
                'category' => $product->category->name ?? 'Non catégorisé',
                'category_id' => $product->category_id,
                'image' => $product->images[0] ?? null,
                'seller' => [
                    'name' => $product->seller->store_name ?? 'Vendeur',
                    'is_verified' => $product->seller->is_verified ?? false,
                ],
                'stock' => $product->stock,
                'in_stock' => $product->stock > 0,
                'is_promoted' => true, // Toujours true ici
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $formattedProducts,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'stats' => [
                    'total_products' => $totalProducts,
                    'average_discount' => round($averageDiscount, 1),
                    'max_savings' => round($maxSavings, 2),
                    'active_promotions' => $totalProducts,
                ]
            ]
        ]);
    }

    /**
     * Récupérer les meilleures promotions (top deals)
     */
    public function topDeals()
    {
        $products = Product::where('is_promoted', true)
            ->where('is_active', true)
            ->whereNotNull('compare_at_price')
            ->where('compare_at_price', '>', 'price')
            ->with(['category', 'seller'])
            ->orderByRaw('((compare_at_price - price) / compare_at_price * 100) DESC')
            ->limit(6)
            ->get()
            ->map(function ($product) {
                $discount = round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'compare_at_price' => (float) $product->compare_at_price,
                    'discount' => $discount,
                    'category' => $product->category->name ?? 'Non catégorisé',
                    'image' => $product->images[0] ?? null,
                    'rating' => (float) $product->average_rating,
                    'is_promoted' => true,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
}
