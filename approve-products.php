<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Approbation des produits pour les tests...\n\n";

// Approuver tous les produits actifs
$updated = \App\Models\Product::where('is_active', true)
    ->update(['status' => 'approved']);

echo "✅ $updated produits ont été approuvés!\n\n";

// Afficher les statistiques
$total = \App\Models\Product::count();
$approved = \App\Models\Product::where('status', 'approved')->count();
$pending = \App\Models\Product::where('status', 'pending')->count();
$rejected = \App\Models\Product::where('status', 'rejected')->count();

echo "📊 Statistiques des produits:\n";
echo "   - Total: $total\n";
echo "   - Approuvés: $approved\n";
echo "   - En attente: $pending\n";
echo "   - Rejetés: $rejected\n\n";

echo "✅ Terminé! La page d'accueil devrait maintenant afficher des produits.\n";
