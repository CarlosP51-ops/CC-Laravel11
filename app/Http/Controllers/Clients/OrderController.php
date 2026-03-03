<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Créer une nouvelle commande depuis le panier
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_info' => 'required|array',
            'customer_info.email' => 'required|email',
            'customer_info.fullname' => 'required|string|max:255',
            'customer_info.phone' => 'required|string|max:20',
            'customer_info.address' => 'required|string|max:500',
            'customer_info.city' => 'required|string|max:100',
            'customer_info.postal_code' => 'required|string|max:20',
            'customer_info.country' => 'required|string|max:100',
            'payment_method' => 'required|in:card,paypal,apple_pay,bank_transfer',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Récupérer le panier
            $cart = Cart::where('user_id', auth()->id())
                ->with(['items.product', 'items.variant'])
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre panier est vide.',
                ], 400);
            }

            // Vérifier le stock pour tous les produits
            foreach ($cart->items as $item) {
                $product = $item->product;
                $stockAvailable = $item->variant 
                    ? $item->variant->stock_quantity 
                    : $product->stock_quantity;

                if ($stockAvailable < $item->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuffisant pour le produit: {$product->name}",
                    ], 400);
                }
            }

            // Calculer les montants
            $subtotal = $cart->items->sum(function ($item) {
                $price = $item->variant?->price ?? $item->product->price;
                return $price * $item->quantity;
            });

            $discount = $cart->discount ?? 0;
            $taxRate = 0.20; // 20% TVA
            $subtotalAfterDiscount = $subtotal - $discount;
            $tax = $subtotalAfterDiscount * $taxRate;
            $total = $subtotalAfterDiscount + $tax;

            // Créer la commande
            $order = Order::create([
                'user_id' => auth()->id(),
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'customer_email' => $validated['customer_info']['email'],
                'customer_name' => $validated['customer_info']['fullname'],
                'customer_phone' => $validated['customer_info']['phone'],
                'shipping_address' => $validated['customer_info']['address'],
                'shipping_city' => $validated['customer_info']['city'],
                'shipping_postal_code' => $validated['customer_info']['postal_code'],
                'shipping_country' => $validated['customer_info']['country'],
                'notes' => $validated['notes'] ?? null,
                'coupon_code' => $cart->coupon_code,
            ]);

            // Créer les items de commande et déduire le stock
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                $variant = $cartItem->variant;
                $price = $variant?->price ?? $product->price;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'seller_id' => $product->seller_id,
                    'product_name' => $product->name,
                    'product_sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $cartItem->quantity,
                    'price' => $price,
                    'subtotal' => $price * $cartItem->quantity,
                ]);

                // Déduire le stock
                if ($variant) {
                    $variant->decrement('stock_quantity', $cartItem->quantity);
                } else {
                    $product->decrement('stock_quantity', $cartItem->quantity);
                }
            }

            // Vider le panier
            $cart->items()->delete();
            $cart->update([
                'discount' => 0,
                'coupon_code' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès.',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_method' => $order->payment_method,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupérer les commandes de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Récupérer une commande spécifique
     */
    public function show($id): JsonResponse
    {
        $order = Order::where('user_id', auth()->id())
            ->with(['items.product.images', 'items.seller'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}
