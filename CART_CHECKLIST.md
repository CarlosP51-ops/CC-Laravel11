# Checklist - API Panier CS-Trade

## ✅ Backend Laravel

### Fichiers créés

- [x] `app/Http/Controllers/Clients/CartController.php`
- [x] `app/Http/Requests/AddToCartRequest.php`
- [x] `app/Http/Requests/UpdateCartItemRequest.php`
- [x] `app/Http/Requests/ApplyCouponRequest.php`
- [x] `app/Http/Resources/CartResource.php`
- [x] `app/Http/Resources/CartItemResource.php`
- [x] `app/Http/Resources/ProductRecommendationResource.php`
- [x] `database/migrations/2026_02_26_000000_add_coupon_fields_to_carts_table.php`
- [x] `database/seeders/CartTestSeeder.php`

### Fichiers modifiés

- [x] `app/Models/Cart.php` (ajout champs fillable)
- [x] `app/Http/Controllers/Clients/HomeController.php` (ajout endpoints)
- [x] `routes/api.php` (ajout routes)

### Routes API

- [x] `GET /api/cart` - Récupérer le panier
- [x] `POST /api/cart/items` - Ajouter un produit
- [x] `PUT /api/cart/items/{id}` - Modifier la quantité
- [x] `DELETE /api/cart/items/{id}` - Supprimer un article
- [x] `DELETE /api/cart` - Vider le panier
- [x] `POST /api/cart/apply-coupon` - Appliquer un coupon
- [x] `GET /api/categories/popular` - Catégories populaires
- [x] `GET /api/products/trending` - Produits tendance

### Fonctionnalités

- [x] Authentification Sanctum
- [x] Validation des données
- [x] Vérification du stock
- [x] Calculs automatiques (subtotal, tax, total)
- [x] Gestion des variantes
- [x] Recommandations personnalisées
- [x] Gestion des erreurs
- [x] Resources pour formater les réponses

---

## 📝 Documentation

- [x] `CART_API.md` - Documentation complète des endpoints
- [x] `CART_API_EXAMPLES.md` - Exemples d'utilisation
- [x] `CART_SETUP.md` - Guide d'installation
- [x] `CART_IMPLEMENTATION_SUMMARY.md` - Résumé de l'implémentation
- [x] `CART_FRONTEND_INTEGRATION.md` - Guide d'intégration React
- [x] `CART_CHECKLIST.md` - Cette checklist

---

## 🚀 Déploiement

### Étapes à suivre

1. **Exécuter les migrations**
   ```bash
   php artisan migrate
   ```

2. **Générer des données de test (optionnel)**
   ```bash
   php artisan db:seed --class=CartTestSeeder
   ```

3. **Créer un utilisateur de test**
   ```bash
   php artisan tinker
   ```
   ```php
   \App\Models\User::create([
       'name' => 'Test Client',
       'email' => 'client@test.com',
       'password' => bcrypt('password'),
       'role' => 'client',
       'email_verified_at' => now(),
   ]);
   ```

4. **Vérifier les routes**
   ```bash
   php artisan route:list --path=cart
   ```

5. **Tester les endpoints**
   - Utiliser Postman ou cURL
   - Voir `CART_API_EXAMPLES.md`

---

## 🧪 Tests

### Tests manuels à effectuer

- [ ] Se connecter et obtenir un token
- [ ] Récupérer le panier vide
- [ ] Ajouter un produit au panier
- [ ] Ajouter un produit avec variante
- [ ] Modifier la quantité d'un article
- [ ] Tester la limite de stock
- [ ] Appliquer un code promo
- [ ] Supprimer un article
- [ ] Vider le panier
- [ ] Vérifier les recommandations
- [ ] Tester les endpoints publics (catégories, produits tendance)

### Scénarios d'erreur à tester

- [ ] Ajouter un produit inexistant
- [ ] Quantité supérieure au stock
- [ ] Quantité négative ou zéro
- [ ] Token invalide ou expiré
- [ ] Supprimer un article inexistant
- [ ] Code promo invalide

---

## 🎨 Frontend

### À implémenter

- [ ] Service API (axios)
- [ ] Types TypeScript
- [ ] Context React (CartContext)
- [ ] Composant Cart
- [ ] Composant CartItem
- [ ] Composant CartSummary
- [ ] Composant EmptyCart
- [ ] Gestion des erreurs
- [ ] Notifications (toast)
- [ ] Loading states
- [ ] Optimistic updates

### Dépendances à installer

```bash
npm install axios react-hot-toast
```

---

## 🔧 Configuration

### Variables d'environnement Laravel

```env
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DRIVER=cookie
```

### Variables d'environnement React

```env
VITE_API_URL=http://localhost:8000/api
```

---

## 📊 Performance

### Optimisations appliquées

- [x] Eager loading des relations
- [x] Index sur user_id et session_id
- [x] Calculs en mémoire
- [x] Limite de recommandations (4)

### À considérer

- [ ] Cache Redis pour catégories populaires
- [ ] Queue pour emails de panier abandonné
- [ ] CDN pour les images
- [ ] Compression des réponses JSON

---

## 🔒 Sécurité

### Mesures en place

- [x] Authentification Sanctum
- [x] Validation stricte des entrées
- [x] Recalcul côté serveur
- [x] Vérification du stock
- [x] Protection CSRF
- [x] Rate limiting (Laravel par défaut)

### À ajouter

- [ ] Logs des actions sensibles
- [ ] Détection de fraude
- [ ] Limites de quantité par utilisateur
- [ ] Captcha sur checkout

---

## 📈 Monitoring

### Métriques à surveiller

- [ ] Temps de réponse des endpoints
- [ ] Taux d'erreur
- [ ] Paniers abandonnés
- [ ] Taux de conversion
- [ ] Utilisation des codes promo

### Outils recommandés

- Laravel Telescope (développement)
- Laravel Horizon (queues)
- Sentry (erreurs)
- Google Analytics (comportement)

---

## 🐛 Dépannage

### Problèmes courants

**Erreur 401 Unauthorized**
- Vérifier le token dans le header
- Vérifier que l'utilisateur est connecté
- Vérifier la configuration Sanctum

**Erreur 404 Not Found**
- Vérifier que les routes sont enregistrées
- Exécuter `php artisan route:clear`

**Stock insuffisant**
- Vérifier les quantités dans la base de données
- Vérifier la logique de vérification du stock

**Calculs incorrects**
- Vérifier le taux de TVA dans CartResource
- Vérifier la logique des coupons

---

## 📚 Ressources

### Documentation

- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [Laravel Resources](https://laravel.com/docs/eloquent-resources)
- [Laravel Validation](https://laravel.com/docs/validation)

### Prochaines étapes

1. Implémenter la table Coupons
2. Créer l'API Wishlist
3. Créer l'API Checkout
4. Intégrer les paiements (Stripe/PayPal)
5. Ajouter les tests unitaires

---

## ✅ Validation finale

Avant de passer en production :

- [ ] Tous les tests manuels passent
- [ ] Documentation à jour
- [ ] Variables d'environnement configurées
- [ ] Migrations exécutées
- [ ] Logs vérifiés
- [ ] Performance testée
- [ ] Sécurité validée
- [ ] Frontend intégré
- [ ] Tests utilisateurs effectués

---

**Status actuel : ✅ API Backend complète et fonctionnelle**

**Prochaine étape : Intégration Frontend React**
