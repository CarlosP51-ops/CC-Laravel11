<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Vérification de la page d'accueil...\n\n";

// 1. Vérifier les produits
echo "1. Produits:\n";
$productsCount = \App\Models\Product::count();
$activeProducts = \App\Models\Product::where('is_active', true)->count();
$approvedProducts = \App\Models\Product::where('status', 'approved')->count();
echo "   - Total: $productsCount\n";
echo "   - Actifs: $activeProducts\n";
echo "   - Approuvés: $approvedProducts\n\n";

// 2. Vérifier les catégories
echo "2. Catégories:\n";
$categoriesCount = \App\Models\Category::count();
$activeCategories = \App\Models\Category::where('is_active', true)->count();
echo "   - Total: $categoriesCount\n";
echo "   - Actives: $activeCategories\n\n";

// 3. Vérifier les vendeurs
echo "3. Vendeurs:\n";
$sellersCount = \App\Models\Seller::count();
$activeSellers = \App\Models\Seller::where('is_active', true)->count();
echo "   - Total: $sellersCount\n";
echo "   - Actifs: $activeSellers\n\n";

// 4. Vérifier les utilisateurs
echo "4. Utilisateurs:\n";
$usersCount = \App\Models\User::count();
$clientsCount = \App\Models\User::where('role', 'client')->count();
$vendorsCount = \App\Models\User::where('role', 'vendor')->count();
echo "   - Total: $usersCount\n";
echo "   - Clients: $clientsCount\n";
echo "   - Vendeurs: $vendorsCount\n\n";

// 5. Tester la méthode index
echo "5. Test de la méthode HomeController::index():\n";
try {
    $controller = new \App\Http\Controllers\Clients\HomeController();
    $response = $controller->index();
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "   ✅ Succès!\n";
        echo "   - Hero stats: " . json_encode($data['data']['hero_stats']) . "\n";
        echo "   - Catégories: " . count($data['data']['categories']) . "\n";
        echo "   - Produits vedettes: " . count($data['data']['featured_products']) . "\n";
        echo "   - Nouveaux produits: " . count($data['data']['new_products']) . "\n";
        echo "   - Meilleures ventes: " . count($data['data']['best_sellers']) . "\n";
    } else {
        echo "   ❌ Échec: " . ($data['message'] ?? 'Erreur inconnue') . "\n";
        if (isset($data['error'])) {
            echo "   Erreur: " . $data['error'] . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n✅ Vérification terminée!\n";
