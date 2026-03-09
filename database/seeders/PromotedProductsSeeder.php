<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class PromotedProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Marquer les 10 premiers produits avec compare_at_price comme promus
        Product::whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->limit(10)
            ->update(['is_promoted' => true]);

        $this->command->info('✅ 10 produits marqués comme promus');
    }
}
