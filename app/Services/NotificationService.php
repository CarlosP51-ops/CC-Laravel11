<?php

namespace App\Services;

use App\Models\ClientNotification;
use App\Models\SellerFollow;
use App\Models\Product;
use App\Models\Seller;

class NotificationService
{
    /**
     * Notifier les abonnés d'un vendeur qu'un nouveau produit est disponible
     */
    public static function notifyNewProduct(Product $product, Seller $seller): void
    {
        $followers = SellerFollow::where('seller_id', $seller->id)
            ->pluck('user_id');

        if ($followers->isEmpty()) return;

        $notifications = $followers->map(fn($userId) => [
            'user_id'    => $userId,
            'type'       => 'new_product',
            'title'      => 'Nouveau produit de ' . $seller->store_name,
            'message'    => $seller->store_name . ' vient de publier "' . $product->name . '"',
            'link'       => '/products/' . $product->slug,
            'seller_id'  => $seller->id,
            'product_id' => $product->id,
            'is_read'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        ClientNotification::insert($notifications);
    }

    /**
     * Notifier les abonnés d'une promotion sur un produit du vendeur
     */
    public static function notifyPromotion(Product $product, Seller $seller): void
    {
        $followers = SellerFollow::where('seller_id', $seller->id)
            ->pluck('user_id');

        if ($followers->isEmpty()) return;

        $discount = $product->compare_at_price
            ? round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100)
            : 0;

        $notifications = $followers->map(fn($userId) => [
            'user_id'    => $userId,
            'type'       => 'promotion',
            'title'      => 'Promotion chez ' . $seller->store_name,
            'message'    => '"' . $product->name . '" est en promotion' . ($discount > 0 ? " (-{$discount}%)" : ''),
            'link'       => '/products/' . $product->slug,
            'seller_id'  => $seller->id,
            'product_id' => $product->id,
            'is_read'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        ClientNotification::insert($notifications);
    }
}
