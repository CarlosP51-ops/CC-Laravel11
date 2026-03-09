<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->with(['category', 'subcategory']);

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
     * Récupérer un produit spécifique
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $seller = $user->seller;

        $product = Product::where('seller_id', $seller->id)
            ->where('id', $id)
            ->with(['category', 'subcategory'])
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
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $images[] = Storage::url($path);
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
            'stock' => $request->stock,
            'stock_quantity' => $request->stock, // Pour compatibilité
            'sku' => $sku,
            'weight' => $request->weight,
            'dimensions' => $dimensions,
            'tags' => $request->tags ? implode(',', $request->tags) : null,
            'images' => $images,
            'status' => 'pending', // En attente de validation admin
            'is_active' => false, // Inactif jusqu'à validation
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès. Il sera visible après validation par l\'administrateur.',
            'data' => $product->load(['category', 'subcategory'])
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
        $images = $product->images ?? [];
        if ($request->hasFile('images')) {
            // Supprimer les anciennes images
            foreach ($images as $image) {
                $path = str_replace('/storage/', '', $image);
                Storage::disk('public')->delete($path);
            }
            
            // Upload nouvelles images
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $images[] = Storage::url($path);
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
            'stock' => $request->stock,
            'sku' => $request->sku ?: $product->sku,
            'weight' => $request->weight,
            'dimensions' => $request->dimensions,
            'tags' => $request->tags,
            'images' => $images,
            'is_digital' => $request->boolean('is_digital'),
            'digital_file_path' => $digitalFilePath,
            // Si le produit était approuvé et qu'on le modifie, il repasse en pending
            'status' => $product->status === 'approved' ? 'pending' : $product->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès.',
            'data' => $product->load(['category', 'subcategory'])
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
        if ($product->images) {
            foreach ($product->images as $image) {
                $path = str_replace('/storage/', '', $image);
                Storage::disk('public')->delete($path);
            }
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