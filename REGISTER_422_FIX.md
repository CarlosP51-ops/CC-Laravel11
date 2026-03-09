# ✅ Correction de l'Erreur 422 lors de l'Inscription

## 🐛 Erreur

```
POST http://localhost:8000/api/register 422 (Unprocessable Content)
```

## 🔍 Cause du Problème

L'erreur 422 signifie que la **validation backend a échoué**. Le problème venait d'une incohérence dans les rôles acceptés :

### Avant la Correction ❌

**Frontend (Register.jsx)** :
```javascript
role: 'client' ou 'seller'
```

**Backend (RegisterRequest.php)** :
```php
'role' => 'required|string|in:client,customer,vendor'
```

❌ Le backend n'acceptait pas `seller` !

## ✅ Solution Appliquée

### Modification du RegisterRequest

**Fichier** : `app/Http/Requests/RegisterRequest.php`

```php
// AVANT
'role' => 'required|string|in:client,customer,vendor',

if ($this->role === 'vendor') {
    // Règles vendeur
}

// APRÈS
'role' => 'required|string|in:client,seller',

if ($this->role === 'seller') {
    // Règles vendeur
}
```

## 📊 Flux de Validation

### Frontend → Backend

```
Frontend envoie:
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+33612345678",
  "role": "seller",  // ← Doit être accepté
  "store_name": "Ma Boutique",
  "slug": "ma-boutique",
  ...
}

Backend valide:
✅ role in ['client', 'seller']
✅ Si role === 'seller' → Valider champs boutique
```

## 🔒 Règles de Validation

### Champs Communs (Client + Vendeur)
- `name` : Obligatoire, max 255 caractères
- `email` : Obligatoire, email valide, unique
- `password` : Obligatoire, règles de sécurité
- `password_confirmation` : Obligatoire, doit correspondre
- `phone` : Optionnel, max 20 caractères
- `role` : Obligatoire, doit être 'client' ou 'seller'

### Champs Vendeur (Si role === 'seller')
- `store_name` : Obligatoire, max 255 caractères
- `slug` : Obligatoire, unique, max 255 caractères
- `description` : Optionnel
- `logo` : Optionnel, image (jpeg,jpg,png,gif,webp), max 2MB
- `banner` : Optionnel, image (jpeg,jpg,png,gif,webp), max 5MB
- `address` : Optionnel, max 255 caractères
- `city` : Optionnel, max 100 caractères
- `postal_code` : Optionnel, max 20 caractères
- `country` : Optionnel, max 100 caractères

## 🧪 Tests de Validation

### Test 1 : Inscription Client ✅
```json
{
  "name": "Client Test",
  "email": "client@test.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+33612345678",
  "role": "client"
}
```
**Résultat attendu** : 201 Created

### Test 2 : Inscription Vendeur ✅
```json
{
  "name": "Vendeur Test",
  "email": "vendor@test.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+33612345678",
  "role": "seller",
  "store_name": "Ma Boutique",
  "slug": "ma-boutique",
  "description": "Description de ma boutique"
}
```
**Résultat attendu** : 201 Created

### Test 3 : Rôle Invalide ❌
```json
{
  "role": "admin"
}
```
**Résultat attendu** : 422 avec message "Le rôle doit être client ou vendeur."

### Test 4 : Email Déjà Utilisé ❌
```json
{
  "email": "existing@test.com"
}
```
**Résultat attendu** : 422 avec message "Cet email est déjà enregistré."

### Test 5 : Vendeur Sans store_name ❌
```json
{
  "role": "seller"
  // Pas de store_name
}
```
**Résultat attendu** : 422 avec message "Veuillez fournir le nom de votre boutique."

## 📋 Messages d'Erreur en Français

Tous les messages de validation sont en français :

```php
'name.required' => 'Veuillez fournir votre nom complet.'
'email.unique' => 'Cet email est déjà enregistré.'
'password_confirmation.same' => 'Les mots de passe ne correspondent pas.'
'role.in' => 'Le rôle doit être client ou vendeur.'
'store_name.required' => 'Veuillez fournir le nom de votre boutique.'
'slug.unique' => 'Ce slug est déjà utilisé.'
'logo.max' => 'Le logo ne peut pas dépasser 2 Mo.'
'banner.max' => 'La bannière ne peut pas dépasser 5 Mo.'
```

## 🔧 Test avec cURL

### Inscription Client
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Client Test",
    "email": "client@test.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+33612345678",
    "role": "client"
  }'
```

### Inscription Vendeur
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: multipart/form-data" \
  -H "Accept: application/json" \
  -F "name=Vendeur Test" \
  -F "email=vendor@test.com" \
  -F "password=password123" \
  -F "password_confirmation=password123" \
  -F "phone=+33612345678" \
  -F "role=seller" \
  -F "store_name=Ma Boutique" \
  -F "slug=ma-boutique" \
  -F "description=Description de ma boutique"
```

## ✅ Checklist de Validation

### Backend
- [x] RegisterRequest accepte 'client' et 'seller'
- [x] Validation conditionnelle pour les vendeurs
- [x] Messages d'erreur en français
- [x] AuthController gère le rôle 'seller'
- [x] Création de l'enregistrement Seller

### Frontend
- [x] Envoie 'client' ou 'seller'
- [x] Envoie tous les champs requis
- [x] Gère les erreurs 422
- [x] Affiche les messages d'erreur

### Base de Données
- [x] Table users : role ENUM('client', 'seller', 'admin')
- [x] Table sellers : Champs boutique
- [x] Contraintes d'unicité (email, slug)

## 🎯 Résultat Final

L'inscription fonctionne maintenant correctement :
- ✅ **Client** : Inscription simple avec validation
- ✅ **Vendeur** : Inscription complète avec boutique
- ✅ **Validation** : Messages d'erreur clairs en français
- ✅ **Sécurité** : Tous les champs validés côté backend

## 📝 Notes Importantes

1. **Rôles standardisés** : `client` et `seller` partout
2. **Validation stricte** : Tous les champs obligatoires vérifiés
3. **Messages français** : Meilleure UX pour les utilisateurs
4. **Transaction DB** : Rollback en cas d'erreur

Le problème 422 est maintenant résolu ! 🎉
