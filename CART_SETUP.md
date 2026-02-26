# Configuration et Test de l'API Panier

## Installation

### 1. Exécuter les migrations

```bash
php artisan migrate
```

Cela va créer les colonnes `coupon_code` et `discount` dans la table `carts`.

### 2. Générer des données de test (optionnel)

```bash
php artisan db:seed --class=CartTestSeeder
```

Cela va créer :
- 1 vendeur (TechStore)
- 1 catégorie (Électronique)
- 3 produits avec images et variantes
- Stock disponible pour tester

---

## Structure de l'API

### Fichiers créés

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Clients/
│   │       ├── CartController.php          # Contrôleur principal
│   │       └── HomeController.php          # Endpoints EmptyCart ajoutés
│   ├── Requests/
│   │   ├── AddToCartRequest.php            # Validation ajout
│   │   ├── UpdateCartItemRequest.php       # Validation mise à jour
│   │   └── ApplyCouponRequest.php          # Validation coupon
│   └── Resources/
│       ├── CartResource.php                # Format réponse panier
│       ├── CartItemResource.php            # Format article
│       └── ProductRecommendationResource.php
├── Models/
│   └── Cart.php                            # Modèle mis à jour
database/
├── migrations/
│   └── 2026_02_26_000000_add_coupon_fields_to_carts_table.php
└── seeders/
    └── CartTestSeeder.php
routes/
└── api.php                                 # Routes ajoutées
```

---

## Routes disponibles

### Routes protégées (auth:sanctum)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/cart` | Récupérer le panier |
| POST | `/api/cart/items` | Ajouter un produit |
| PUT | `/api/cart/items/{id}` | Modifier la quantité |
| DELETE | `/api/cart/items/{id}` | Supprimer un article |
| DELETE | `/api/cart` | Vider le panier |
| POST | `/api/cart/apply-coupon` | Appliquer un code promo |

### Routes publiques

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/categories/popular` | Catégories populaires (EmptyCart) |
| GET | `/api/products/trending` | Produits tendance (EmptyCart) |

---

## Test rapide

### 1. Créer un utilisateur et se connecter

```bash
# Créer un utilisateur client
php artisan tinker
```

```php
$user = \App\Models\User::create([
    'name' => 'Test Client',
    'email' => 'client@test.com',
    'password' => bcrypt('password'),
    'role' => 'client',
    'email_verified_at' => now(),
]);
```

### 2. Se connecter via l'API

```bash
POST /api/login
```

```json
{
  "email": "client@test.com",
  "password": "password"
}
```

Récupérer le `token` de la réponse.

### 3. Tester les endpoints

Voir `CART_API_EXAMPLES.md` pour des exemples complets.

---

## Fonctionnalités implémentées

✅ CRUD complet du panier
✅ Gestion des variantes de produits
✅ Vérification automatique du stock
✅ Calculs automatiques (subtotal, taxes, total)
✅ Application de codes promo
✅ Recommandations personnalisées
✅ Endpoints pour page panier vide
✅ Validation des données
✅ Resources pour formater les réponses
✅ Statistiques de confiance

---

## Fonctionnalités à implémenter

🔲 Table et logique complète des coupons
🔲 Synchronisation panier localStorage → serveur
🔲 Gestion des favoris (Wishlist)
🔲 Historique des paniers abandonnés
🔲 Notifications de baisse de prix
🔲 Limites de quantité par produit
🔲 Gestion des produits en rupture de stock

---

## Intégration Frontend

### Structure de données attendue

Le frontend React doit envoyer :

**Ajouter au panier :**
```typescript
{
  product_id: number;
  product_variant_id?: number;
  quantity: number;
}
```

**Modifier la quantité :**
```typescript
{
  quantity: number;
}
```

**Appliquer un coupon :**
```typescript
{
  code: string;
}
```

### Gestion des états

```typescript
// État du panier
interface Cart {
  id: number;
  items: CartItem[];
  items_count: number;
  summary: {
    subtotal: number;
    discount: number;
    tax: number;
    tax_rate: number;
    total: number;
    coupon_code: string | null;
  };
  recommendations: Product[];
  stats: {
    satisfaction_rate: number;
    support_response_time: string;
    active_clients: string;
  };
}
```

---

## Dépannage

### Erreur 401 Unauthorized

Vérifier que le token est bien passé dans le header :
```
Authorization: Bearer {token}
```

### Erreur 404 sur les routes

Vérifier que les routes sont bien enregistrées :
```bash
php artisan route:list --path=cart
```

### Stock insuffisant

Vérifier les quantités disponibles dans la table `products` ou `product_variants`.

### Calculs incorrects

Les calculs sont faits côté serveur. Vérifier :
- Le prix du produit/variante
- Le taux de TVA (20% par défaut dans `CartResource`)
- La logique de réduction des coupons

---

## Performance

### Optimisations appliquées

- Eager loading des relations (`with()`)
- Index sur `user_id` et `session_id` dans la table `carts`
- Calculs en mémoire (pas de requêtes supplémentaires)

### Recommandations

- Mettre en cache les catégories populaires (Redis)
- Limiter les recommandations à 4 produits
- Utiliser des queues pour les emails de panier abandonné

---

## Sécurité

✅ Validation stricte des entrées
✅ Recalcul côté serveur des montants
✅ Vérification du stock avant ajout
✅ Protection CSRF (Sanctum)
✅ Rate limiting sur les routes API

---

## Support

Pour toute question ou problème :
1. Consulter `CART_API.md` pour la documentation complète
2. Voir `CART_API_EXAMPLES.md` pour des exemples d'utilisation
3. Vérifier les logs Laravel : `storage/logs/laravel.log`
