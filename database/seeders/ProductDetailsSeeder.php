<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Seller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ProductDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Démarrage du seeding pour la page de détails du produit...');

        // 1. Créer ou récupérer des clients
        $clients = [];
        for ($i = 1; $i <= 15; $i++) {
            $email = "client$i@example.com";
            $client = User::firstOrCreate(
                ['email' => $email],
                [
                    'fullname' => "Client Test $i",
                    'password' => Hash::make('password123'),
                    'role' => 'client',
                    'phone' => '+33698765' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]
            );
            $clients[] = $client;
        }

        // 2. Créer ou récupérer un vendeur vérifié
        $vendorEmail = "vendor.premium@example.com";
        $vendorUser = User::firstOrCreate(
            ['email' => $vendorEmail],
            [
                'fullname' => "TechDesign Studio",
                'password' => Hash::make('password123'),
                'role' => 'vendor',
                'phone' => '+33612345678',
            ]
        );

        $seller = Seller::firstOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'store_name' => "TechDesign Studio",
                'slug' => "techdesign-studio",
                'description' => "Créateur de templates et UI kits professionnels depuis 2020. Plus de 10 000 clients satisfaits dans le monde entier.",
                'is_verified' => true,
                'is_active' => true,
                'logo' => 'https://via.placeholder.com/100x100?text=TDS',
            ]
        );

        // 3. Créer ou récupérer une catégorie
        $category = Category::firstOrCreate(
            ['slug' => 'templates-ui-kits'],
            [
                'name' => 'Templates & UI Kits',
                'description' => 'Templates web, dashboards et kits d\'interface utilisateur',
                'is_active' => true,
                'order' => 1,
            ]
        );

        // 4. Créer un produit détaillé
        $this->command->info('📦 Création du produit de démonstration...');
        
        // Supprimer le produit s'il existe déjà
        $existingProduct = Product::where('slug', 'template-dashboard-react-pro-2024')->first();
        if ($existingProduct) {
            $this->command->info('🗑️  Suppression de l\'ancien produit...');
            // Supprimer les avis, images et commandes liés
            Review::where('product_id', $existingProduct->id)->delete();
            ProductImage::where('product_id', $existingProduct->id)->delete();
            OrderItem::where('product_id', $existingProduct->id)->delete();
            $existingProduct->delete();
        }
        
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Template Dashboard React Pro 2024',
            'slug' => 'template-dashboard-react-pro-2024',
            'description' => "Ce template React professionnel est conçu pour créer rapidement des dashboards modernes, performants et responsive. Il inclut des composants réutilisables, une architecture propre et une excellente expérience utilisateur.

🎯 Caractéristiques principales :
• Architecture modulaire - Code propre et bien structuré, facile à maintenir et étendre
• Design responsive - S'adapte parfaitement à tous les appareils et tailles d'écran
• Performance optimisée - Chargement rapide avec lazy loading et code splitting
• Internationalisation prête - Support multi-langues intégré
• Dark mode inclus - Thème sombre élégant et moderne
• Documentation complète - Guide détaillé pour une prise en main rapide

📦 Ce que vous obtenez :
• Code source complet et commenté
• Documentation technique détaillée (50+ pages)
• Assets PSD et Figma inclus
• 100+ composants réutilisables
• Exemples d'utilisation avancée
• Tests unitaires et d'intégration
• Configuration CI/CD prête
• Support technique prioritaire pendant 1 an

🔧 Technologies utilisées :
• React 18+ avec Hooks
• Vite pour un build ultra-rapide
• Tailwind CSS pour le styling
• Redux Toolkit pour la gestion d'état
• React Router v6 pour la navigation
• Axios pour les requêtes API
• Chart.js pour les graphiques
• React Query pour le cache

✨ Fonctionnalités incluses :
• Authentification complète (login, register, reset password)
• Gestion des utilisateurs et rôles
• Dashboard avec statistiques en temps réel
• Tableaux de données avec tri, filtres et pagination
• Formulaires avancés avec validation
• Upload de fichiers avec drag & drop
• Notifications toast élégantes
• Modales et popovers
• Sidebar responsive avec menu multi-niveaux
• Profil utilisateur éditable
• Paramètres de l'application
• Mode maintenance
• Pages d'erreur personnalisées (404, 500, etc.)

🎨 Design moderne :
• Interface épurée et professionnelle
• Animations fluides et subtiles
• Icônes Lucide React incluses
• Palette de couleurs personnalisable
• Typographie soignée
• Espacement cohérent
• Accessibilité WCAG 2.1 niveau AA

💼 Cas d'usage :
• Dashboard d'administration
• Plateforme SaaS
• CRM / ERP
• Application de gestion
• Tableau de bord analytique
• Outil de monitoring
• Interface de back-office

🚀 Démarrage rapide :
1. Téléchargez et décompressez le fichier
2. Installez les dépendances : npm install
3. Lancez le serveur de dev : npm run dev
4. Personnalisez selon vos besoins
5. Déployez en production : npm run build

📞 Support inclus :
• Réponse sous 24h (jours ouvrés)
• Aide à l'installation et configuration
• Résolution de bugs
• Conseils d'optimisation
• Mises à jour gratuites pendant 1 an

⚡ Mises à jour régulières :
Nous publions des mises à jour mensuelles avec de nouvelles fonctionnalités, corrections de bugs et améliorations de performance. Toutes les mises à jour sont gratuites pendant 1 an.

🏆 Garantie satisfait ou remboursé :
Si vous n'êtes pas satisfait, nous vous remboursons intégralement sous 30 jours, sans question.

📄 Licence :
Licence étendue incluse - Utilisez ce template pour un nombre illimité de projets personnels et commerciaux.",
            'short_description' => "Template React professionnel pour créer des dashboards modernes. Inclut 100+ composants, documentation complète et support prioritaire.",
            'price' => 49.00,
            'compare_at_price' => 79.00,
            'stock_quantity' => 999,
            'sku' => 'TEMPLATE-REACT-PRO-2024',
            'is_active' => true,
        ]);

        // 5. Ajouter plusieurs images
        $this->command->info('🖼️  Ajout des images du produit...');
        
        $images = [
            ['url' => 'https://via.placeholder.com/800x600/4F46E5/FFFFFF?text=Dashboard+Main', 'is_primary' => true],
            ['url' => 'https://via.placeholder.com/800x600/7C3AED/FFFFFF?text=Analytics+View', 'is_primary' => false],
            ['url' => 'https://via.placeholder.com/800x600/EC4899/FFFFFF?text=Mobile+Responsive', 'is_primary' => false],
            ['url' => 'https://via.placeholder.com/800x600/10B981/FFFFFF?text=Code+Preview', 'is_primary' => false],
        ];

        foreach ($images as $index => $imageData) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imageData['url'],
                'is_primary' => $imageData['is_primary'],
                'order' => $index,
            ]);
        }

        // 6. Créer des commandes pour certains clients (pour les achats vérifiés)
        $this->command->info('🛒 Création des commandes pour les achats vérifiés...');
        
        $verifiedBuyers = array_slice($clients, 0, 8); // 8 premiers clients ont acheté
        foreach ($verifiedBuyers as $client) {
            $subtotal = $product->price;
            $tax = round($subtotal * 0.20, 2); // TVA 20%
            $shippingCost = 0; // Produit numérique, pas de frais de port
            $total = $subtotal + $tax + $shippingCost;
            
            $order = Order::create([
                'user_id' => $client->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'status' => 'delivered',
                'payment_status' => 'paid',
                'payment_method' => 'stripe',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'total_price' => $product->price,
            ]);
        }

        // 7. Créer des avis détaillés
        $this->command->info('⭐ Création des avis clients...');
        
        $reviews = [
            [
                'client' => $clients[0],
                'rating' => 5,
                'comment' => "Excellent produit ! Le template est très bien structuré et facile à personnaliser. La documentation est complète et les composants sont réutilisables. J'ai pu créer mon dashboard en moins d'une semaine. Le support a répondu rapidement à mes questions. Je le recommande vivement pour tout projet professionnel.",
                'days_ago' => 3
            ],
            [
                'client' => $clients[1],
                'rating' => 5,
                'comment' => "Parfait pour mon projet de SaaS ! L'intégration avec Tailwind CSS est fluide et les performances sont excellentes. Le code est propre, bien commenté et suit les meilleures pratiques React. Les animations sont subtiles et élégantes. Vraiment un excellent investissement.",
                'days_ago' => 5
            ],
            [
                'client' => $clients[2],
                'rating' => 4,
                'comment' => "Bon rapport qualité/prix. Le template est complet et fonctionnel. J'aurais aimé plus d'exemples d'utilisation avancée et quelques composants supplémentaires pour les formulaires complexes, mais dans l'ensemble c'est un très bon produit. Le dark mode est particulièrement réussi.",
                'days_ago' => 7
            ],
            [
                'client' => $clients[3],
                'rating' => 5,
                'comment' => "Je l'utilise en production depuis 3 mois, aucune erreur et très stable. Les mises à jour régulières montrent que le vendeur est engagé. La performance est au rendez-vous même avec beaucoup de données. Le système de routing est bien pensé. Très satisfait de mon achat !",
                'days_ago' => 90
            ],
            [
                'client' => $clients[4],
                'rating' => 5,
                'comment' => "Template de qualité professionnelle. Les composants sont modulaires et faciles à adapter. La documentation est claire avec de nombreux exemples. J'ai particulièrement apprécié les tableaux de données avec tri et filtres. Le support technique est réactif et compétent.",
                'days_ago' => 14
            ],
            [
                'client' => $clients[5],
                'rating' => 4,
                'comment' => "Très bon template avec une belle interface. Le code est propre et bien organisé. Quelques petits ajustements nécessaires pour mon cas d'usage spécifique, mais rien de bloquant. Les graphiques Chart.js sont bien intégrés. Je recommande pour un projet d'entreprise.",
                'days_ago' => 21
            ],
            [
                'client' => $clients[6],
                'rating' => 5,
                'comment' => "Meilleur template React que j'ai acheté ! L'architecture est solide, le design est moderne et responsive. Les formulaires avec validation sont très pratiques. Le système d'authentification est complet. Les assets Figma inclus sont un gros plus. Bravo au développeur !",
                'days_ago' => 28
            ],
            [
                'client' => $clients[7],
                'rating' => 5,
                'comment' => "Excellent investissement pour notre startup. Nous avons économisé des semaines de développement. Le template est flexible et s'adapte parfaitement à nos besoins. La qualité du code facilite la maintenance. Le support est top, réponse en moins de 24h. À recommander sans hésitation !",
                'days_ago' => 35
            ],
            [
                'client' => $clients[8],
                'rating' => 4,
                'comment' => "Bon produit dans l'ensemble. Le design est élégant et professionnel. J'ai dû faire quelques modifications pour l'adapter à mon projet, mais la base est solide. La documentation pourrait être un peu plus détaillée sur certains points avancés. Sinon, très content de mon achat.",
                'days_ago' => 42
            ],
            [
                'client' => $clients[9],
                'rating' => 5,
                'comment' => "Template complet et bien pensé. Les composants sont réutilisables et personnalisables. Le système de thème avec dark mode fonctionne parfaitement. Les performances sont excellentes grâce à Vite. Le vendeur est à l'écoute et prend en compte les suggestions. Top qualité !",
                'days_ago' => 49
            ],
        ];

        foreach ($reviews as $reviewData) {
            $createdAt = now()->subDays($reviewData['days_ago']);
            
            Review::create([
                'product_id' => $product->id,
                'user_id' => $reviewData['client']->id,
                'rating' => $reviewData['rating'],
                'comment' => $reviewData['comment'],
                'is_approved' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // 8. Créer quelques produits similaires
        $this->command->info('📦 Création des produits similaires...');
        
        $relatedProducts = [
            [
                'name' => 'UI Kit Dashboard Pro',
                'price' => 39.00,
                'compare_at_price' => 59.00,
                'description' => 'Kit complet de composants UI pour dashboards modernes',
            ],
            [
                'name' => 'Template E-commerce React',
                'price' => 69.00,
                'compare_at_price' => 99.00,
                'description' => 'Template complet pour créer une boutique en ligne',
            ],
            [
                'name' => 'Admin Panel Template',
                'price' => 45.00,
                'compare_at_price' => 75.00,
                'description' => 'Template d\'administration avec gestion complète',
            ],
        ];

        foreach ($relatedProducts as $index => $relatedData) {
            $relatedProduct = Product::create([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => $relatedData['name'],
                'slug' => Str::slug($relatedData['name']) . '-' . time() . '-' . $index,
                'description' => $relatedData['description'],
                'short_description' => $relatedData['description'],
                'price' => $relatedData['price'],
                'compare_at_price' => $relatedData['compare_at_price'],
                'stock_quantity' => rand(50, 200),
                'sku' => 'SKU-' . strtoupper(Str::random(8)),
                'is_active' => true,
            ]);

            // Ajouter une image
            ProductImage::create([
                'product_id' => $relatedProduct->id,
                'image_path' => 'https://via.placeholder.com/400x400?text=' . urlencode($relatedData['name']),
                'is_primary' => true,
                'order' => 0,
            ]);

            // Ajouter quelques avis
            for ($r = 0; $r < rand(3, 8); $r++) {
                Review::create([
                    'product_id' => $relatedProduct->id,
                    'user_id' => $clients[array_rand($clients)]->id,
                    'rating' => rand(4, 5),
                    'comment' => 'Excellent produit, je recommande !',
                    'is_approved' => true,
                ]);
            }
        }

        $this->command->info('✅ Données de la page de détails créées avec succès!');
        $this->command->info('👤 Vendeur vérifié : ' . $seller->store_name);
        $this->command->info('📦 Produit principal : ' . $product->name . ' (ID: ' . $product->id . ')');
        $this->command->info('🖼️  ' . count($images) . ' images ajoutées');
        $this->command->info('⭐ ' . count($reviews) . ' avis créés');
        $this->command->info('📦 ' . count($relatedProducts) . ' produits similaires créés');
        $this->command->info('');
        $this->command->info('🔗 URL de test : http://localhost:5173/products/' . $product->id);
    }
}
