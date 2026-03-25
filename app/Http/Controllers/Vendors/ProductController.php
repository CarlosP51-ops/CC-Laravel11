<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Récupérer tous les produits du vendeur
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $seller = $user->seller;

        $query = Product::where('seller_id', $seller->id)
            ->with(['category', 'subcategory', 'productImages']);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 12);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    /**
     * Stats d'un produit spécifique
     */
    public function stats(Request $request, $id)
    {
        $user = $request->user();
        $seller = $user->seller;

        $product = Product::where('seller_id', $seller->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produit non trouvé'], 404);
        }

        // Ventes totales & revenus
        $salesData = \DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $id)
            ->whereIn('orders.status', ['completed', 'delivered', 'processing', 'shipped'])
            ->selectRaw('COUNT(*) as total_sales, SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_units')
            ->first();

        // Avis
        $reviewData = \DB::table('reviews')
            ->where('product_id', $id)
            ->where('is_approved', true)
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as avg_rating')
            ->first();

        // Favoris
        $wishlistCount = \DB::table('wishlists')->where('product_id', $id)->count();

        // Ventes des 6 derniers mois
        $monthlySales = \DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $id)
            ->whereIn('orders.status', ['completed', 'delivered', 'processing', 'shipped'])
            ->where('orders.created_at', '>=', now()->subMonths(6))
            ->selectRaw("DATE_FORMAT(orders.created_at, '%Y-%m') as month, SUM(order_items.quantity) as units, SUM(order_items.total_price) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Remplir les mois manquants
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $found = $monthlySales->firstWhere('month', $key);
            $months[] = [
                'month' => now()->subMonths($i)->locale('fr')->isoFormat('MMM YY'),
                'units' => $found ? (int)$found->units : 0,
                'revenue' => $found ? (float)$found->revenue : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales'   => (int)($salesData->total_sales ?? 0),
                'total_units'   => (int)($salesData->total_units ?? 0),
                'total_revenue' => (float)($salesData->total_revenue ?? 0),
                'avg_rating'    => round((float)($reviewData->avg_rating ?? 0), 1),
                'total_reviews' => (int)($reviewData->total_reviews ?? 0),
                'wishlist_count'=> $wishlistCount,
                'monthly'       => $months,
            ]
        ]);
    }

    /**
     * Récupérer un produit spécifique
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $seller = $user->seller;

        $product = Product::where('seller_id', $seller->id)
            ->where('id', $id)
            ->with(['category', 'subcategory', 'variants', 'productImages'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Créer un nouveau produit
     */
    public function store(StoreProductRequest $request)
    {
        $user = $request->user();
        $seller = $user->seller;

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Profil vendeur introuvable. Veuillez contacter l\'administrateur.'
            ], 403);
        }

        // Générer le slug
        $slug = $request->slug ?: Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;
        
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Générer le SKU si non fourni
        $sku = $request->sku ?: 'SKU-' . strtoupper(Str::random(8));

        // Upload des images
        $uploadedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $uploadedImages[] = Storage::url($path);
            }
        }

        // Formater les dimensions
        $dimensions = null;
        if ($request->has('dimensions')) {
            $dims = $request->dimensions;
            if (isset($dims['length']) || isset($dims['width']) || isset($dims['height'])) {
                $dimensions = sprintf(
                    '%s x %s x %s cm',
                    $dims['length'] ?? '0',
                    $dims['width'] ?? '0',
                    $dims['height'] ?? '0'
                );
            }
        }

        // Créer le produit avec statut "pending" (en attente de validation admin)
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'short_description' => $request->shortDescription,
            'price' => $request->price,
            'compare_at_price' => $request->comparePrice,
            'category_id' => $request->category,
            'subcategory_id' => $request->subcategory,
            'stock_quantity' => $request->stock,
            'sku' => $sku,
            'weight' => $request->weight,
            'dimensions' => $dimensions,
            'tags' => $request->tags ? implode(',', $request->tags) : null,
            'is_digital' => $request->boolean('is_digital'),
            'status' => 'pending',
            'is_active' => false,
        ]);

        // Insérer les images dans product_images
        $featuredIdx = (int) $request->input('featuredImage', 0);
        foreach ($uploadedImages as $i => $url) {
            $product->productImages()->create([
                'image_path' => $url,
                'is_primary' => $i === $featuredIdx,
                'order' => $i,
            ]);
        }

        // Sauvegarder les variantes
        if ($request->has('variants') && is_array($request->variants)) {
            foreach ($request->variants as $variant) {
                if (empty($variant['attributes'])) continue;
                $product->variants()->create([
                    'sku' => $variant['sku'] ?? 'VAR-' . strtoupper(Str::random(6)),
                    'attributes' => $variant['attributes'],
                    'price' => $variant['price'] ?? null,
                    'stock_quantity' => $variant['stock_quantity'] ?? 0,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès. Il sera visible après validation par l\'administrateur.',
            'data' => $product->load(['category', 'subcategory', 'variants'])
        ], 201);
    }

    /**
     * Mettre à jour un produit
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $seller = $user->seller;

        $product = Product::where('seller_id', $seller->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0|gt:price',
            'category_id' => 'required|exists:categories,id',
            'subcategory_id' => 'nullable|exists:categories,id',
            'stock' => 'required|integer|min:0',
            'sku' => ['nullable', 'string', Rule::unique('products')->ignore($product->id)],
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string',
            'tags' => 'nullable|string',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_digital' => 'boolean',
            'digital_file' => 'nullable|file|max:10240',
        ]);

        // Générer nouveau slug si le nom a changé
        $slug = $product->slug;
        if ($request->name !== $product->name) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;
            
            while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Gérer les images
        if ($request->hasFile('images')) {
            // Supprimer les anciennes images
            foreach ($product->productImages as $img) {
                $path = str_replace('/storage/', '', $img->image_path);
                Storage::disk('public')->delete($path);
                $img->delete();
            }
            // Upload nouvelles images
            $featuredIdx = (int) $request->input('featuredImage', 0);
            foreach ($request->file('images') as $i => $image) {
                $path = $image->store('products', 'public');
                $product->productImages()->create([
                    'image_path' => Storage::url($path),
                    'is_primary' => $i === $featuredIdx,
                    'order' => $i,
                ]);
            }
        }

        // Gérer le fichier numérique
        $digitalFilePath = $product->digital_file_path;
        if ($request->hasFile('digital_file')) {
            // Supprimer l'ancien fichier
            if ($digitalFilePath) {
                Storage::disk('private')->delete($digitalFilePath);
            }
            $digitalFilePath = $request->file('digital_file')->store('digital-products', 'private');
        }

        // Mettre à jour le produit
        $product->update([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'compare_at_price' => $request->compare_at_price,
            'category_id' => $request->category_id,
            'subcategory_id' => $request->subcategory_id,
            'stock_quantity' => $request->stock,
            'sku' => $request->sku ?: $product->sku,
            'weight' => $request->weight,
            'dimensions' => $request->dimensions,
            'tags' => $request->tags,
            'is_digital' => $request->boolean('is_digital'),
            'digital_file_path' => $digitalFilePath,
            'status' => $product->status === 'approved' ? 'pending' : $product->status,
        ]);

        // Synchroniser les variantes
        if ($request->has('variants')) {
            $incoming = is_array($request->variants) ? $request->variants : json_decode($request->variants, true) ?? [];
            // Supprimer les variantes existantes et recréer (approche simple)
            $product->variants()->delete();
            foreach ($incoming as $variant) {
                if (empty($variant['attributes'])) continue;
                $product->variants()->create([
                    'sku' => $variant['sku'] ?? 'VAR-' . strtoupper(Str::random(6)),
                    'attributes' => $variant['attributes'],
                    'price' => $variant['price'] ?? null,
                    'stock_quantity' => $variant['stock_quantity'] ?? 0,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès.',
            'data' => $product->load(['category', 'subcategory', 'variants'])
        ]);
    }

    /**
     * Supprimer un produit
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $seller = $user->seller;

        $product = Product::where('seller_id', $seller->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Supprimer les images
        foreach ($product->productImages as $img) {
            $path = str_replace('/storage/', '', $img->image_path);
            Storage::disk('public')->delete($path);
            $img->delete();
        }

        // Supprimer le fichier numérique
        if ($product->digital_file_path) {
            Storage::disk('private')->delete($product->digital_file_path);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès'
        ]);
    }

    /**
     * Récupérer les catégories et sous-catégories
     */
    public function getCategories()
    {
        try {
            $categories = Category::whereNull('parent_id')
                ->with(['children' => function ($query) {
                    $query->orderBy('name');
                }])
                ->orderBy('name')
                ->get();

            \Log::info('Categories loaded', ['count' => $categories->count()]);

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading categories', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des catégories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un produit
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = $request->user();
        $seller = $user->seller;

        $product = Product::where('seller_id', $seller->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Seuls les produits approuvés peuvent être activés/désactivés
        if ($product->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les produits approuvés peuvent être activés/désactivés'
            ], 400);
        }

        $product->update([
            'is_active' => !$product->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => $product->is_active ? 'Produit activé' : 'Produit désactivé',
            'data' => $product
        ]);
    }
}