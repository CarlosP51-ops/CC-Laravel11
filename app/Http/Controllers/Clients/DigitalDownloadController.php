<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DigitalDownloadController extends Controller
{
    /**
     * Télécharger un fichier digital après achat confirmé
     * GET /orders/{orderId}/download/{productId}
     */
    public function download(int $orderId, int $productId)
    {
        $user = Auth::user();

        // Vérifier que la commande appartient au client et est payée
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable ou non payée.',
            ], 404);
        }

        // Vérifier que le produit fait partie de la commande
        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit ne fait pas partie de cette commande.',
            ], 403);
        }

        // Récupérer le produit
        $product = Product::find($productId);

        if (!$product || !$product->is_digital) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n\'est pas un produit digital.',
            ], 400);
        }

        if (!$product->digital_file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier de ce produit n\'est pas encore disponible.',
            ], 404);
        }

        // Vérifier que le fichier existe
        if (!Storage::disk('private')->exists($product->digital_file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier introuvable sur le serveur.',
            ], 404);
        }

        // Retourner le fichier en téléchargement
        $filename = $product->name . '.' . pathinfo($product->digital_file_path, PATHINFO_EXTENSION);

        return Storage::disk('private')->download(
            $product->digital_file_path,
            $filename
        );
    }

    /**
     * Vérifier si un produit digital est téléchargeable pour une commande
     * GET /orders/{orderId}/download/{productId}/check
     */
    public function check(int $orderId, int $productId)
    {
        $user = Auth::user();

        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'downloadable' => false]);
        }

        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if (!$orderItem) {
            return response()->json(['success' => false, 'downloadable' => false]);
        }

        $product = Product::find($productId);

        $downloadable = $product
            && $product->is_digital
            && $product->digital_file_path
            && Storage::disk('private')->exists($product->digital_file_path);

        return response()->json([
            'success' => true,
            'downloadable' => $downloadable,
            'product_name' => $product?->name,
        ]);
    }
}
