<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\ApplyCouponRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Récupère le panier complet de l'utilisateur
     */
    public function index(): JsonResponse
    {
        $cart = $this->getOrCreateCart();
        $cart->load(['items.product.category', 'items.product.seller', 'items.product.images', 'items.variant']);
        
        // Ajouter les recommandations basées sur les produits du panier
        $cart->recommendations = $this->getRecommendations($cart);

        return response()->json([
            'success' => true,
            'data' => new CartResource($cart),
        ]);
    }

    /**
     * Ajoute un produit au panier
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Vérifier le stock disponible
        $product = Product::findOrFail($validated['product_id']);
        $stockAvailable = $validated['product_variant_id'] 
            ? $product->variants()->find($validated['product_variant_id'])->stock_quantity
            : $product->stock_quantity;

        if ($stockAvailable < $validated['quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant pour ce produit.',
            ], 400);
        }

        $cart = $this->getOrCreateCart();

        // Vérifier si l'article existe déjà dans le panier
        $existingItem = $cart->items()
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $validated['quantity'];
            
            if ($stockAvailable < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock insuffisant pour cette quantité.',
                ], 400);
            }

            $existingItem->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create($validated);
        }

        $cart->load(['items.product.category', 'items.product.seller', 'items.product.images', 'items.variant']);
        $cart->recommendations = $this->getRecommendations($cart);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté au panier.',
            'data' => new CartResource($cart),
        ], 201);
    }

    /**
     * Met à jour la quantité d'un article
     */
    public function updateItem(UpdateCartItemRequest $request, int $itemId): JsonResponse
    {
        $validated = $request->validated();
        $cart = $this->getOrCreateCart();
        
        $item = $cart->items()->findOrFail($itemId);
        
        // Vérifier le stock
        $stockAvailable = $item->variant?->stock_quantity ?? $item->product->stock_quantity;
        
        if ($stockAvailable < $validated['quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant pour cette quantité.',
            ], 400);
        }

        $item->update(['quantity' => $validated['quantity']]);
        
        $cart->load(['items.product.category', 'items.product.seller', 'items.product.images', 'items.variant']);
        $cart->recommendations = $this->getRecommendations($cart);

        return response()->json([
            'success' => true,
            'message' => 'Quantité mise à jour.',
            'data' => new CartResource($cart),
        ]);
    }

    /**
     * Supprime un article du panier
     */
    public function removeItem(int $itemId): JsonResponse
    {
        $cart = $this->getOrCreateCart();
        $item = $cart->items()->findOrFail($itemId);
        
        $item->delete();
        
        $cart->load(['items.product.category', 'items.product.seller', 'items.product.images', 'items.variant']);
        $cart->recommendations = $this->getRecommendations($cart);

        return response()->json([
            'success' => true,
            'message' => 'Article retiré du panier.',
            'data' => new CartResource($cart),
        ]);
    }

    /**
     * Vide complètement le panier
     */
    public function clear(): JsonResponse
    {
        $cart = $this->getOrCreateCart();
        $cart->items()->delete();
        
        $cart->load('items');

        return response()->json([
            'success' => true,
            'message' => 'Panier vidé.',
            'data' => new CartResource($cart),
        ]);
    }

    /**
     * Applique un code promo
     */
    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $cart = $this->getOrCreateCart();
        
        // TODO: Implémenter la logique de validation des coupons
        // Pour l'instant, simulation d'un coupon de 10%
        $subtotal = $cart->items->sum(function ($item) {
            $price = $item->variant?->price ?? $item->product->price;
            return $price * $item->quantity;
        });

        $discount = $subtotal * 0.10; // 10% de réduction
        
        $cart->coupon_code = $validated['code'];
        $cart->discount = $discount;
        $cart->save();

        $cart->load(['items.product.category', 'items.product.seller', 'items.product.images', 'items.variant']);
        $cart->recommendations = $this->getRecommendations($cart);

        return response()->json([
            'success' => true,
            'message' => 'Code promo appliqué.',
            'data' => new CartResource($cart),
        ]);
    }

    /**
     * Récupère ou crée le panier de l'utilisateur
     */
    private function getOrCreateCart(): Cart
    {
        $user = auth()->user();
        
        return Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['session_id' => session()->getId()]
        );
    }

    /**
     * Génère des recommandations basées sur le panier
     */
    private function getRecommendations(Cart $cart)
    {
        if ($cart->items->isEmpty()) {
            // Si le panier est vide, retourner les produits tendance
            return Product::where('is_active', true)
                ->withCount('orderItems')
                ->orderBy('order_items_count', 'desc')
                ->limit(4)
                ->get();
        }

        // Récupérer les catégories des produits dans le panier
        $categoryIds = $cart->items->pluck('product.category_id')->unique()->filter();
        
        // Exclure les produits déjà dans le panier
        $productIdsInCart = $cart->items->pluck('product_id');

        // Recommander des produits de la même catégorie
        return Product::where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->whereNotIn('id', $productIdsInCart)
            ->inRandomOrder()
            ->limit(4)
            ->get();
    }
}
