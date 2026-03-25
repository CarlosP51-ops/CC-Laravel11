<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
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
            'payment_method' => 'required|in:card,orange_money,wave,mtn_money,bank_transfer',
            'phone'          => 'nullable|string|max:20',
            'notes'          => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $cart = Cart::where('user_id', auth()->id())
                ->with(['items.product', 'items.variant'])
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Votre panier est vide.'], 400);
            }

            // Vérifier et verrouiller le stock (évite la race condition)
            foreach ($cart->items as $item) {
                $product = $item->product;
                if (!$product->is_digital) {
                    if ($item->variant) {
                        $locked = ProductVariant::lockForUpdate()->find($item->variant->id);
                        $stock = $locked?->stock_quantity ?? 0;
                    } else {
                        $locked = Product::lockForUpdate()->find($product->id);
                        $stock = $locked?->stock_quantity ?? 0;
                    }
                    if ($stock < $item->quantity) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Stock insuffisant pour : {$product->name} (disponible : {$stock})",
                        ], 400);
                    }
                }
            }

            // Calcul montants
            $subtotal = $cart->items->sum(fn($item) =>
                ($item->variant?->price ?? $item->product->price) * $item->quantity
            );
            $tax = 0; // TVA incluse dans le prix XOF
            $total = $subtotal;

            // Créer la commande
            $order = Order::create([
                'user_id'        => auth()->id(),
                'order_number'   => 'ORD-' . strtoupper(Str::random(10)),
                'status'         => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'shipping_cost'  => 0,
                'total_amount'   => $total,
                'notes'          => $validated['notes'] ?? null,
            ]);

            // Créer les items
            foreach ($cart->items as $cartItem) {
                $product  = $cartItem->product;
                $variant  = $cartItem->variant;
                $price    = $variant?->price ?? $product->price;

                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity'           => $cartItem->quantity,
                    'unit_price'         => $price,
                    'total_price'        => $price * $cartItem->quantity,
                ]);

                // Déduire le stock (produits physiques)
                if (!$product->is_digital) {
                    if ($variant) {
                        $variant->decrement('stock_quantity', $cartItem->quantity);
                    } else {
                        $product->decrement('stock_quantity', $cartItem->quantity);
                    }
                }
            }

            // Simuler le paiement (en prod : appel API gateway + webhook)
            $simulatedSuccess = $this->simulatePayment($validated['payment_method']);

            $paymentStatus = $simulatedSuccess ? 'completed' : 'failed';
            $orderPaymentStatus = $simulatedSuccess ? 'paid' : 'failed';
            $orderStatus = $simulatedSuccess ? 'paid' : 'pending';

            // Créer l'enregistrement de paiement
            Payment::create([
                'order_id'              => $order->id,
                'transaction_id'        => 'TXN-' . strtoupper(Str::random(12)),
                'amount'                => $total,
                'status'                => $paymentStatus,
                'payment_method'        => $validated['payment_method'],
                'gateway'               => $this->getGateway($validated['payment_method']),
                'gateway_transaction_id'=> 'SIM-' . strtoupper(Str::random(16)),
                'payment_date'          => $simulatedSuccess ? now() : null,
                'gateway_response'      => json_encode([
                    'simulated'  => true,
                    'gateway'    => $this->getGateway($validated['payment_method']),
                    'status'     => $paymentStatus,
                    'timestamp'  => now()->toISOString(),
                ]),
            ]);

            // Mettre à jour le statut de la commande
            $order->update([
                'status'         => $orderStatus,
                'payment_status' => $orderPaymentStatus,
            ]);

            // Vider le panier
            $cart->items()->delete();

            DB::commit();

            if (!$simulatedSuccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le paiement a échoué. Veuillez réessayer.',
                ], 402);
            }

            return response()->json([
                'success' => true,
                'message' => 'Commande créée et paiement confirmé.',
                'data'    => [
                    'order_id'       => $order->id,
                    'order_number'   => $order->order_number,
                    'total_amount'   => $order->total_amount,
                    'payment_method' => $order->payment_method,
                    'payment_status' => 'paid',
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function simulatePayment(string $method): bool
    {
        // En production : appel réel à l'API Orange Money / Wave / MTN / Stripe
        // Ici on simule un taux de succès de 100% (sandbox)
        return true;
    }

    private function getGateway(string $method): string
    {
        return match($method) {
            'orange_money' => 'orange_money',
            'wave'         => 'wave',
            'mtn_money'    => 'mtn_money',
            'card'         => 'stripe',
            default        => 'manual',
        };
    }

    /**
     * Récupérer les commandes de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items.product.images'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    /**
     * Récupérer une commande spécifique
     */
    public function show($id): JsonResponse
    {
        $order = Order::where('user_id', auth()->id())
            ->with(['items.product.images', 'payments'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $order]);
    }
}
