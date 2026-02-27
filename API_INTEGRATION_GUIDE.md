# 🚀 Guide d'Intégration API - Digital Marketplace

## 📋 Table des matières
- [Configuration](#configuration)
- [Démarrage](#démarrage)
- [Endpoints d'authentification](#endpoints-dauthentification)
- [Tests](#tests)
- [Résolution de problèmes](#résolution-de-problèmes)

---

## ⚙️ Configuration

### Backend (Laravel)

1. **Variables d'environnement** (`.env`)
```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digital_marketing_center
DB_USERNAME=root
DB_PASSWORD=
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Générer la clé d'application**
```bash
php artisan key:generate
```

4. **Exécuter les migrations**
```bash
php artisan migrate
```

5. **Créer le lien symbolique pour le storage**
```bash
php artisan storage:link
```

### Frontend (React)

1. **Variables d'environnement** (`.env`)
```env
VITE_API_URL=http://localhost:8000/api
```

2. **Installer les dépendances**
```bash
npm install
```

---

## 🚀 Démarrage

### Backend
```bash
cd digital-marketplace-backend
php artisan serve
```
L'API sera disponible sur `http://localhost:8000`

### Frontend
```bash
cd digital-marketplace
npm run dev
```
Le frontend sera disponible sur `http://localhost:5173`

---

## 🔐 Endpoints d'Authentification

### 1. Inscription (Register)

**Endpoint:** `POST /api/register`

**Headers:**
```
Content-Type: multipart/form-data
Accept: application/json
```

**Body (Client):**
```json
{
  "name": "Jean Dupont",
  "email": "jean@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+33612345678",
  "role": "customer"
}
```

**Body (Vendeur):**
```json
{
  "name": "Marie Martin",
  "email": "marie@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+33612345678",
  "role": "vendor",
  "store_name": "Ma Boutique",
  "slug": "ma-boutique",
  "description": "Description de ma boutique",
  "address": "123 Rue de la Paix",
  "city": "Paris",
  "postal_code": "75001",
  "country": "France",
  "logo": [FILE],
  "banner": [FILE]
}
```

**Réponse (Success):**
```json
{
  "success": true,
  "message": "Inscription réussie.",
  "user": {
    "id": 1,
    "name": "Jean Dupont",
    "fullname": "Jean Dupont",
    "email": "jean@example.com",
    "phone": "+33612345678",
    "role": "client"
  },
  "token": "1|abcdef123456..."
}
```

**Réponse (Erreur):**
```json
{
  "success": false,
  "message": "Erreur lors de l'inscription",
  "errors": {
    "email": ["Cet email est déjà enregistré."]
  }
}
```

---

### 2. Connexion (Login)

**Endpoint:** `POST /api/login`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "email": "jean@example.com",
  "password": "password123"
}
```

**Réponse (Success):**
```json
{
  "success": true,
  "message": "Connexion réussie.",
  "user": {
    "id": 1,
    "name": "Jean Dupont",
    "fullname": "Jean Dupont",
    "email": "jean@example.com",
    "phone": "+33612345678",
    "role": "client"
  },
  "token": "2|xyz789..."
}
```

**Réponse (Erreur):**
```json
{
  "message": "Les identifiants fournis sont incorrects.",
  "errors": {
    "email": ["Les identifiants fournis sont incorrects."]
  }
}
```

---

### 3. Déconnexion (Logout)

**Endpoint:** `POST /api/logout`

**Headers:**
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

**Réponse:**
```json
{
  "success": true,
  "message": "Déconnexion réussie."
}
```

---

### 4. Utilisateur connecté (Me)

**Endpoint:** `GET /api/user`

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
```

**Réponse:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Jean Dupont",
    "fullname": "Jean Dupont",
    "email": "jean@example.com",
    "phone": "+33612345678",
    "role": "client"
  }
}
```

---

## 🧪 Tests

### Test avec cURL

**Inscription:**
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Accept: application/json" \
  -F "name=Test User" \
  -F "email=test@example.com" \
  -F "password=password123" \
  -F "password_confirmation=password123" \
  -F "phone=+33612345678" \
  -F "role=customer"
```

**Connexion:**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

**Utilisateur connecté:**
```bash
curl -X GET http://localhost:8000/api/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 🔧 Résolution de problèmes

### Erreur CORS
Si vous rencontrez des erreurs CORS:
1. Vérifiez que `FRONTEND_URL` est défini dans `.env`
2. Exécutez `php artisan config:clear`
3. Redémarrez le serveur Laravel

### Erreur 500 lors de l'upload de fichiers
1. Vérifiez les permissions du dossier `storage/`
2. Exécutez `php artisan storage:link`
3. Vérifiez la configuration dans `config/filesystems.php`

### Token invalide
1. Vérifiez que le token est bien envoyé dans le header `Authorization: Bearer {token}`
2. Vérifiez que le token n'a pas expiré
3. Essayez de vous reconnecter

### Base de données
Si les migrations échouent:
1. Vérifiez la connexion à la base de données dans `.env`
2. Créez la base de données manuellement: `CREATE DATABASE digital_marketing_center;`
3. Exécutez `php artisan migrate:fresh`

---

## 📝 Notes importantes

1. **Rôles acceptés:** `client`, `customer` (alias de client), `vendor`, `admin`
2. **Taille maximale des fichiers:**
   - Logo: 2 Mo
   - Bannière: 5 Mo
3. **Formats d'images acceptés:** jpeg, jpg, png, gif, webp
4. **Le slug est auto-généré** depuis le nom de la boutique dans le frontend
5. **Les comptes vendeurs** sont créés avec `is_verified=false` et `is_active=false` par défaut

---

## 🎯 Prochaines étapes

- [ ] Implémenter la vérification d'email
- [ ] Ajouter la réinitialisation de mot de passe
- [ ] Créer les endpoints pour les produits
- [ ] Créer les endpoints pour le panier
- [ ] Créer les endpoints pour les commandes
