<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CartTestSeeder extends Seeder
{
    /**
     * Seed test data for Cart API
     */
    public function run(): void
    {
        // Créer un vendeur
        $seller = Seller::firstOrCreate(
            ['email' => 'seller@test.com'],
            [
                'user_id' => User::factory()->create(['role' => 'seller'])->id,
                'business_name' => 'TechStore',
                'store_name' => 'TechStore',
                'slug' => 'techstore',
                'phone' => '+33123456789',
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        // Créer une catégorie
        $category = Category::firstOrCreate(
            ['slug' => 'electronique'],
            [
                'name' => 'Électronique',
                'description' => 'Produits électroniques et high-tech',
                'is_active' => true,
                'order' => 1,
            ]
        );

        // Créer des produits de test
        $products = [
            [
                'name' => 'Laptop Gaming Pro',
                'slug' => 'laptop-gaming-pro',
                'description' => 'Laptop haute performance pour gaming et création de contenu. Processeur Intel i9, carte graphique RTX 4080.',
                'short_description' => 'Laptop gaming haute performance',
                'price' => 1299.99,
                'compare_at_price' => 1499.99,
                'sku' => 'LAP-001',
                'stock_quantity' => 15,
            ],
            [
                'name' => 'Souris Gaming RGB',
                'slug' => 'souris-gaming-rgb',
                'description' => 'Souris gaming avec éclairage RGB personnalisable, 16000 DPI, 8 boutons programmables.',
                'short_description' => 'Souris gaming RGB 16000 DPI',
                'price' => 49.99,
                'compare_at_price' => 69.99,
                'sku' => 'MOU-001',
                'stock_quantity' => 50,
            ],
            [
                'name' => 'Clavier Mécanique',
                'slug' => 'clavier-mecanique',
                'description' => 'Clavier mécanique avec switches Cherry MX Red, rétroéclairage RGB, repose-poignet.',
                'short_description' => 'Clavier mécanique Cherry MX',
                'price' => 129.99,
                'compare_at_price' => 159.99,
                'sku' => 'KEY-001',
                'stock_quantity' => 30,
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::firstOrCreate(
                ['sku' => $productData['sku']],
                array_merge($productData, [
                    'seller_id' => $seller->id,
                    'category_id' => $category->id,
                    'is_active' => true,
                ])
            );

            // Ajouter une image
            ProductImage::firstOrCreate(
                ['product_id' => $product->id],
                [
                    'image_path' => 'products/' . $product->slug . '.jpg',
                    'is_primary' => true,
                ]
            );

            // Ajouter des variantes pour le laptop
            if ($product->sku === 'LAP-001') {
                $variants = [
                    ['name' => '16GB RAM - 512GB SSD', 'sku' => 'LAP-16-512', 'price' => 1299.99, 'stock' => 10],
                    ['name' => '32GB RAM - 1TB SSD', 'sku' => 'LAP-32-1TB', 'price' => 1599.99, 'stock' => 5],
                ];

                foreach ($variants as $variantData) {
                    ProductVariant::firstOrCreate(
                        ['sku' => $variantData['sku']],
                        [
                            'product_id' => $product->id,
                            'name' => $variantData['name'],
                            'price' => $variantData['price'],
                            'stock_quantity' => $variantData['stock'],
                        ]
                    );
                }
            }
        }

        $this->command->info('✅ Données de test pour l\'API Panier créées avec succès !');
        $this->command->info('📧 Email vendeur : seller@test.com');
        $this->command->info('🏪 Catégorie : Électronique');
        $this->command->info('📦 Produits : ' . count($products));
    }
}
