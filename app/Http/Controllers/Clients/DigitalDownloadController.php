<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DigitalDownloadController extends Controller
{
    public function download(int $orderId, int $productId)
    {
        $user = Auth::user();

        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Commande introuvable ou non payée.'], 404);
        }

        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if (!$orderItem) {
            return response()->json(['success' => false, 'message' => 'Ce produit ne fait pas partie de cette commande.'], 403);
        }

        $product = Product::find($productId);

        if (!$product || !$product->is_digital) {
            return response()->json(['success' => false, 'message' => 'Ce produit n\'est pas un produit digital.'], 400);
        }

        if (!$product->digital_file_path) {
            return response()->json(['success' => false, 'message' => 'Le fichier de ce produit n\'est pas encore disponible.'], 404);
        }

        // En production : générer une URL signée Supabase (valable 1h)
        if (config('app.env') === 'production') {
            $signedUrl = StorageService::signedUrl($product->digital_file_path, 3600);
            if ($signedUrl) {
                return response()->json([
                    'success'      => true,
                    'download_url' => $signedUrl,
                    'filename'     => $product->name . '.' . pathinfo($product->digital_file_path, PATHINFO_EXTENSION),
                    'expires_in'   => 3600,
                ]);
            }
            return response()->json(['success' => false, 'message' => 'Impossible de générer le lien de téléchargement.'], 500);
        }

        // En local : servir le fichier directement
        if (!Storage::disk('private')->exists($product->digital_file_path)) {
            return response()->json(['success' => false, 'message' => 'Fichier introuvable.'], 404);
        }

        $filename = $product->name . '.' . pathinfo($product->digital_file_path, PATHINFO_EXTENSION);
        return Storage::disk('private')->download($product->digital_file_path, $filename);
    }

    public function check(int $orderId, int $productId)
    {
        $user = Auth::user();

        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->first();

        if (!$order) return response()->json(['success' => false, 'downloadable' => false]);

        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->first();

        if (!$orderItem) return response()->json(['success' => false, 'downloadable' => false]);

        $product = Product::find($productId);

        $downloadable = $product && $product->is_digital && $product->digital_file_path;

        return response()->json([
            'success'      => true,
            'downloadable' => $downloadable,
            'product_name' => $product?->name,
        ]);
    }
}
