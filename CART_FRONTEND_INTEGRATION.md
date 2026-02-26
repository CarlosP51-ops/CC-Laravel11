# Guide d'intégration Frontend - API Panier

Guide complet pour intégrer l'API Panier dans votre application React.

---

## Configuration de base

### 1. Variables d'environnement

```env
VITE_API_URL=http://localhost:8000/api
```

### 2. Service API (axios)

```typescript
// src/services/api.ts
import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Intercepteur pour ajouter le token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
```

---

## Types TypeScript

```typescript
// src/types/cart.ts

export interface CartItem {
  id: number;
  product_id: number;
  product_name: string;
  product_slug: string;
  category: string;
  seller: {
    id: number;
    name: string;
  };
  image: string | null;
  variant: {
    id: number;
    name: string;
    sku: string;
  } | null;
  price: number;
  compare_at_price: number | null;
  quantity: number;
  subtotal: number;
  savings: number;
  stock_available: number;
  rating: {
    average: number;
    count: number;
  };
}

export interface CartSummary {
  subtotal: number;
  discount: number;
  tax: number;
  tax_rate: number;
  total: number;
  coupon_code: string | null;
}

export interface ProductRecommendation {
  id: number;
  name: string;
  slug: string;
  price: number;
  compare_at_price: number | null;
  image: string | null;
  category: string;
  rating: {
    average: number;
    count: number;
  };
}

export interface Cart {
  id: number;
  items: CartItem[];
  items_count: number;
  summary: CartSummary;
  recommendations: ProductRecommendation[];
  stats: {
    satisfaction_rate: number;
    support_response_time: string;
    active_clients: string;
  };
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
}
```

---

## Service Panier

```typescript
// src/services/cartService.ts
import api from './api';
import { Cart, ApiResponse } from '../types/cart';

export const cartService = {
  // Récupérer le panier
  getCart: async (): Promise<Cart> => {
    const { data } = await api.get<ApiResponse<Cart>>('/cart');
    return data.data;
  },

  // Ajouter un produit
  addItem: async (
    productId: number,
    quantity: number,
    variantId?: number
  ): Promise<Cart> => {
    const { data } = await api.post<ApiResponse<Cart>>('/cart/items', {
      product_id: productId,
      quantity,
      product_variant_id: variantId,
    });
    return data.data;
  },

  // Mettre à jour la quantité
  updateItem: async (itemId: number, quantity: number): Promise<Cart> => {
    const { data } = await api.put<ApiResponse<Cart>>(
      `/cart/items/${itemId}`,
      { quantity }
    );
    return data.data;
  },

  // Supprimer un article
  removeItem: async (itemId: number): Promise<Cart> => {
    const { data } = await api.delete<ApiResponse<Cart>>(
      `/cart/items/${itemId}`
    );
    return data.data;
  },

  // Vider le panier
  clearCart: async (): Promise<Cart> => {
    const { data } = await api.delete<ApiResponse<Cart>>('/cart');
    return data.data;
  },

  // Appliquer un coupon
  applyCoupon: async (code: string): Promise<Cart> => {
    const { data } = await api.post<ApiResponse<Cart>>('/cart/apply-coupon', {
      code,
    });
    return data.data;
  },

  // Catégories populaires (public)
  getPopularCategories: async () => {
    const { data } = await api.get('/categories/popular');
    return data.data;
  },

  // Produits tendance (public)
  getTrendingProducts: async () => {
    const { data } = await api.get('/products/trending');
    return data.data;
  },
};
```

---

## Context React

```typescript
// src/contexts/CartContext.tsx
import React, { createContext, useContext, useState, useEffect } from 'react';
import { Cart } from '../types/cart';
import { cartService } from '../services/cartService';
import { toast } from 'react-hot-toast';

interface CartContextType {
  cart: Cart | null;
  loading: boolean;
  addToCart: (productId: number, quantity: number, variantId?: number) => Promise<void>;
  updateQuantity: (itemId: number, quantity: number) => Promise<void>;
  removeItem: (itemId: number) => Promise<void>;
  clearCart: () => Promise<void>;
  applyCoupon: (code: string) => Promise<void>;
  refreshCart: () => Promise<void>;
}

const CartContext = createContext<CartContextType | undefined>(undefined);

export const CartProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [cart, setCart] = useState<Cart | null>(null);
  const [loading, setLoading] = useState(false);

  const refreshCart = async () => {
    try {
      setLoading(true);
      const data = await cartService.getCart();
      setCart(data);
    } catch (error) {
      console.error('Error fetching cart:', error);
    } finally {
      setLoading(false);
    }
  };

  const addToCart = async (productId: number, quantity: number, variantId?: number) => {
    try {
      setLoading(true);
      const data = await cartService.addItem(productId, quantity, variantId);
      setCart(data);
      toast.success('Produit ajouté au panier');
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur lors de l\'ajout');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const updateQuantity = async (itemId: number, quantity: number) => {
    try {
      setLoading(true);
      const data = await cartService.updateItem(itemId, quantity);
      setCart(data);
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur lors de la mise à jour');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const removeItem = async (itemId: number) => {
    try {
      setLoading(true);
      const data = await cartService.removeItem(itemId);
      setCart(data);
      toast.success('Article retiré du panier');
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur lors de la suppression');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const clearCart = async () => {
    try {
      setLoading(true);
      const data = await cartService.clearCart();
      setCart(data);
      toast.success('Panier vidé');
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const applyCoupon = async (code: string) => {
    try {
      setLoading(true);
      const data = await cartService.applyCoupon(code);
      setCart(data);
      toast.success('Code promo appliqué');
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Code promo invalide');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshCart();
  }, []);

  return (
    <CartContext.Provider
      value={{
        cart,
        loading,
        addToCart,
        updateQuantity,
        removeItem,
        clearCart,
        applyCoupon,
        refreshCart,
      }}
    >
      {children}
    </CartContext.Provider>
  );
};

export const useCart = () => {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within CartProvider');
  }
  return context;
};
```

---

## Composants

### Cart.tsx

```typescript
import React from 'react';
import { useCart } from '../contexts/CartContext';
import CartItem from './CartItem';
import CartSummary from './CartSummary';
import EmptyCart from './EmptyCart';

const Cart: React.FC = () => {
  const { cart, loading } = useCart();

  if (loading && !cart) {
    return <div>Chargement...</div>;
  }

  if (!cart || cart.items_count === 0) {
    return <EmptyCart />;
  }

  return (
    <div className="cart-page">
      <header className="cart-header">
        <h1>Panier ({cart.items_count} articles)</h1>
        <button onClick={() => window.history.back()}>
          Continuer les achats
        </button>
      </header>

      <div className="cart-content">
        <div className="cart-items">
          {cart.items.map((item) => (
            <CartItem key={item.id} item={item} />
          ))}
        </div>

        <CartSummary summary={cart.summary} />
      </div>

      {cart.recommendations.length > 0 && (
        <section className="recommendations">
          <h2>Recommandations</h2>
          <div className="products-grid">
            {cart.recommendations.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      )}

      <section className="trust-stats">
        <div className="stat">
          <span>{cart.stats.satisfaction_rate}%</span>
          <span>Satisfaction</span>
        </div>
        <div className="stat">
          <span>{cart.stats.support_response_time}</span>
          <span>Support</span>
        </div>
        <div className="stat">
          <span>{cart.stats.active_clients}</span>
          <span>Clients actifs</span>
        </div>
      </section>
    </div>
  );
};

export default Cart;
```

### CartItem.tsx

```typescript
import React from 'react';
import { useCart } from '../contexts/CartContext';
import { CartItem as CartItemType } from '../types/cart';

interface Props {
  item: CartItemType;
}

const CartItem: React.FC<Props> = ({ item }) => {
  const { updateQuantity, removeItem } = useCart();

  const handleQuantityChange = (newQuantity: number) => {
    if (newQuantity > 0 && newQuantity <= item.stock_available) {
      updateQuantity(item.id, newQuantity);
    }
  };

  return (
    <div className="cart-item">
      <img src={item.image || '/placeholder.jpg'} alt={item.product_name} />
      
      <div className="item-details">
        <h3>{item.product_name}</h3>
        <p className="category">{item.category}</p>
        <p className="seller">Vendu par {item.seller.name}</p>
        {item.variant && <p className="variant">{item.variant.name}</p>}
        
        <div className="rating">
          ⭐ {item.rating.average} ({item.rating.count} avis)
        </div>
      </div>

      <div className="item-price">
        <span className="current-price">{item.price}€</span>
        {item.compare_at_price && (
          <span className="old-price">{item.compare_at_price}€</span>
        )}
        {item.savings > 0 && (
          <span className="savings">Économie: {item.savings}€</span>
        )}
      </div>

      <div className="item-quantity">
        <button onClick={() => handleQuantityChange(item.quantity - 1)}>-</button>
        <span>{item.quantity}</span>
        <button onClick={() => handleQuantityChange(item.quantity + 1)}>+</button>
      </div>

      <div className="item-subtotal">
        {item.subtotal}€
      </div>

      <button onClick={() => removeItem(item.id)} className="remove-btn">
        🗑️
      </button>
    </div>
  );
};

export default CartItem;
```

### CartSummary.tsx

```typescript
import React, { useState } from 'react';
import { useCart } from '../contexts/CartContext';
import { CartSummary as CartSummaryType } from '../types/cart';

interface Props {
  summary: CartSummaryType;
}

const CartSummary: React.FC<Props> = ({ summary }) => {
  const { applyCoupon } = useCart();
  const [couponCode, setCouponCode] = useState('');

  const handleApplyCoupon = async (e: React.FormEvent) => {
    e.preventDefault();
    if (couponCode.trim()) {
      await applyCoupon(couponCode);
      setCouponCode('');
    }
  };

  return (
    <div className="cart-summary">
      <h2>Résumé de la commande</h2>

      <div className="summary-line">
        <span>Sous-total</span>
        <span>{summary.subtotal}€</span>
      </div>

      {summary.discount > 0 && (
        <div className="summary-line discount">
          <span>Réduction ({summary.coupon_code})</span>
          <span>-{summary.discount}€</span>
        </div>
      )}

      <div className="summary-line">
        <span>TVA ({summary.tax_rate * 100}%)</span>
        <span>{summary.tax}€</span>
      </div>

      <div className="summary-line total">
        <span>Total</span>
        <span>{summary.total}€</span>
      </div>

      <form onSubmit={handleApplyCoupon} className="coupon-form">
        <input
          type="text"
          placeholder="Code promo"
          value={couponCode}
          onChange={(e) => setCouponCode(e.target.value)}
        />
        <button type="submit">Appliquer</button>
      </form>

      <button className="checkout-btn">
        Passer la commande
      </button>
    </div>
  );
};

export default CartSummary;
```

---

## Gestion des erreurs

```typescript
// src/utils/errorHandler.ts
import { toast } from 'react-hot-toast';

export const handleApiError = (error: any) => {
  if (error.response) {
    // Erreur de réponse du serveur
    const message = error.response.data?.message || 'Une erreur est survenue';
    toast.error(message);
    
    // Erreurs de validation
    if (error.response.data?.errors) {
      Object.values(error.response.data.errors).forEach((errors: any) => {
        errors.forEach((err: string) => toast.error(err));
      });
    }
  } else if (error.request) {
    // Pas de réponse du serveur
    toast.error('Impossible de contacter le serveur');
  } else {
    // Autre erreur
    toast.error('Une erreur inattendue est survenue');
  }
};
```

---

## Installation des dépendances

```bash
npm install axios react-hot-toast
```

---

## Utilisation dans App.tsx

```typescript
import { CartProvider } from './contexts/CartContext';
import { Toaster } from 'react-hot-toast';

function App() {
  return (
    <CartProvider>
      <Toaster position="top-right" />
      {/* Vos routes */}
    </CartProvider>
  );
}
```

---

## Tests

```typescript
// src/__tests__/cartService.test.ts
import { cartService } from '../services/cartService';

describe('Cart Service', () => {
  it('should fetch cart', async () => {
    const cart = await cartService.getCart();
    expect(cart).toHaveProperty('items');
    expect(cart).toHaveProperty('summary');
  });

  it('should add item to cart', async () => {
    const cart = await cartService.addItem(1, 2);
    expect(cart.items_count).toBeGreaterThan(0);
  });
});
```

---

## Optimisations

1. **Debounce** pour les mises à jour de quantité
2. **Optimistic updates** pour une meilleure UX
3. **Cache** avec React Query
4. **Lazy loading** des recommandations

---

Votre API Panier est maintenant prête à être intégrée dans votre frontend React ! 🚀
