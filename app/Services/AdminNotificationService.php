<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\User;
use App\Models\Seller;
use App\Models\Product;
use App\Models\Withdrawal;
use App\Models\Review;

class AdminNotificationService
{
    /**
     * Synchronise les notifications depuis les données réelles de la plateforme.
     * Appelé à chaque requête GET /admin/notifications pour garder la liste à jour.
     */
    public function sync(): void
    {
        $this->syncWithdrawals();
        $this->syncPendingProducts();
        $this->syncNewVendors();
        $this->syncNewClients();
        $this->syncBadReviews();
        $this->syncFailedPayments();
        $this->syncNewOrders();
    }

    // ── Retraits en attente ───────────────────────────────────────────────────
    private function syncWithdrawals(): void
    {
        $withdrawals = Withdrawal::with('seller')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($withdrawals as $w) {
            AdminNotification::createUnique([
                'type'        => 'withdrawal_request',
                'title'       => 'Retrait en attente',
                'subtitle'    => ($w->seller?->store_name ?? 'Vendeur') . ' — ' . number_format($w->amount, 0, ',', ' ') . ' XOF',
                'body'        => 'Une demande de retrait nécessite votre approbation.',
                'link'        => '/admin/payments?tab=withdrawals',
                'entity_type' => 'withdrawal',
                'entity_id'   => $w->id,
                'meta'        => ['amount' => $w->amount, 'seller' => $w->seller?->store_name, 'reference' => $w->reference],
            ]);
        }
    }

    // ── Produits en attente de modération ─────────────────────────────────────
    private function syncPendingProducts(): void
    {
        $products = Product::with('seller')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($products as $p) {
            AdminNotification::createUnique([
                'type'        => 'product_pending',
                'title'       => 'Produit à modérer',
                'subtitle'    => $p->name . ' — ' . ($p->seller?->store_name ?? ''),
                'body'        => 'Un nouveau produit attend votre validation.',
                'link'        => '/admin/products',
                'entity_type' => 'product',
                'entity_id'   => $p->id,
                'meta'        => ['product_name' => $p->name, 'seller' => $p->seller?->store_name, 'price' => $p->price],
            ]);
        }
    }

    // ── Nouveaux vendeurs (non vérifiés) ──────────────────────────────────────
    private function syncNewVendors(): void
    {
        $sellers = Seller::with('user')
            ->where('is_verified', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($sellers as $s) {
            AdminNotification::createUnique([
                'type'        => 'new_vendor',
                'title'       => 'Nouveau vendeur inscrit',
                'subtitle'    => $s->store_name . ' — ' . ($s->user?->email ?? ''),
                'body'        => 'Un nouveau vendeur attend la vérification de son compte.',
                'link'        => '/admin/sellers',
                'entity_type' => 'seller',
                'entity_id'   => $s->id,
                'meta'        => ['store_name' => $s->store_name, 'email' => $s->user?->email],
            ]);
        }
    }

    // ── Nouveaux clients (dernières 24h) ──────────────────────────────────────
    private function syncNewClients(): void
    {
        $clients = User::where('role', 'client')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        foreach ($clients as $u) {
            AdminNotification::createUnique([
                'type'        => 'new_client',
                'title'       => 'Nouveau client inscrit',
                'subtitle'    => $u->fullname . ' — ' . $u->email,
                'body'        => 'Un nouveau client vient de rejoindre la plateforme.',
                'link'        => '/admin/users',
                'entity_type' => 'user',
                'entity_id'   => $u->id,
                'meta'        => ['name' => $u->fullname, 'email' => $u->email],
            ]);
        }
    }

    // ── Avis négatifs (≤ 2 étoiles, derniers 7 jours) ────────────────────────
    private function syncBadReviews(): void
    {
        $reviews = Review::with(['product', 'user'])
            ->where('rating', '<=', 2)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($reviews as $r) {
            AdminNotification::createUnique([
                'type'        => 'bad_review',
                'title'       => 'Avis négatif signalé',
                'subtitle'    => $r->rating . '★ sur "' . ($r->product?->name ?? 'Produit') . '"',
                'body'        => 'Par ' . ($r->user?->fullname ?? 'Client') . ' : ' . ($r->comment ?? ''),
                'link'        => '/admin/performance?tab=alerts',
                'entity_type' => 'review',
                'entity_id'   => $r->id,
                'meta'        => ['rating' => $r->rating, 'product' => $r->product?->name, 'user' => $r->user?->fullname],
            ]);
        }
    }

    // ── Paiements échoués (dernières 48h) ─────────────────────────────────────
    private function syncFailedPayments(): void
    {
        $orders = Order::with('user')
            ->where('payment_status', 'failed')
            ->where('created_at', '>=', now()->subHours(48))
            ->get();

        foreach ($orders as $o) {
            AdminNotification::createUnique([
                'type'        => 'payment_failed',
                'title'       => 'Paiement échoué',
                'subtitle'    => ($o->user?->fullname ?? 'Client') . ' — ' . number_format($o->total_amount, 0, ',', ' ') . ' XOF',
                'body'        => 'La commande #' . $o->order_number . ' a un paiement en échec.',
                'link'        => '/admin/payments',
                'entity_type' => 'order',
                'entity_id'   => $o->id,
                'meta'        => ['order_number' => $o->order_number, 'amount' => $o->total_amount, 'user' => $o->user?->fullname],
            ]);
        }
    }

    // ── Nouvelles commandes (dernières 2h) ────────────────────────────────────
    private function syncNewOrders(): void
    {
        $orders = Order::with('user')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subHours(2))
            ->get();

        foreach ($orders as $o) {
            AdminNotification::createUnique([
                'type'        => 'new_order',
                'title'       => 'Nouvelle commande',
                'subtitle'    => ($o->user?->fullname ?? 'Client') . ' — ' . number_format($o->total_amount, 0, ',', ' ') . ' XOF',
                'body'        => 'Commande #' . $o->order_number . ' payée avec succès.',
                'link'        => '/admin/users',
                'entity_type' => 'order',
                'entity_id'   => $o->id,
                'meta'        => ['order_number' => $o->order_number, 'amount' => $o->total_amount],
            ]);
        }
    }
}
