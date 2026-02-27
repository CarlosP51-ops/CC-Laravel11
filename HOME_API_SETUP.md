# 🏠 Configuration de l'API Page d'Accueil

## 📋 Prérequis

- Base de données créée et migrations exécutées
- Serveur Laravel démarré (`php artisan serve`)

## 🚀 Installation Rapide

### 1. Peupler la base de données avec des données de test

```bash
cd digital-marketplace-backend

# Exécuter le seeder
php artisan db:seed --class=HomePageSeeder
```

Cela va créer :
- ✅ 5 vendeurs avec leurs boutiques
- ✅ 8 catégories (Électronique, Mode, Maison, etc.)
- ✅ 20 produits avec images et reviews
- ✅ Reviews aléatoires pour chaque produit

### 2. Tester l'API

```bash
# Test de la page d'accueil complète
curl http://localhost:8000/api/home | jq '.'

# Test des catégories
curl http://localhost:8000/api/home/categories | jq '.'

# Test des catégories populaires
curl http://localhost:8000/api/categories/popular | jq '.'

# Test des produits tendance
curl http://localhost:8000/api/products/trending | jq '.'
```

## 📊 Structure des Données Retournées

### GET /api/home

```json
{
  "success": true,
  "data": {
    "hero_stats": {
      "total_products": 20,
      "total_sellers": 5,
      "satisfaction_rate": 98,
      "total_users": 0
    },
    "categories": [
      {
        "id": 1,
        "name": "Électronique",
        "slug": "electronique",
        "description": "Produits électroniques et high-tech",
        "products_count": 5
      }
    ],
    "featured_products": [
      {
        "id": 1,
        "name": "Smartphone Premium",
        "slug": "smartphone-premium-0",
        "description": "Un excellent Smartphone Premium de qualité",
        "price": 299.99,
        "compare_at_price": 399.99,
        "rating": 4.5,
        "reviews_count": 15,
        "sales_count": 0,
        "stock_quantity": 50,
        "image": "https://via.placeholder.com/400x400?text=Smartphone+Premium",
        "category": {
          "id": 1,
          "name": "Électronique",
          "slug": "electronique"
        },
        "seller": {
          "id": 1,
          "store_name": "Boutique 1",
          "slug": "boutique-1",
          "is_verified": true,
          "logo": null
        }
      }
    ],
    "new_products": [...],
    "best_sellers": [...],
    "platform_stats": {
      "monthly_transactions": 0,
      "average_rating": 4.2,
      "support_hours": "24/7",
      "countries_served": 1,
      "total_sales": 0,
      "verified_sellers": 5
    }
  }
}
```

## 🔧 Personnalisation

### Ajouter plus de produits

Modifiez le seeder `HomePageSeeder.php` et ajoutez plus de noms dans le tableau `$productNames`.

### Modifier les catégories

Éditez le tableau `$categories` dans le seeder.

### Réinitialiser les données

```bash
# Supprimer toutes les données et recréer
php artisan migrate:fresh --seed

# Ou juste réexécuter le seeder
php artisan db:seed --class=HomePageSeeder
```

## 🐛 Résolution de Problèmes

### Erreur: "Class 'Database\Seeders\Review' not found"

Vérifiez que tous les modèles sont bien importés dans le seeder.

### Erreur: "SQLSTATE[23000]: Integrity constraint violation"

Assurez-vous que les migrations sont à jour :
```bash
php artisan migrate:fresh
php artisan db:seed --class=HomePageSeeder
```

### Pas de données retournées

Vérifiez que les produits sont actifs :
```sql
SELECT * FROM products WHERE is_active = 1;
```

## 📝 Notes

- Les images utilisent des placeholders (via.placeholder.com)
- Les reviews sont générées aléatoirement
- Les prix sont entre 20€ et 500€
- Tous les vendeurs sont vérifiés par défaut
- Tous les produits sont actifs

## 🎯 Prochaines Étapes

1. ✅ Tester l'API avec le frontend
2. ⬜ Remplacer les images placeholder par de vraies images
3. ⬜ Ajouter plus de variété dans les produits
4. ⬜ Implémenter la recherche de produits
5. ⬜ Ajouter les filtres par catégorie
