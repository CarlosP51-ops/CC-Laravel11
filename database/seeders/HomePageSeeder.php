<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Seller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class HomePageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Démarrage du seeding...');

        // 1. Créer des clients pour les reviews
        $clients = [];
        for ($i = 1; $i <= 10; $i++) {
            $email = "client$i@example.com";
            $client = User::where('email', $email)->first();
            
            if (!$client) {
                $client = User::create([
                    'fullname' => "Client $i",
                    'email' => $email,
                    'password' => Hash::make('password123'),
                    'role' => 'client',
                    'phone' => '+33698765' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]);
            }
            
            $clients[] = $client;
        }

        // 2. Créer des vendeurs
        $vendors = [];
        for ($i = 1; $i <= 5; $i++) {
            $email = "vendor$i@example.com";
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $user = User::create([
                    'fullname' => "Vendeur $i",
                    'email' => $email,
                    'password' => Hash::make('password123'),
                    'role' => 'vendor',
                    'phone' => '+33612345' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]);
            }

            $seller = Seller::where('user_id', $user->id)->first();
            
            if (!$seller) {
                $seller = Seller::create([
                    'user_id' => $user->id,
                    'store_name' => "Boutique $i",
                    'slug' => "boutique-$i",
                    'description' => "Description de la boutique $i",
                    'is_verified' => true,
                    'is_active' => true,
                ]);
            }

            $vendors[] = $seller;
        }

        // 3. Créer des catégories
        $categories = [
            ['name' => 'Électronique', 'slug' => 'electronique', 'description' => 'Produits électroniques et high-tech'],
            ['name' => 'Mode', 'slug' => 'mode', 'description' => 'Vêtements et accessoires'],
            ['name' => 'Maison & Jardin', 'slug' => 'maison-jardin', 'description' => 'Décoration et équipement'],
            ['name' => 'Sport & Loisirs', 'slug' => 'sport-loisirs', 'description' => 'Équipement sportif'],
            ['name' => 'Livres', 'slug' => 'livres', 'description' => 'Livres et magazines'],
            ['name' => 'Jouets', 'slug' => 'jouets', 'description' => 'Jouets pour enfants'],
            ['name' => 'Beauté', 'slug' => 'beaute', 'description' => 'Produits de beauté'],
            ['name' => 'Alimentation', 'slug' => 'alimentation', 'description' => 'Produits alimentaires'],
        ];

        $createdCategories = [];
        foreach ($categories as $category) {
            $existingCategory = Category::where('slug', $category['slug'])->first();
            
            if (!$existingCategory) {
                $createdCategories[] = Category::create([
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'order' => 0,
                ]);
            } else {
                $createdCategories[] = $existingCategory;
            }
        }

        // 4. Créer des produits
        $this->command->info('📦 Suppression des anciens produits...');
        Review::query()->delete(); // Supprimer toutes les reviews
        ProductImage::query()->delete(); // Supprimer toutes les images
        Product::query()->delete(); // Supprimer tous les produits existants
        
        $productNames = [
            'Smartphone Premium',
            'Laptop Ultra',
            'Casque Audio',
            'Montre Connectée',
            'Tablette Pro',
            'Appareil Photo',
            'Console de Jeu',
            'Écouteurs Sans Fil',
            'Clavier Mécanique',
            'Souris Gaming',
            'T-Shirt Premium',
            'Jean Slim',
            'Robe Élégante',
            'Chaussures Sport',
            'Sac à Main',
            'Canapé Moderne',
            'Table Basse',
            'Lampe Design',
            'Tapis Déco',
            'Coussin Confort',
        ];

        foreach ($productNames as $index => $name) {
            $category = $createdCategories[array_rand($createdCategories)];
            $seller = $vendors[array_rand($vendors)];
            
            $price = rand(20, 500);
            $comparePrice = $price + rand(10, 100);

            $product = Product::create([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $index,
                'description' => "Description détaillée du produit $name. Un excellent produit de qualité supérieure.",
                'short_description' => "Un excellent $name de qualité",
                'price' => $price,
                'compare_at_price' => $comparePrice,
                'stock_quantity' => rand(10, 100),
                'sku' => 'SKU-' . strtoupper(Str::random(8)),
                'is_active' => true,
            ]);

            // Ajouter une image (placeholder)
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => 'https://via.placeholder.com/400x400?text=' . urlencode($name),
                'is_primary' => true,
                'order' => 0,
            ]);

            // Ajouter des reviews
            $reviewCount = rand(5, 20);
            for ($r = 0; $r < $reviewCount; $r++) {
                $client = $clients[array_rand($clients)];
                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $client->id,
                    'rating' => rand(3, 5),
                    'comment' => 'Excellent produit, je recommande !',
                    'is_approved' => true,
                ]);
            }
        }

        $this->command->info('✅ Données de la page d\'accueil créées avec succès!');
        $this->command->info('👥 ' . count($clients) . ' clients créés');
        $this->command->info('📊 ' . count($vendors) . ' vendeurs créés');
        $this->command->info('📁 ' . count($createdCategories) . ' catégories créées');
        $this->command->info('📦 ' . count($productNames) . ' produits créés');
    }
}
