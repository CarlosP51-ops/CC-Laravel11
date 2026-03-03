# Guide d'utilisation du ProductDetailsSeeder

## 📋 Description

Ce seeder crée des données de test complètes pour la page de détails du produit, incluant :
- 1 vendeur vérifié professionnel
- 1 produit principal avec description détaillée
- 4 images du produit
- 10 avis clients détaillés (avec achats vérifiés)
- 3 produits similaires
- 15 clients de test

## 🚀 Utilisation

### Option 1 : Exécuter uniquement ce seeder

```bash
php artisan db:seed --class=ProductDetailsSeeder
```

### Option 2 : Ajouter au DatabaseSeeder principal

Ouvrez `database/seeders/DatabaseSeeder.php` et ajoutez :

```php
public function run(): void
{
    $this->call([
        ProductDetailsSeeder::class,
        // ... autres seeders
    ]);
}
```

Puis exécutez :

```bash
php artisan db:seed
```

## 📦 Données créées

### Vendeur
- **Email** : `vendor.premium@example.com`
- **Mot de passe** : `password123`
- **Nom de la boutique** : TechDesign Studio
- **Statut** : Vérifié ✓

### Produit principal
- **Nom** : Template Dashboard React Pro 2024
- **Prix** : 49€ (au lieu de 79€)
- **Réduction** : 38%
- **Stock** : 999 unités
- **Images** : 4 images (dashboard, analytics, mobile, code)
- **Avis** : 10 avis détaillés (moyenne ~4.8/5)
- **Description** : Complète avec caractéristiques, technologies, cas d'usage

### Clients
- **Emails** : `client1@example.com` à `client15@example.com`
- **Mot de passe** : `password123`
- **Achats vérifiés** : Les 8 premiers clients ont acheté le produit

### Produits similaires
1. UI Kit Dashboard Pro - 39€
2. Template E-commerce React - 69€
3. Admin Panel Template - 45€

## 🧪 Test de la page

Après avoir exécuté le seeder, le produit principal sera créé avec un ID.
L'URL de test sera affichée dans la console :

```
🔗 URL de test : http://localhost:5173/products/{ID}
```

### Vérifications à effectuer

1. **Chargement de la page**
   - [ ] La page se charge sans erreur
   - [ ] Les données du produit s'affichent correctement
   - [ ] Les images sont visibles

2. **Breadcrumb**
   - [ ] Navigation : Accueil → Produits → Templates & UI Kits → Produit
   - [ ] Les liens fonctionnent

3. **Informations produit**
   - [ ] Nom, prix, réduction affichés
   - [ ] Badge "Best Seller" visible (si sales > 100)
   - [ ] Badge "Premium" visible (si rating >= 4.5)
   - [ ] Vendeur vérifié avec badge ✓
   - [ ] Stock disponible affiché
   - [ ] Note moyenne et nombre d'avis corrects

4. **Galerie d'images**
   - [ ] 4 images affichées
   - [ ] Sélection de miniatures fonctionne
   - [ ] Image principale change au clic

5. **Description**
   - [ ] Description complète affichée
   - [ ] Formatage préservé (sauts de ligne)

6. **Détails techniques**
   - [ ] SKU affiché
   - [ ] Informations techniques présentes
   - [ ] Statistiques (ventes, note) correctes

7. **Avis clients**
   - [ ] 10 avis affichés
   - [ ] Distribution des notes (barres de progression)
   - [ ] Badge "Achat vérifié" sur 8 avis
   - [ ] Dates relatives ("Il y a X jours")
   - [ ] Filtrage par note fonctionne

8. **Produits similaires**
   - [ ] 3 produits affichés
   - [ ] Navigation vers un produit similaire fonctionne
   - [ ] Scroll en haut de page après clic

9. **Interactions**
   - [ ] Bouton "Ajouter au panier" (console.log)
   - [ ] Bouton "Acheter maintenant" (console.log)
   - [ ] Gestion de la quantité (+/-)
   - [ ] Wishlist (toggle cœur)
   - [ ] Partage (bouton présent)

## 🔄 Réinitialisation

Pour supprimer toutes les données et recommencer :

```bash
php artisan migrate:fresh
php artisan db:seed --class=ProductDetailsSeeder
```

⚠️ **Attention** : Cette commande supprime TOUTES les données de la base !

## 📊 Données générées

### Statistiques du produit principal
- **Ventes** : 8 (achats vérifiés)
- **Avis** : 10 avis
- **Note moyenne** : ~4.8/5
- **Distribution** :
  - 5 étoiles : 7 avis (70%)
  - 4 étoiles : 3 avis (30%)
  - 3 étoiles : 0 avis
  - 2 étoiles : 0 avis
  - 1 étoile : 0 avis

### Dates des avis
Les avis sont répartis sur les 3 derniers mois :
- 3 jours : 1 avis
- 5 jours : 1 avis
- 7 jours : 1 avis
- 14 jours : 1 avis
- 21 jours : 1 avis
- 28 jours : 1 avis
- 35 jours : 1 avis
- 42 jours : 1 avis
- 49 jours : 1 avis
- 90 jours : 1 avis

## 🐛 Dépannage

### Erreur : "Class ProductDetailsSeeder not found"
```bash
composer dump-autoload
php artisan db:seed --class=ProductDetailsSeeder
```

### Erreur : "Column not found"
Vérifiez que toutes les migrations sont à jour :
```bash
php artisan migrate:status
php artisan migrate
```

### Erreur : "Foreign key constraint fails"
Assurez-vous que les tables nécessaires existent :
- users
- sellers
- categories
- products
- product_images
- reviews
- orders
- order_items

## 💡 Personnalisation

Pour modifier les données générées, éditez le fichier :
`database/seeders/ProductDetailsSeeder.php`

Vous pouvez personnaliser :
- Le nombre de clients
- Le nombre d'avis
- Les notes des avis
- Les images (URLs)
- Les produits similaires
- Les prix et réductions

## 📝 Notes

- Les images utilisent des placeholders (via.placeholder.com)
- Les mots de passe sont tous `password123` (à changer en production)
- Les achats vérifiés sont créés automatiquement pour les 8 premiers clients
- Le seeder est idempotent : il utilise `firstOrCreate` pour éviter les doublons
