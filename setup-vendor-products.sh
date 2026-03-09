#!/bin/bash

echo "🚀 Configuration de la gestion des produits vendeur..."

# Exécuter les migrations
echo "📦 Exécution des migrations..."
php artisan migrate

# Exécuter le seeder des catégories
echo "🏷️ Création des catégories..."
php artisan db:seed --class=CategoriesSeeder

# Créer les liens symboliques pour le stockage
echo "🔗 Création des liens symboliques..."
php artisan storage:link

echo "✅ Configuration terminée !"
echo ""
echo "📋 Résumé des fonctionnalités ajoutées :"
echo "   - Gestion complète des produits vendeur"
echo "   - Validation admin obligatoire"
echo "   - Support des produits numériques"
echo "   - Upload d'images multiples"
echo "   - Catégories et sous-catégories"
echo "   - Filtres et recherche avancés"
echo ""
echo "🌐 Accédez à la gestion des produits : http://localhost:5173/vendor/products"