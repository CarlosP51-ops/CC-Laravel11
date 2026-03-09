<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Récupérer tous les produits de la wishlist de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $wishlists = $user->wishlists()
            ->with(['product.category', 'product.seller'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($wishlist) {
                $product = $wishlist->product;
                
                return [
                    'id' => $wishlist->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'price' => (float) $product->price,
                    'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
                    'rating' => (float) $product->average_rating,
                    'review_count' => $product->reviews_count,
                    'category' => $product->category->name ?? 'Non catégorisé',
                    'added_date' => $wishlist->created_at->toISOString(),
                    'image' => $product->images[0] ?? null,
                    'in_stock' => $product->stock > 0,
                    'tags' => $product->tags ?? [],
                    'seller_name' => $product->seller->store_name ?? 'Vendeur',
                ];
            });

        // Calculer les statistiques
        $totalPrice = $wishlists->sum('price');
        $totalSavings = $wishlists->sum(function ($item) {
            return $item['compare_at_price'] ? ($item['compare_at_price'] - $item['price']) : 0;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $wishlists,
                'stats' => [
                    'total_items' => $wishlists->count(),
                    'total_price' => number_format($totalPrice, 2),
                    'total_savings' => number_format($totalSavings, 2),
                ]
            ]
        ]);
    }

    /**
     * Ajouter un produit à la wishlist
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = $request->user();

        // Vérifier si le produit est déjà dans la wishlist
        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit est déjà dans votre wishlist',
            ], 400);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté à la wishlist',
            'data' => [
                'wishlist_id' => $wishlist->id,
            ]
        ], 201);
    }

    /**
     * Supprimer un produit de la wishlist
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $wishlist = Wishlist::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$wishlist) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé dans votre wishlist',
            ], 404);
        }

        $wishlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit retiré de la wishlist',
        ]);
    }

    /**
     * Supprimer plusieurs produits de la wishlist
     */
    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'wishlist_ids' => 'required|array',
            'wishlist_ids.*' => 'required|exists:wishlists,id',
        ]);

        $user = $request->user();

        $deleted = Wishlist::whereIn('id', $validated['wishlist_ids'])
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} produit(s) retiré(s) de la wishlist",
        ]);
    }

    /**
     * Vérifier si un produit est dans la wishlist
     */
    public function check(Request $request, $productId)
    {
        $user = $request->user();

        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'in_wishlist' => $exists,
            ]
        ]);
    }
}
