<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = DB::table('sellers')->pluck('id')->toArray();
        $categories = DB::table('categories')->pluck('id')->toArray();

        if (empty($sellers) || empty($categories)) {
            $this->command->warn('Aucun vendeur ou catégorie trouvé. Lancez d\'abord les seeders de base.');
            return;
        }

        $products = [
            [
                'name'              => 'UI Kit Premium - Dashboard Template',
                'short_description' => 'Template moderne pour dashboard admin avec composants React',
                'description'       => 'Un kit UI complet avec plus de 200 composants React, compatible Tailwind CSS. Idéal pour créer des dashboards admin professionnels.',
                'price'             => 89.99,
                'compare_at_price'  => 119.99,
                'sku'               => 'UIK-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => true,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => '3D Character Pack - Fantasy Set',
                'short_description' => 'Collection de 10 personnages 3D fantasy pour jeux vidéo',
                'description'       => 'Pack complet de personnages 3D en haute résolution. Formats Blender, FBX et OBJ inclus. Rigging et animations de base fournies.',
                'price'             => 149.99,
                'compare_at_price'  => 199.99,
                'sku'               => '3DC-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Modern Font Family - Sans Serif Pro',
                'short_description' => 'Famille de polices modernes pour projets web et print',
                'description'       => 'Une famille de polices complète avec 8 graisses, ligatures et caractères spéciaux. Formats OTF, TTF et WOFF2.',
                'price'             => 49.99,
                'compare_at_price'  => null,
                'sku'               => 'FNT-001',
                'status'            => 'pending',
                'is_active'         => false,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Sound Effects Pack - Sci-Fi Edition',
                'short_description' => 'Pack de 50 effets sonores pour projets sci-fi',
                'description'       => '50 effets sonores haute qualité en WAV 24bit/48kHz. Parfait pour jeux vidéo, films et podcasts à thème science-fiction.',
                'price'             => 79.99,
                'compare_at_price'  => 99.99,
                'sku'               => 'SFX-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => true,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Illustration Pack - Business Concepts',
                'short_description' => '100 illustrations vectorielles pour présentations business',
                'description'       => 'Collection de 100 illustrations vectorielles au format SVG, AI et EPS. Style moderne et professionnel pour vos présentations.',
                'price'             => 129.99,
                'compare_at_price'  => 179.99,
                'sku'               => 'ILL-001',
                'status'            => 'rejected',
                'is_active'         => false,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Mobile App Template - E-commerce',
                'short_description' => 'Template React Native pour application e-commerce',
                'description'       => 'Template complet React Native avec panier, paiement, profil utilisateur et gestion des commandes. Compatible iOS et Android.',
                'price'             => 119.99,
                'compare_at_price'  => null,
                'sku'               => 'MOB-001',
                'status'            => 'pending',
                'is_active'         => false,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Video Template - Corporate Intro',
                'short_description' => 'Template After Effects pour intros corporate',
                'description'       => 'Template After Effects professionnel pour créer des intros d\'entreprise. Entièrement personnalisable, rendu rapide.',
                'price'             => 199.99,
                'compare_at_price'  => 249.99,
                'sku'               => 'VID-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Icon Set - 1000 Line Icons',
                'short_description' => 'Set complet d\'icônes en style line pour applications web',
                'description'       => '1000 icônes vectorielles en style line, disponibles en SVG, PNG et Webfont. Organisées en 20 catégories.',
                'price'             => 39.99,
                'compare_at_price'  => 59.99,
                'sku'               => 'ICN-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => true,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Photography Pack - Urban Landscape',
                'short_description' => 'Collection de 50 photos haute résolution paysages urbains',
                'description'       => '50 photos haute résolution (6000x4000px) de paysages urbains du monde entier. Licence commerciale incluse.',
                'price'             => 89.99,
                'compare_at_price'  => null,
                'sku'               => 'PHO-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Presentation Template - Startup Pitch',
                'short_description' => 'Template PowerPoint pour pitch deck startup',
                'description'       => 'Template PowerPoint et Keynote professionnel pour pitch deck. 50 slides uniques, animations incluses, facilement personnalisable.',
                'price'             => 69.99,
                'compare_at_price'  => 89.99,
                'sku'               => 'PPT-001',
                'status'            => 'pending',
                'is_active'         => false,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'WordPress Theme - Agency Pro',
                'short_description' => 'Thème WordPress premium pour agences créatives',
                'description'       => 'Thème WordPress responsive avec page builder intégré, 15 démos prêtes à l\'emploi et support premium 12 mois.',
                'price'             => 59.99,
                'compare_at_price'  => 79.99,
                'sku'               => 'WP-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
            [
                'name'              => 'Logo Pack - Minimal Branding',
                'short_description' => '20 logos minimalistes prêts à personnaliser',
                'description'       => '20 logos vectoriels au style minimaliste. Formats AI, EPS, SVG et PNG. Idéal pour startups et freelances.',
                'price'             => 34.99,
                'compare_at_price'  => null,
                'sku'               => 'LOG-001',
                'status'            => 'approved',
                'is_active'         => true,
                'is_digital'        => true,
                'is_promoted'       => false,
                'stock_quantity'    => 999,
            ],
        ];

        $now = Carbon::now();
        $sellerIndex = 0;
        $catIndex = 0;

        foreach ($products as $product) {
            $sellerId   = $sellers[$sellerIndex % count($sellers)];
            $categoryId = $categories[$catIndex % count($categories)];
            $sellerIndex++;
            $catIndex++;

            $slug = Str::slug($product['name']) . '-' . Str::random(4);

            // Éviter les doublons de SKU
            if (DB::table('products')->where('sku', $product['sku'])->exists()) {
                $product['sku'] .= '-' . Str::random(3);
            }

            DB::table('products')->insert(array_merge($product, [
                'seller_id'   => $sellerId,
                'category_id' => $categoryId,
                'slug'        => $slug,
                'tags'        => json_encode(['digital', 'premium', 'download']),
                'created_at'  => $now->subDays(rand(1, 60)),
                'updated_at'  => $now,
            ]));
        }

        $this->command->info('✅ ' . count($products) . ' produits créés avec succès.');
    }
}
