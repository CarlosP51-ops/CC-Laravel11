<?php

namespace App\Services;

use App\Models\ClientNotification;
use App\Models\VendorNotification;
use App\Models\SellerFollow;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS INTERNES
    // ══════════════════════════════════════════════════════════════════════════

    private static function createClient(int $userId, string $type, string $title, string $message, ?string $link = null, array $meta = []): void
    {
        ClientNotification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'is_read' => false,
        ]);
    }

    private static function createVendor(int $userId, string $type, string $title, string $message, ?string $link = null, array $meta = []): void
    {
        VendorNotification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'meta'    => $meta ?: null,
            'is_read' => false,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // COMMANDES
    // ══════════════════════════════════════════════════════════════════════════

    /** Appelé quand une commande est créée et payée */
    public static function onOrderCreated(Order $order): void
    {
        // → Client : confirmation commande
        self::createClient(
            $order->user_id,
            'order_placed',
            'Commande reçue',
            "Votre commande #{$order->order_number} a bien été reçue.",
            '/account/orders'
        );

        // → Client : confirmation paiement si payé
        if ($order->payment_status === 'paid') {
            self::createClient(
                $order->user_id,
                'payment_confirmed',
                'Paiement confirmé',
                "Le paiement de votre commande #{$order->order_number} a été confirmé.",
                '/account/orders'
            );
        }

        // → Vendeur : nouvelle commande
        if ($order->seller_id) {
            $seller = Seller::find($order->seller_id);
            if ($seller) {
                self::createVendor(
                    $seller->user_id,
                    'new_order',
                    'Nouvelle commande',
                    "Commande #{$order->order_number} — " . number_format($order->total_amount, 0, ',', ' ') . ' XOF',
                    '/vendor/orders/' . $order->id,
                    ['order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => $order->total_amount]
                );
            }
        }
    }

    /** Appelé quand le vendeur change le statut d'une commande */
    public static function onOrderStatusChanged(Order $order, string $newStatus): void
    {
        $messages = [
            'processing' => ['Commande en préparation', "Votre commande #{$order->order_number} est en cours de préparation."],
            'shipped'    => ['Commande expédiée', "Votre commande #{$order->order_number} a été expédiée."],
            'delivered'  => ['Commande livrée', "Votre commande #{$order->order_number} a été livrée. Merci !"],
            'cancelled'  => ['Commande annulée', "Votre commande #{$order->order_number} a été annulée."],
        ];

        if (!isset($messages[$newStatus])) return;

        [$title, $message] = $messages[$newStatus];

        self::createClient($order->user_id, 'order_' . $newStatus, $title, $message, '/account/orders');

        // Si annulée → notifier aussi le vendeur
        if ($newStatus === 'cancelled' && $order->seller_id) {
            $seller = Seller::find($order->seller_id);
            if ($seller) {
                self::createVendor(
                    $seller->user_id,
                    'order_cancelled',
                    'Commande annulée',
                    "La commande #{$order->order_number} a été annulée.",
                    '/vendor/orders/' . $order->id,
                    ['order_id' => $order->id, 'order_number' => $order->order_number]
                );
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRODUITS
    // ══════════════════════════════════════════════════════════════════════════

    /** Appelé quand l'admin approuve un produit */
    public static function onProductApproved(Product $product, Seller $seller): void
    {
        // → Vendeur
        self::createVendor(
            $seller->user_id,
            'product_approved',
            'Produit approuvé',
            "Votre produit \"{$product->name}\" a été approuvé et est maintenant visible.",
            '/vendor/products/' . $product->id,
            ['product_id' => $product->id, 'product_name' => $product->name]
        );

        // → Abonnés du vendeur (clients qui suivent ce vendeur)
        self::notifyNewProduct($product, $seller);
    }

    /** Appelé quand l'admin rejette un produit */
    public static function onProductRejected(Product $product, Seller $seller, ?string $reason = null): void
    {
        self::createVendor(
            $seller->user_id,
            'product_rejected',
            'Produit rejeté',
            "Votre produit \"{$product->name}\" a été rejeté." . ($reason ? " Raison : {$reason}" : ''),
            '/vendor/products/' . $product->id,
            ['product_id' => $product->id, 'product_name' => $product->name, 'reason' => $reason]
        );
    }

    /** Notifier les abonnés d'un vendeur d'un nouveau produit */
    public static function notifyNewProduct(Product $product, Seller $seller): void
    {
        $followers = SellerFollow::where('seller_id', $seller->id)->pluck('user_id');
        if ($followers->isEmpty()) return;

        $notifications = $followers->map(fn($userId) => [
            'user_id'    => $userId,
            'type'       => 'new_product',
            'title'      => 'Nouveau produit de ' . $seller->store_name,
            'message'    => $seller->store_name . ' vient de publier "' . $product->name . '"',
            'link'       => '/products/' . $product->slug,
            'is_read'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        ClientNotification::insert($notifications);
    }

    /** Notifier les abonnés d'une promotion */
    public static function notifyPromotion(Product $product, Seller $seller): void
    {
        $followers = SellerFollow::where('seller_id', $seller->id)->pluck('user_id');
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
            'is_read'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        ClientNotification::insert($notifications);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // AVIS
    // ══════════════════════════════════════════════════════════════════════════

    /** Appelé quand un client laisse un avis sur un produit */
    public static function onNewReview(int $productId, int $rating, int $reviewerId): void
    {
        $product = Product::with('seller')->find($productId);
        if (!$product || !$product->seller) return;

        self::createVendor(
            $product->seller->user_id,
            'new_review',
            'Nouvel avis reçu',
            "Un client a laissé un avis {$rating}★ sur \"{$product->name}\".",
            '/vendor/products/' . $product->id,
            ['product_id' => $product->id, 'rating' => $rating]
        );
    }

    /** Appelé quand un vendeur répond à un avis */
    public static function onReviewReply(int $clientUserId, string $productName, int $productId): void
    {
        self::createClient(
            $clientUserId,
            'review_reply',
            'Réponse à votre avis',
            "Le vendeur a répondu à votre avis sur \"{$productName}\".",
            '/products/' . $productId
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RETRAITS
    // ══════════════════════════════════════════════════════════════════════════

    /** Appelé quand l'admin approuve un retrait */
    public static function onWithdrawalApproved(int $vendorUserId, float $amount, string $reference): void
    {
        self::createVendor(
            $vendorUserId,
            'withdrawal_approved',
            'Retrait approuvé',
            "Votre demande de retrait de " . number_format($amount, 0, ',', ' ') . " XOF (réf. {$reference}) a été approuvée.",
            '/vendor/payments',
            ['amount' => $amount, 'reference' => $reference]
        );
    }

    /** Appelé quand l'admin rejette un retrait */
    public static function onWithdrawalRejected(int $vendorUserId, float $amount, string $reference, ?string $reason = null): void
    {
        self::createVendor(
            $vendorUserId,
            'withdrawal_rejected',
            'Retrait rejeté',
            "Votre demande de retrait de " . number_format($amount, 0, ',', ' ') . " XOF a été rejetée." . ($reason ? " Raison : {$reason}" : ''),
            '/vendor/payments',
            ['amount' => $amount, 'reference' => $reference, 'reason' => $reason]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // COMPTE VENDEUR
    // ══════════════════════════════════════════════════════════════════════════

    /** Appelé quand l'admin suspend ou réactive un vendeur */
    public static function onSellerStatusChanged(int $vendorUserId, bool $isActive): void
    {
        if ($isActive) {
            self::createVendor($vendorUserId, 'account_reactivated', 'Compte réactivé', 'Votre compte vendeur a été réactivé. Vous pouvez à nouveau vendre sur la plateforme.', '/vendor/dashboard');
        } else {
            self::createVendor($vendorUserId, 'account_suspended', 'Compte suspendu', 'Votre compte vendeur a été suspendu. Contactez le support pour plus d\'informations.', '/vendor/dashboard');
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MESSAGES
    // ══════════════════════════════════════════════════════════════════════════

    /** Appelé quand un message est envoyé dans une conversation */
    public static function onNewMessage(int $recipientId, string $senderName, string $senderRole): void
    {
        $recipientUser = User::find($recipientId);
        if (!$recipientUser) return;

        $title   = 'Nouveau message';
        $message = "Vous avez reçu un message de {$senderName}.";

        if ($recipientUser->role === 'client') {
            $link = '/messages';
            self::createClient($recipientId, 'new_message', $title, $message, $link);
        } elseif ($recipientUser->role === 'vendor') {
            $link = '/vendor/messages';
            self::createVendor($recipientId, 'new_message', $title, $message, $link, ['sender' => $senderName]);
        }
        // admin n'a pas de notif in-app pour les messages (géré autrement)
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STOCK FAIBLE
    // ══════════════════════════════════════════════════════════════════════════

    /** Appelé quand le stock d'un produit passe sous le seuil */
    public static function onLowStock(Product $product, Seller $seller, int $remaining): void
    {
        self::createVendor(
            $seller->user_id,
            'low_stock',
            'Stock faible',
            "Le stock de \"{$product->name}\" est faible ({$remaining} restant(s)).",
            '/vendor/products/' . $product->id,
            ['product_id' => $product->id, 'product_name' => $product->name, 'remaining' => $remaining]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEWSLETTERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Newsletter vendeur → ses clients
     * Envoie email + notif in-app
     */
    public static function vendorNewsletter(Seller $seller, string $subject, string $body): array
    {
        $clients = User::whereHas('orders', fn($q) => $q->where('seller_id', $seller->id))
            ->get(['id', 'fullname', 'email']);

        if ($clients->isEmpty()) return ['sent' => 0, 'failed' => 0, 'total' => 0];

        $sent = 0; $failed = 0;
        $senderName = $seller->store_name ?? $seller->user?->fullname ?? 'Vendeur';

        foreach ($clients as $client) {
            // Email
            try {
                Mail::send([], [], function ($mail) use ($client, $subject, $body, $senderName) {
                    $mail->to($client->email, $client->fullname)
                         ->from(config('mail.from.address'), $senderName)
                         ->subject($subject)
                         ->html(self::buildEmailHtml($client->fullname, $subject, $body, $senderName));
                });
                $sent++;
            } catch (\Exception $e) {
                $failed++;
            }

            // Notif in-app
            self::createClient(
                $client->id,
                'newsletter',
                "Message de {$senderName}",
                $subject,
                null
            );
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => $clients->count()];
    }

    /**
     * Newsletter admin → cible flexible
     * $target : 'all_clients' | 'all_vendors' | 'all_users' | user_id (int)
     */
    public static function adminNewsletter(string $subject, string $body, $target): array
    {
        $users = self::resolveNewsletterTarget($target);
        if ($users->isEmpty()) return ['sent' => 0, 'failed' => 0, 'total' => 0];

        $sent = 0; $failed = 0;
        $platformName = config('app.name', 'La Plateforme');

        foreach ($users as $user) {
            // Email
            try {
                Mail::send([], [], function ($mail) use ($user, $subject, $body, $platformName) {
                    $mail->to($user->email, $user->fullname)
                         ->from(config('mail.from.address'), $platformName)
                         ->subject($subject)
                         ->html(self::buildEmailHtml($user->fullname, $subject, $body, $platformName));
                });
                $sent++;
            } catch (\Exception $e) {
                $failed++;
            }

            // Notif in-app selon le rôle
            if ($user->role === 'client') {
                self::createClient($user->id, 'newsletter', "Message de {$platformName}", $subject, null);
            } elseif ($user->role === 'vendor') {
                self::createVendor($user->id, 'newsletter', "Message de {$platformName}", $subject, null);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => $users->count()];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════════════════

    private static function resolveNewsletterTarget($target)
    {
        if ($target === 'all_clients') {
            return User::where('role', 'client')->get(['id', 'fullname', 'email', 'role']);
        }
        if ($target === 'all_vendors') {
            return User::where('role', 'vendor')->get(['id', 'fullname', 'email', 'role']);
        }
        if ($target === 'all_users') {
            return User::whereIn('role', ['client', 'vendor'])->get(['id', 'fullname', 'email', 'role']);
        }
        // ID spécifique
        if (is_numeric($target)) {
            return User::where('id', $target)->get(['id', 'fullname', 'email', 'role']);
        }
        return collect();
    }

    private static function buildEmailHtml(string $recipientName, string $subject, string $body, string $senderName): string
    {
        return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f9fafb;margin:0;padding:24px">'
            . '<div style="max-width:600px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1)">'
            . '<div style="background:linear-gradient(135deg,#1d4ed8,#7c3aed);padding:24px 32px">'
            . '<h1 style="color:#fff;margin:0;font-size:20px">' . e($subject) . '</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="color:#374151;margin-top:0">Bonjour ' . e($recipientName) . ',</p>'
            . '<div style="color:#374151;line-height:1.7">' . nl2br(e($body)) . '</div>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f3f4f6;border-top:1px solid #e5e7eb">'
            . '<p style="color:#9ca3af;font-size:12px;margin:0">Ce message vous a été envoyé par ' . e($senderName) . '.</p>'
            . '</div>'
            . '</div></body></html>';
    }
}
