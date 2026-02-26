# Exemples d'utilisation de l'API Panier

## Configuration

Base URL : `http://localhost:8000/api`

Headers requis pour les routes protégées :
```
Authorization: Bearer {votre_token}
Content-Type: application/json
Accept: application/json
```

---

## Scénario complet : Parcours utilisateur

### 1. Connexion (obtenir le token)

```bash
POST /api/login
```

```json
{
  "email": "client@example.com",
  "password": "password"
}
```

Réponse → Récupérer le `token` pour les requêtes suivantes.

---

### 2. Consulter le panier (vide au départ)

```bash
GET /api/cart
Authorization: Bearer {token}
```

---

### 3. Ajouter un premier produit

```bash
POST /api/cart/items
Authorization: Bearer {token}
```

```json
{
  "product_id": 1,
  "quantity": 2
}
```

---

### 4. Ajouter un produit avec variante

```bash
POST /api/cart/items
Authorization: Bearer {token}
```

```json
{
  "product_id": 5,
  "product_variant_id": 3,
  "quantity": 1
}
```

---

### 5. Modifier la quantité d'un article

```bash
PUT /api/cart/items/1
Authorization: Bearer {token}
```

```json
{
  "quantity": 5
}
```

---

### 6. Appliquer un code promo

```bash
POST /api/cart/apply-coupon
Authorization: Bearer {token}
```

```json
{
  "code": "PROMO10"
}
```

---

### 7. Supprimer un article

```bash
DELETE /api/cart/items/2
Authorization: Bearer {token}
```

---

### 8. Vider complètement le panier

```bash
DELETE /api/cart
Authorization: Bearer {token}
```

---

## Routes publiques (sans authentification)

### Catégories populaires (pour EmptyCart)

```bash
GET /api/categories/popular
```

### Produits tendance (pour EmptyCart)

```bash
GET /api/products/trending
```

---

## Tests avec cURL

### Ajouter au panier

```bash
curl -X POST http://localhost:8000/api/cart/items \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "quantity": 2
  }'
```

### Récupérer le panier

```bash
curl -X GET http://localhost:8000/api/cart \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Appliquer un coupon

```bash
curl -X POST http://localhost:8000/api/cart/apply-coupon \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "PROMO10"
  }'
```

---

## Gestion des erreurs

### Stock insuffisant

```json
{
  "success": false,
  "message": "Stock insuffisant pour ce produit."
}
```

### Produit introuvable

```json
{
  "success": false,
  "message": "Le produit n'existe pas."
}
```

### Validation échouée

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "quantity": [
      "La quantité doit être au moins 1."
    ]
  }
}
```

---

## Collection Postman

Importer cette collection dans Postman pour tester rapidement :

1. Créer une nouvelle collection "CS-Trade Cart API"
2. Ajouter une variable d'environnement `base_url` = `http://localhost:8000/api`
3. Ajouter une variable `token` après la connexion
4. Créer les requêtes ci-dessus

---

## Prochaines étapes

Après avoir testé l'API du panier, vous pouvez :

1. Implémenter la logique complète des coupons (table `coupons`)
2. Ajouter la gestion des favoris (Wishlist)
3. Créer l'API de checkout (Orders)
4. Intégrer les paiements (Stripe, PayPal)
