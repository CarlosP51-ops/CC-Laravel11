<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['seller', 'category'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        // Recherche
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhereHas('seller', fn($s) => $s->where('store_name', 'like', "%$search%"));
            });
        }

        // Filtre statut
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                if ($status === 'active')   $query->where('is_active', true);
                if ($status === 'inactive') $query->where('is_active', false);
                if ($status === 'pending')  $query->where('status', 'pending');
                if ($status === 'approved') $query->where('status', 'approved');
                if ($status === 'rejected') $query->where('status', 'rejected');
            }
        }

        // Filtre catégorie
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Filtre vendeur
        if ($sellerId = $request->input('seller_id')) {
            $query->where('seller_id', $sellerId);
        }

        // Filtre prix
        if ($minPrice = $request->input('min_price')) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        $perPage = (int) $request->input('per_page', 10);
        $products = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'products'   => $products->map(fn($p) => $this->formatProduct($p)),
                'pagination' => [
                    'total'        => $products->total(),
                    'per_page'     => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page'    => $products->lastPage(),
                ],
            ],
        ]);
    }

    public function stats()
    {
        $total    = Product::count();
        $active   = Product::where('is_active', true)->count();
        $inactive = Product::where('is_active', false)->count();
        $pending  = Product::where('status', 'pending')->count();
        $approved = Product::where('status', 'approved')->count();
        $rejected = Product::where('status', 'rejected')->count();

        $totalRevenue = (float) OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->sum('order_items.total_price');

        $totalSales = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->sum('order_items.quantity');

        $newThisWeek = Product::where('created_at', '>=', Carbon::now()->subWeek())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total'        => $total,
                'active'       => $active,
                'inactive'     => $inactive,
                'pending'      => $pending,
                'approved'     => $approved,
                'rejected'     => $rejected,
                'totalRevenue' => $totalRevenue,
                'totalSales'   => (int) $totalSales,
                'newThisWeek'  => $newThisWeek,
            ],
        ]);
    }

    public function show($id)
    {
        $product = Product::with(['seller', 'category', 'reviews.user'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->findOrFail($id);

        $totalSales = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->where('order_items.product_id', $product->id)
            ->sum('order_items.quantity');

        $totalRevenue = (float) OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->where('order_items.product_id', $product->id)
            ->sum('order_items.total_price');

        $data = $this->formatProduct($product);
        $data['total_sales']   = (int) $totalSales;
        $data['total_revenue'] = $totalRevenue;
        $data['reviews']       = $product->reviews->take(5)->map(fn($r) => [
            'id'         => $r->id,
            'rating'     => $r->rating,
            'comment'    => $r->comment,
            'user'       => $r->user?->fullname,
            'created_at' => $r->created_at->toIso8601String(),
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function moderate(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        $product->status    = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $product->is_active = $validated['action'] === 'approve';
        $product->save();

        // Notifier les abonnés du vendeur si le produit est approuvé
        if ($validated['action'] === 'approve' && $product->seller) {
            \App\Services\NotificationService::notifyNewProduct($product, $product->seller);
        }

        return response()->json([
            'success' => true,
            'message' => $validated['action'] === 'approve' ? 'Produit approuvé.' : 'Produit rejeté.',
            'data'    => $this->formatProduct($product->load(['seller', 'category'])),
        ]);
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => $product->is_active ? 'Produit activé.' : 'Produit désactivé.',
            'data'    => $this->formatProduct($product->load(['seller', 'category'])),
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Produit supprimé.']);
    }

    public function bulkModerate(Request $request)
    {
        $validated = $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
            'action' => 'required|in:approve,reject',
        ]);

        $status    = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $isActive  = $validated['action'] === 'approve';

        Product::whereIn('id', $validated['ids'])->update([
            'status'    => $status,
            'is_active' => $isActive,
        ]);

        return response()->json(['success' => true, 'message' => count($validated['ids']) . ' produit(s) traité(s).']);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        Product::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => count($ids) . ' produit(s) supprimé(s).']);
    }

    public function export()
    {
        $products = Product::with(['seller', 'category'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => $this->formatProduct($p));

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function categories()
    {
        $cats = Category::whereNull('parent_id')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'slug']);

        return response()->json(['success' => true, 'data' => $cats]);
    }

    // -------------------------------------------------------------------------

    private function formatProduct(Product $product): array
    {
        // images est une relation HasMany vers ProductImage, pas un cast
        $thumb = null;
        try {
            $primaryImage = $product->relationLoaded('productImages')
                ? $product->productImages->first()?->image_path
                : null;
            if ($primaryImage) {
                $thumb = str_starts_with($primaryImage, 'http')
                    ? $primaryImage
                    : "http://localhost:8000/storage/{$primaryImage}";
            }
        } catch (\Throwable $e) {
            $thumb = null;
        }

        return [
            'id'            => $product->id,
            'name'          => $product->name,
            'slug'          => $product->slug,
            'description'   => $product->short_description ?? $product->description,
            'price'         => (float) $product->price,
            'compare_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'sku'           => $product->sku,
            'stock'         => $product->stock_quantity ?? 0,
            'is_active'     => (bool) $product->is_active,
            'is_promoted'   => (bool) ($product->is_promoted ?? false),
            'is_digital'    => (bool) ($product->is_digital ?? false),
            'status'        => $product->status ?? 'pending',
            'thumbnail'     => $thumb,
            'category'      => $product->category?->name,
            'category_id'   => $product->category_id,
            'seller'        => $product->seller?->store_name,
            'seller_id'     => $product->seller_id,
            'rating'        => round((float) ($product->reviews_avg_rating ?? 0), 1),
            'reviews_count' => $product->reviews_count ?? 0,
            'created_at'    => $product->created_at->toIso8601String(),
            'updated_at'    => $product->updated_at->toIso8601String(),
        ];
    }
}
