# API - Page de detail produit

## Date : 25 Fevrier 2026

---

## Endpoints disponibles

### 1. Details du produit (avec preview)
**Endpoint** : `GET /api/products/{id}`

**Description** : Retourne toutes les informations du produit + 5 premiers avis + 4 produits similaires

**Parametres** : Aucun

**Reponse** :
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 1,
      "name": "Template WordPress Premium",
      "slug": "template-wordpress-premium",
      "description": "Description complete...",
      "short_description": "Description courte...",
      "price": 49.99,
      "compare_at_price": 99.99,
      "discount_percentage": 50,
      "sku": "WP-TEMP-001",
      "stock_quantity": 999,
      "sales_count": 156,
      "rating": 4.5,
      "reviews_count": 42,
      "last_updated": "2026-02-20",
      "is_active": true,
      "images": [
        {
          "id": 1,
          "image_path": "/storage/products/main.jpg",
          "is_primary": true
        }
      ],
      "category": {
        "id": 1,
        "name": "Templates",
        "slug": "templates"
      },
      "seller": {
        "id": 1,
        "store_name": "Digital Store",
        "slug": "digital-store",
        "is_verified": true,
        "logo": "/storage/logos/logo.png",
        "description": "Vendeur professionnel..."
      },
      "variants": [
        {
          "id": 1,
          "name": "Licence Standard",
          "price": 49.99,
          "stock_quantity": 100
        }
      ],
      "features": [
        "Telechargement instantane",
        "Paiement securise",
        "Garantie 30 jours",
        "Mises a jour gratuites",
        "Support prioritaire"
      ]
    },
    "reviews_preview": {
      "summary": {
        "average_rating": 4.5,
        "total_reviews": 42,
        "rating_distribution": {
          "5": 25,
          "4": 10,
          "3": 5,
          "2": 1,
          "1": 1
        }
      },
      "reviews": [
        {
          "id": 1,
          "user_name": "John Doe",
          "user_initials": "JD",
          "rating": 5,
          "comment": "Excellent produit !",
          "created_at": "2026-02-20",
          "helpful_count": 12,
          "is_verified_purchase": true
        }
      ],
      "has_more": true
    },
    "related_products": [
      {
        "id": 2,
        "name": "Autre template",
        "slug": "autre-template",
        "price": 39.99,
        "compare_at_price": null,
        "image": "/storage/products/thumb.jpg",
        "rating": 4.3,
        "reviews_count": 28,
        "sales_count": 89,
        "is_popular": true,
        "category": {...},
        "seller": {...}
      }
    ],
    "technical_details": {
      "sku": "WP-TEMP-001",
      "format": "Digital Download",
      "file_size": "Variable",
      "compatibility": "Tous navigateurs modernes",
      "license": "Licence standard",
      "version": "1.0.0",
      "last_update": "2026-02-20",
      "included": [
        "Fichiers source",
        "Documentation",
        "Support technique"
      ]
    },
    "breadcrumb": [
      {
        "name": "Accueil",
        "url": "/",
        "active": false
      },
      {
        "name": "Produits",
        "url": "/products",
        "active": false
      },
      {
        "name": "Templates",
        "url": "/products?category=templates",
        "active": false
      },
      {
        "name": "Template WordPress Premium",
        "url": null,
        "active": true
      }
    ]
  }
}
```

---

### 2. Produits similaires
**Endpoint** : `GET /api/products/{id}/related`

**Description** : Retourne plus de produits similaires

**Parametres** :
- `limit` (optionnel) : Nombre de produits (defaut: 4)

**Exemple** : `GET /api/products/1/related?limit=8`

**Reponse** :
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "name": "Autre template",
      "slug": "autre-template",
      "price": 39.99,
      "compare_at_price": null,
      "image": "/storage/products/thumb.jpg",
      "rating": 4.3,
      "reviews_count": 28,
      "sales_count": 89,
      "is_popular": true,
      "category": {...},
      "seller": {...}
    }
  ]
}
```

---

### 3. Liste des avis (paginee)
**Endpoint** : `GET /api/products/{product}/reviews`

**Description** : Retourne les avis avec pagination et filtres

**Parametres** :
- `page` (optionnel) : Numero de page (defaut: 1)
- `per_page` (optionnel) : Nombre par page (defaut: 10)
- `sort` (optionnel) : Tri (`recent`, `helpful`, `rating_desc`, `rating_asc`)
- `rating` (optionnel) : Filtrer par note (1-5)

**Exemple** : `GET /api/products/1/reviews?page=2&sort=helpful&rating=5`

**Reponse** :
```json
{
  "success": true,
  "data": {
    "current_page": 2,
    "data": [
      {
        "id": 11,
        "user_name": "Jane Smith",
        "user_initials": "JS",
        "rating": 5,
        "comment": "Tres bon produit, je recommande !",
        "created_at": "2026-02-19",
        "created_at_human": "il y a 1 jour",
        "helpful_count": 8,
        "is_verified_purchase": true
      }
    ],
    "per_page": 10,
    "total": 42,
    "last_page": 5,
    "has_more_pages": true
  },
  "summary": {
    "average_rating": 4.5,
    "total_reviews": 42,
    "rating_distribution": {
      "5": 25,
      "4": 10,
      "3": 5,
      "2": 1,
      "1": 1
    }
  }
}
```

---

### 4. Creer un avis (protege)
**Endpoint** : `POST /api/products/{product}/reviews`

**Description** : Permet a un utilisateur authentifie de laisser un avis

**Headers** : `Authorization: Bearer {token}`

**Body** :
```json
{
  "rating": 5,
  "comment": "Excellent produit, je recommande !"
}
```

**Validation** :
- `rating` : requis, entier entre 1 et 5
- `comment` : requis, string, min 10 caracteres, max 1000

**Reponse succes** :
```json
{
  "success": true,
  "message": "Votre avis a ete publie avec succes.",
  "data": {
    "id": 43,
    "user_name": "John Doe",
    "user_initials": "JD",
    "rating": 5,
    "comment": "Excellent produit, je recommande !",
    "created_at": "2026-02-25",
    "created_at_human": "il y a quelques secondes",
    "helpful_count": 0,
    "is_verified_purchase": true
  }
}
```

**Reponse erreur (deja evalue)** :
```json
{
  "success": false,
  "message": "Vous avez deja laisse un avis pour ce produit."
}
```

**Reponse erreur (pas achete)** :
```json
{
  "success": false,
  "message": "Vous devez acheter ce produit avant de laisser un avis."
}
```

---

### 5. Marquer un avis comme utile (protege)
**Endpoint** : `POST /api/reviews/{review}/helpful`

**Description** : Permet de marquer un avis comme utile

**Headers** : `Authorization: Bearer {token}`

**Body** : Aucun

**Reponse succes** :
```json
{
  "success": true,
  "message": "Merci pour votre retour !",
  "data": {
    "helpful_count": 13
  }
}
```

**Reponse erreur (deja marque)** :
```json
{
  "success": false,
  "message": "Vous avez deja marque cet avis comme utile."
}
```

---

### 6. Signaler un avis (protege)
**Endpoint** : `POST /api/reviews/{review}/report`

**Description** : Permet de signaler un avis inapproprie

**Headers** : `Authorization: Bearer {token}`

**Body** :
```json
{
  "reason": "spam",
  "details": "Cet avis contient des liens publicitaires"
}
```

**Validation** :
- `reason` : requis, valeurs: `spam`, `inappropriate`, `fake`, `other`
- `details` : optionnel, string, max 500 caracteres

**Reponse succes** :
```json
{
  "success": true,
  "message": "Merci pour votre signalement. Nous allons examiner cet avis."
}
```

---

## Tables necessaires

### Table `review_helpful` (a creer)
```sql
CREATE TABLE review_helpful (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_helpful (review_id, user_id)
);
```

### Table `review_reports` (a creer)
```sql
CREATE TABLE review_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reason ENUM('spam', 'inappropriate', 'fake', 'other') NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Colonne a ajouter dans `reviews`
```sql
ALTER TABLE reviews ADD COLUMN helpful_count INT DEFAULT 0;
```

---

## Integration Frontend

### Exemple React - Chargement initial
```javascript
const ProductDetails = ({ productId }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`/api/products/${productId}`)
      .then(res => res.json())
      .then(result => {
        setData(result.data);
        setLoading(false);
      });
  }, [productId]);

  if (loading) return <Loader />;

  return (
    <>
      <Breadcrumb items={data.breadcrumb} />
      <ProductGallery images={data.product.images} />
      <ProductInfo product={data.product} />
      <ProductTabs 
        description={data.product.description}
        technicalDetails={data.technical_details}
        reviewsPreview={data.reviews_preview}
        productId={productId}
      />
      <RelatedProducts products={data.related_products} />
    </>
  );
};
```

### Exemple React - Pagination des avis
```javascript
const ReviewsList = ({ productId, initialReviews }) => {
  const [reviews, setReviews] = useState(initialReviews);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);

  const loadMore = async () => {
    const response = await fetch(
      `/api/products/${productId}/reviews?page=${page + 1}`
    );
    const result = await response.json();
    
    setReviews([...reviews, ...result.data.data]);
    setPage(page + 1);
    setHasMore(result.data.has_more_pages);
  };

  return (
    <>
      {reviews.map(review => (
        <ReviewCard key={review.id} {...review} />
      ))}
      {hasMore && (
        <button onClick={loadMore}>Voir plus d'avis</button>
      )}
    </>
  );
};
```

---

## Notes importantes

1. **Achat verifie** : Un badge "Achat verifie" est affiche si l'utilisateur a achete le produit
2. **Produit populaire** : Badge affiche si le produit a plus de 50 ventes
3. **Images** : L'image primaire est utilisee en priorite, sinon la premiere image
4. **Pourcentage de reduction** : Calcule automatiquement si compare_at_price existe
5. **Initiales** : Generees automatiquement a partir du nom complet de l'utilisateur

---

## Prochaines etapes

1. Creer les migrations pour `review_helpful` et `review_reports`
2. Ajouter la colonne `helpful_count` dans la table `reviews`
3. Creer des seeders pour tester les avis
4. Implementer le cache pour les produits populaires
5. Ajouter des tests unitaires
