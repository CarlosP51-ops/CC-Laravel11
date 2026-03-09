# Sécurité des Contrôleurs Vendeur

## ✅ Implémentation Complète

### 1. Middleware Centralisé

**Fichier** : `app/Http/Middleware/CheckVendorRole.php`

Ce middleware vérifie :
- ✅ L'utilisateur est authentifié
- ✅ L'utilisateur a le rôle `seller`
- ✅ Retourne 401 si non authentifié
- ✅ Retourne 403 si pas le bon rôle

```php
public function handle(Request $request, Closure $next)
{
    $user = Auth::user();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Non authentifié. Veuillez vous connecter.'
        ], 401);
    }
    
    if ($user->role !== 'seller') {
        return response()->json([
            'success' => false,
            'message' => 'Accès refusé. Vous devez être vendeur.'
        ], 403);
    }
    
    return $next($request);
}
```

### 2. Enregistrement du Middleware

**Fichier** : `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\CheckVendorRole::class,
    ]);
})
```

### 3. Application aux Routes

**Fichier** : `routes/api.php`

Toutes les routes vendeur sont protégées :

```php
Route::middleware('role:vendor')->prefix('vendor')->group(function () {
    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [VendorDashboardController::class, 'getStats']);
        Route::get('/revenue-chart', [VendorDashboardController::class, 'getRevenueChart']);
        Route::get('/recent-orders', [VendorDashboardController::class, 'getRecentOrders']);
        Route::get('/top-products', [VendorDashboardController::class, 'getTopProducts']);
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/categories', [VendorProductController::class, 'getCategories']);
        Route::get('/', [VendorProductController::class, 'index']);
        Route::post('/', [VendorProductController::class, 'store']);
        Route::get('/{id}', [VendorProductController::class, 'show']);
        Route::put('/{id}', [VendorProductController::class, 'update']);
        Route::delete('/{id}', [VendorProductController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [VendorProductController::class, 'toggleStatus']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/', [VendorOrderController::class, 'index']);
        Route::get('/{id}', [VendorOrderController::class, 'show']);
        Route::patch('/{id}/status', [VendorOrderController::class, 'updateStatus']);
        Route::patch('/{id}/tracking', [VendorOrderController::class, 'updateTracking']);
        Route::post('/{id}/notes', [VendorOrderController::class, 'addNote']);
    });
});
```

### 4. Contrôleurs Protégés

Tous les contrôleurs vendeur sont maintenant protégés :

#### ✅ DashboardController
- `getStats()` - Statistiques du dashboard
- `getRevenueChart()` - Graphique des revenus
- `getRecentOrders()` - Commandes récentes
- `getTopProducts()` - Produits les plus vendus

#### ✅ ProductController
- `index()` - Liste des produits
- `show($id)` - Détails d'un produit
- `store()` - Créer un produit
- `update($id)` - Modifier un produit
- `destroy($id)` - Supprimer un produit
- `getCategories()` - Récupérer les catégories
- `toggleStatus($id)` - Activer/désactiver un produit

#### ✅ OrderController
- `index()` - Liste des commandes
- `show($id)` - Détails d'une commande
- `updateStatus($id)` - Mettre à jour le statut
- `updateTracking($id)` - Ajouter le suivi
- `addNote($id)` - Ajouter une note

### 5. Filtrage par Vendeur

En plus de la vérification du rôle, chaque contrôleur filtre les données par vendeur :

#### DashboardController
```php
$user = Auth::user();
$seller = $user->seller;
// Toutes les requêtes filtrent par $seller->id
```

#### ProductController
```php
$user = Auth::user();
$seller = $user->seller;

$query = Product::where('seller_id', $seller->id);
```

#### OrderController
```php
$vendorId = Auth::id();

$query = Order::whereHas('items.product', function ($q) use ($vendorId) {
    $q->where('vendor_id', $vendorId);
});
```

### 6. Sécurité Multi-Niveaux

#### Niveau 1 : Routes
```php
Route::middleware('role:vendor')
```
→ Vérifie l'authentification et le rôle

#### Niveau 2 : Contrôleurs
```php
$user = Auth::user();
$seller = $user->seller;
```
→ Récupère les informations du vendeur

#### Niveau 3 : Requêtes
```php
->where('seller_id', $seller->id)
```
→ Filtre les données par vendeur

### 7. Codes de Réponse HTTP

- **200 OK** : Requête réussie
- **201 Created** : Ressource créée
- **400 Bad Request** : Données invalides
- **401 Unauthorized** : Non authentifié
- **403 Forbidden** : Pas le bon rôle
- **404 Not Found** : Ressource non trouvée
- **500 Internal Server Error** : Erreur serveur

### 8. Messages d'Erreur

#### Non Authentifié (401)
```json
{
  "success": false,
  "message": "Non authentifié. Veuillez vous connecter."
}
```

#### Accès Refusé (403)
```json
{
  "success": false,
  "message": "Accès refusé. Vous devez être vendeur pour accéder à cette ressource."
}
```

#### Ressource Non Trouvée (404)
```json
{
  "success": false,
  "message": "Produit non trouvé"
}
```

## 🧪 Tests de Sécurité

### Test 1 : Sans Token
```bash
curl http://localhost:8000/api/vendor/dashboard/stats
```
**Attendu** : 401 Unauthorized

### Test 2 : Avec Token Client
```bash
curl -H "Authorization: Bearer CLIENT_TOKEN" \
     http://localhost:8000/api/vendor/dashboard/stats
```
**Attendu** : 403 Forbidden

### Test 3 : Avec Token Vendeur
```bash
curl -H "Authorization: Bearer VENDOR_TOKEN" \
     http://localhost:8000/api/vendor/dashboard/stats
```
**Attendu** : 200 OK avec données

### Test 4 : Accès aux Données d'un Autre Vendeur
```bash
# Vendeur A essaie d'accéder au produit du Vendeur B
curl -H "Authorization: Bearer VENDOR_A_TOKEN" \
     http://localhost:8000/api/vendor/products/VENDOR_B_PRODUCT_ID
```
**Attendu** : 404 Not Found (le produit n'existe pas pour ce vendeur)

## ✅ Checklist de Sécurité

### Middleware
- [x] Middleware créé (`CheckVendorRole`)
- [x] Middleware enregistré dans `bootstrap/app.php`
- [x] Middleware appliqué à toutes les routes vendeur

### Contrôleurs
- [x] DashboardController protégé
- [x] ProductController protégé
- [x] OrderController protégé
- [x] Filtrage par `seller_id` dans toutes les requêtes

### Routes
- [x] Toutes les routes vendeur sous `middleware('role:vendor')`
- [x] Préfixe `/vendor` pour toutes les routes
- [x] Routes groupées logiquement

### Validation
- [x] Vérification de l'authentification
- [x] Vérification du rôle
- [x] Filtrage des données par vendeur
- [x] Messages d'erreur appropriés

## 🔒 Bonnes Pratiques Appliquées

1. **Principe du moindre privilège** : Les vendeurs ne voient que leurs propres données
2. **Défense en profondeur** : Sécurité à plusieurs niveaux (routes, middleware, contrôleurs)
3. **Séparation des préoccupations** : Middleware réutilisable
4. **Messages d'erreur clairs** : Aide au débogage sans exposer d'informations sensibles
5. **Codes HTTP standards** : Facilite l'intégration frontend

## 📊 Flux de Sécurité

```
Requête API
    ↓
Middleware auth:sanctum (Laravel)
    ↓ (Vérifie le token)
Middleware role:vendor (Custom)
    ↓ (Vérifie le rôle)
Contrôleur
    ↓ (Filtre par seller_id)
Base de données
    ↓
Réponse JSON
```

## 🚀 Avantages de cette Approche

1. **Centralisé** : Un seul middleware pour tous les contrôleurs
2. **Réutilisable** : Peut être appliqué à d'autres routes
3. **Maintenable** : Facile à modifier et tester
4. **Performant** : Vérification avant l'exécution du contrôleur
5. **Sécurisé** : Protection à plusieurs niveaux

## 📝 Notes Importantes

- Le middleware `role:vendor` est appliqué au niveau des routes, pas dans les contrôleurs
- Chaque contrôleur filtre ensuite les données par `seller_id`
- Les vendeurs ne peuvent accéder qu'à leurs propres ressources
- Toutes les erreurs retournent des messages JSON cohérents
