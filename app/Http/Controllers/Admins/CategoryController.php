<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    // ─── LIST ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $query = Category::withCount('products')
                ->with('parent:id,name')
                ->orderBy('order')
                ->orderBy('name');

            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('slug', 'like', "%$search%")
                      ->orWhere('description', 'like', "%$search%");
                });
            }

            if ($request->status && $request->status !== 'all') {
                $query->where('is_active', $request->status === 'active');
            }

            if ($request->parent_id !== null) {
                $query->where('parent_id', $request->parent_id ?: null);
            }

            $categories = $query->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $categories->map(fn($c) => $this->formatCategory($c)),
                'meta' => [
                    'total'        => $categories->total(),
                    'per_page'     => $categories->perPage(),
                    'current_page' => $categories->currentPage(),
                    'last_page'    => $categories->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── STATS ────────────────────────────────────────────────────────────────

    public function stats()
    {
        try {
            $total = Category::count();
            $active = Category::where('is_active', true)->count();
            $inactive = Category::where('is_active', false)->count();
            $roots = Category::whereNull('parent_id')->count();
            $withProducts = Category::has('products')->count();

            $topCategory = Category::withCount('products')
                ->orderByDesc('products_count')
                ->first();

            $totalProducts = DB::table('products')->count();
            $avgPerCategory = $total > 0 ? round($totalProducts / $total) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_categories'               => $total,
                    'active_categories'              => $active,
                    'inactive_categories'            => $inactive,
                    'root_categories'                => $roots,
                    'with_products'                  => $withProducts,
                    'total_products'                 => $totalProducts,
                    'average_products_per_category'  => $avgPerCategory,
                    'top_category'                   => $topCategory?->name ?? '—',
                    'top_category_products'          => $topCategory?->products_count ?? 0,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── SHOW ─────────────────────────────────────────────────────────────────

    public function show($id)
    {
        $category = Category::withCount('products')
            ->with(['parent:id,name', 'children:id,name,slug,is_active'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatCategory($category)
        ]);
    }

    // ─── STORE ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'is_active'   => 'boolean',
            'order'       => 'integer',
        ]);

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $i = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $i++;
        }

        $category = Category::create([
            'name'        => $request->name,
            'slug'        => $slug,
            'description' => $request->description,
            'parent_id'   => $request->parent_id,
            'is_active'   => $request->is_active ?? true,
            'order'       => $request->order ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créée',
            'data' => $this->formatCategory($category->load('parent:id,name')->loadCount('products'))
        ], 201);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'is_active'   => 'boolean',
            'order'       => 'integer',
        ]);

        // Empêcher une catégorie d'être son propre parent
        if ($request->parent_id == $id) {
            return response()->json(['success' => false, 'message' => 'Une catégorie ne peut pas être son propre parent'], 422);
        }

        $updateData = $request->only(['name', 'description', 'parent_id', 'is_active', 'order']);

        if (isset($updateData['name']) && $updateData['name'] !== $category->name) {
            $slug = Str::slug($updateData['name']);
            $originalSlug = $slug;
            $i = 1;
            while (Category::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $i++;
            }
            $updateData['slug'] = $slug;
        }

        $category->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie mise à jour',
            'data' => $this->formatCategory($category->fresh(['parent:id,name'])->loadCount('products'))
        ]);
    }

    // ─── TOGGLE STATUS ────────────────────────────────────────────────────────

    public function toggleStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);

        return response()->json([
            'success' => true,
            'message' => $category->is_active ? 'Catégorie activée' : 'Catégorie désactivée',
            'data' => ['is_active' => $category->is_active]
        ]);
    }

    // ─── DELETE ───────────────────────────────────────────────────────────────

    public function destroy($id)
    {
        $category = Category::withCount(['products', 'children'])->findOrFail($id);

        if ($category->products_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de supprimer : {$category->products_count} produit(s) associé(s)"
            ], 422);
        }

        if ($category->children_count > 0) {
            // Détacher les enfants (remonter au niveau racine)
            Category::where('parent_id', $id)->update(['parent_id' => $category->parent_id]);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Catégorie supprimée']);
    }

    // ─── TREE (hiérarchie complète) ───────────────────────────────────────────

    public function tree()
    {
        try {
            $categories = Category::withCount('products')
                ->orderBy('order')
                ->orderBy('name')
                ->get();

            $tree = $this->buildTree($categories);

            return response()->json(['success' => true, 'data' => $tree]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function formatCategory($c): array
    {
        return [
            'id'             => $c->id,
            'name'           => $c->name,
            'slug'           => $c->slug,
            'description'    => $c->description ?? '',
            'parent_id'      => $c->parent_id,
            'parent_name'    => $c->parent?->name ?? null,
            'order'          => $c->order,
            'is_active'      => $c->is_active,
            'products_count' => $c->products_count ?? 0,
            'children'       => $c->children?->map(fn($ch) => ['id' => $ch->id, 'name' => $ch->name, 'slug' => $ch->slug, 'is_active' => $ch->is_active]) ?? [],
            'created_at'     => $c->created_at,
            'updated_at'     => $c->updated_at,
        ];
    }

    private function buildTree($categories, $parentId = null): array
    {
        return $categories
            ->where('parent_id', $parentId)
            ->map(fn($c) => array_merge($this->formatCategory($c), [
                'children' => $this->buildTree($categories, $c->id)
            ]))
            ->values()
            ->toArray();
    }
}
