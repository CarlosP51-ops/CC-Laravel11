<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Withdrawal;
use App\Models\Seller;
use Illuminate\Support\Str;

class WithdrawalSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = Seller::all();
        if ($sellers->isEmpty()) return;

        $methods = ['bank_transfer', 'mtn_momo', 'moov_money'];
        $statuses = ['pending', 'pending', 'processing', 'completed', 'completed', 'completed', 'rejected', 'cancelled'];

        foreach ($sellers->take(8) as $index => $seller) {
            $amount = round(rand(200, 5000) + rand(0, 99) / 100, 2);
            $fee = round($amount * 0.01, 2);
            $method = $methods[$index % count($methods)];

            Withdrawal::create([
                'seller_id' => $seller->id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => round($amount - $fee, 2),
                'method' => $method,
                'gateway' => $method === 'bank_transfer' ? 'stripe' : 'fedapay',
                'payout_details' => $method === 'bank_transfer'
                    ? ['account' => 'FR76 1234 5678 9012 3456 7890 123', 'bank' => 'BNP Paribas']
                    : ['phone' => '+22961' . rand(100000, 999999), 'network' => strtoupper(str_replace('_', ' ', $method))],
                'status' => $statuses[$index % count($statuses)],
                'reference' => 'WDR-' . strtoupper(Str::random(8)),
                'risk_score' => rand(0, 30),
                'notes' => null,
                'processed_at' => in_array($statuses[$index % count($statuses)], ['completed', 'rejected']) ? now()->subHours(rand(1, 48)) : null,
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }
    }
}
