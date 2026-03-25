<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $productId = 22;
        $product   = DB::table('products')->where('id', $productId)->first();

        if (!$product) {
            $this->command->error("Produit #$productId introuvable.");
            return;
        }

        $sellerId  = $product->seller_id;
        $unitPrice = (float) $product->price;
        $clientIds = DB::table('users')->where('role', 'client')->pluck('id')->toArray();

        if (empty($clientIds)) {
            $this->command->error("Aucun client trouvé.");
            return;
        }

        $scenarios = [
            ['status' => 'delivered', 'payment_status' => 'paid',    'qty' => 2, 'days_ago' => 45],
            ['status' => 'delivered', 'payment_status' => 'paid',    'qty' => 1, 'days_ago' => 38],
            ['status' => 'delivered', 'payment_status' => 'paid',    'qty' => 3, 'days_ago' => 30],
            ['status' => 'delivered', 'payment_status' => 'paid',    'qty' => 1, 'days_ago' => 25],
            ['status' => 'delivered', 'payment_status' => 'paid',    'qty' => 2, 'days_ago' => 20],
            ['status' => 'shipped',   'payment_status' => 'paid',    'qty' => 1, 'days_ago' => 10],
            ['status' => 'shipped',   'payment_status' => 'paid',    'qty' => 2, 'days_ago' => 7],
            ['status' => 'pending',   'payment_status' => 'pending', 'qty' => 1, 'days_ago' => 3],
            ['status' => 'pending',   'payment_status' => 'pending', 'qty' => 1, 'days_ago' => 2],
            ['status' => 'cancelled', 'payment_status' => 'failed',  'qty' => 1, 'days_ago' => 15],
            ['status' => 'cancelled', 'payment_status' => 'failed',  'qty' => 2, 'days_ago' => 28],
            ['status' => 'delivered', 'payment_status' => 'paid',    'qty' => 4, 'days_ago' => 60],
        ];

        $count = 0;
        foreach ($scenarios as $i => $s) {
            $clientId    = $clientIds[$i % count($clientIds)];
            $qty         = $s['qty'];
            $totalItem   = $unitPrice * $qty;
            $tax         = round($totalItem * 0.20, 2);
            $totalOrder  = $totalItem + $tax;
            $createdAt   = Carbon::now()->subDays($s['days_ago']);
            $orderNumber = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $orderId = DB::table('orders')->insertGetId([
                'user_id'        => $clientId,
                'seller_id'      => $sellerId,
                'order_number'   => $orderNumber,
                'status'         => $s['status'],
                'subtotal'       => $totalItem,
                'tax'            => $tax,
                'shipping_cost'  => 0,
                'total_amount'   => $totalOrder,
                'payment_method' => 'card',
                'payment_status' => $s['payment_status'],
                'created_at'     => $createdAt,
                'updated_at'     => $createdAt,
            ]);

            DB::table('order_items')->insert([
                'order_id'    => $orderId,
                'product_id'  => $productId,
                'quantity'    => $qty,
                'unit_price'  => $unitPrice,
                'total_price' => $totalItem,
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ]);

            $count++;
        }

        $this->command->info("✅ $count commandes créées pour le produit #$productId ({$product->name}).");
    }
}
