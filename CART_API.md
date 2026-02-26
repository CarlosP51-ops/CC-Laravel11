# API Panier - CS-Trade

Documentation complète de l'API pour la gestion du panier d'achat.

## Authentification

Toutes les routes du panier nécessitent une authentification via Sanctum.
Ajouter le header : `Authorization: Bearer {token}`

---

## Endpoints

### 1. Récupérer le panier

**GET** `/api/cart`

Récupère le panier complet de l'utilisateur avec tous les articles, le résumé financier, les recommandations et les statistiques.

**Réponse (200 OK) :**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "items": [
      {
        "id": 1,
        "product_id": 5,
        "product_name": "Laptop Gaming Pro",
        "product_slug": "laptop-gaming-pro",
        "category": "Électronique",
        "seller": {
          "id": 2,
          "name": "TechStore"
        },
        "image": "http://localhost/storage/products/laptop.jpg",
        "variant": {
          "id": 3,
          "name": "16GB RAM - 512GB SSD",
          "sku": "LAP-16-512"
        },
        "price": 1299.99,
        "compare_at_price": 1499.99,
        "quantity": 1,
        "subtotal": 1299.99,
        "savings": 200.00,
        "stock_available": 15,
        "rating": {
          "average": 4.5,
          "count": 128
        }
      }
    ],
    "items_count": 1,
    "summary": {
      "subtotal": 1299.99,
      "discount": 0,
      "tax": 259.99,
      "tax_rate": 0.2,
      "total": 1559.98,
      "coupon_code": null
    },
    "recommendations": [
      {
        "id": 8,
        "name": "Souris Gaming RGB",
        "slug": "souris-gaming-rgb",
        "price": 49.99,
        "compare_at_price": 69.99,
        "image": "http://localhost/storage/products/mouse.jpg",
        "category": "Électronique",
        "rating": {
          "average": 4.7,
          "count": 89
        }
      }
    ],
    "stats": {
      "satisfaction_rate": 98,
      "support_response_time": "2h",
      "active_clients": "50k+"
    }
  }
}
```

---

### 2. Ajouter un produit au panier

**POST** `/api/cart/items`

Ajoute un produit au panier ou augmente la quantité si déjà présent.

**Body :**
```json
{
  "product_id": 5,
  "product_variant_id": 3,  // Optionnel
  "quantity": 1
}
```

**Réponse (201 Created) :**
```json
{
  "success": true,
  "message": "Produit ajouté au panier.",
  "data": {
    // Structure identique à GET /api/cart
  }
}
```

**Erreurs possibles :**
- `400` : Stock insuffisant
- `404` : Produit ou variante introuvable

---

### 3. Mettre à jour la quantité

**PUT** `/api/cart/items/{itemId}`

Modifie la quantité d'un article dans le panier.

**Body :**
```json
{
  "quantity": 3
}
```

**Réponse (200 OK) :**
```json
{
  "success": true,
  "message": "Quantité mise à jour.",
  "data": {
    // Structure identique à GET /api/cart
  }
}
```

**Erreurs possibles :**
- `400` : Stock insuffisant
- `404` : Article introuvable dans le panier

---

### 4. Supprimer un article

**DELETE** `/api/cart/items/{itemId}`

Retire un article du panier.

**Réponse (200 OK) :**
```json
{
  "success": true,
  "message": "Article retiré du panier.",
  "data": {
    // Structure identique à GET /api/cart
  }
}
```

---

### 5. Vider le panier

**DELETE** `/api/cart`

Supprime tous les articles du panier.

**Réponse (200 OK) :**
```json
{
  "success": true,
  "message": "Panier vidé.",
  "data": {
    "id": 1,
    "items": [],
    "items_count": 0,
    "summary": {
      "subtotal": 0,
      "discount": 0,
      "tax": 0,
      "tax_rate": 0.2,
      "total": 0,
      "coupon_code": null
    }
  }
}
```

---

### 6. Appliquer un code promo

**POST** `/api/cart/apply-coupon`

Applique un code promo au panier.

**Body :**
```json
{
  "code": "PROMO10"
}
```

**Réponse (200 OK) :**
```json
{
  "success": true,
  "message": "Code promo appliqué.",
  "data": {
    // Structure identique à GET /api/cart
    // Le champ summary.discount sera mis à jour
  }
}
```

**Note :** La logique de validation des coupons est à implémenter selon vos besoins.

---

## Endpoints publics (EmptyCart)

### 7. Catégories populaires

**GET** `/api/categories/popular`

Récupère les 4 catégories les plus populaires pour la page panier vide.

**Réponse (200 OK) :**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Électronique",
      "slug": "electronique",
      "products_count": 245,
      "icon": "Laptop"
    }
  ]
}
```

---

### 8. Produits tendance

**GET** `/api/products/trending`

Récupère les 3 produits les plus vendus pour la page panier vide.

**Réponse (200 OK) :**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "Laptop Gaming Pro",
      "slug": "laptop-gaming-pro",
      "description": "Laptop haute performance...",
      "price": 1299.99,
      "compare_at_price": 1499.99,
      "rating": 4.5,
      "reviews_count": 128,
      "sales_count": 456,
      "stock_quantity": 15,
      "image": "products/laptop.jpg",
      "category": {
        "id": 1,
        "name": "Électronique",
        "slug": "electronique"
      },
      "seller": {
        "id": 2,
        "store_name": "TechStore",
        "slug": "techstore",
        "is_verified": true,
        "logo": "sellers/logo.jpg"
      }
    }
  ]
}
```

---

## Logique métier

### Calculs automatiques

Tous les montants sont recalculés côté serveur pour garantir la sécurité :
- **Subtotal** : Somme des (prix × quantité) de tous les articles
- **Discount** : Réduction appliquée via code promo
- **Tax** : (Subtotal - Discount) × 20%
- **Total** : Subtotal - Discount + Tax

### Gestion du stock

À chaque ajout ou modification de quantité, le système vérifie la disponibilité en stock.

### Recommandations

- Si le panier est vide : produits tendance (les plus vendus)
- Si le panier contient des articles : produits de la même catégorie (max 4)

### Panier persistant

Le panier est lié à l'utilisateur connecté et persiste entre les sessions.

---

## Migration requise

Exécuter la migration pour ajouter les champs coupon :

```bash
php artisan migrate
```

---

## Tests suggérés

1. Ajouter un produit au panier
2. Modifier la quantité (vérifier limite de stock)
3. Appliquer un code promo
4. Vider le panier
5. Vérifier les recommandations
6. Tester avec panier vide (endpoints publics)
