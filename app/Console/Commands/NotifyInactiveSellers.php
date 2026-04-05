<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\Seller;
use App\Models\Order;
use Illuminate\Console\Command;

class NotifyInactiveSellers extends Command
{
    protected $signature   = 'notifications:inactive-sellers {--days=30}';
    protected $description = 'Notifie l\'admin des vendeurs sans vente depuis X jours';

    public function handle(): void
    {
        $days = (int) $this->option('days');

        $inactiveSellers = Seller::where('is_active', true)
            ->whereDoesntHave('orders', function ($q) use ($days) {
                $q->where('payment_status', 'paid')
                  ->where('created_at', '>=', now()->subDays($days));
            })
            ->whereHas('orders') // a déjà eu des commandes (pas les nouveaux)
            ->with('user')
            ->get();

        foreach ($inactiveSellers as $seller) {
            AdminNotification::createUnique([
                'type'        => 'seller_inactive',
                'title'       => 'Vendeur inactif',
                'subtitle'    => $seller->store_name . ' — aucune vente depuis {$days} jours',
                'body'        => "Le vendeur {$seller->store_name} n'a enregistré aucune vente depuis {$days} jours.",
                'link'        => '/admin/sellers/' . $seller->id,
                'entity_type' => 'seller',
                'entity_id'   => $seller->id,
                'meta'        => ['store_name' => $seller->store_name, 'days' => $days],
            ]);
        }

        $this->info("Vérification terminée : {$inactiveSellers->count()} vendeur(s) inactif(s) notifié(s).");
    }
}
