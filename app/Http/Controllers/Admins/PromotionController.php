<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    // ─── STATS ───────────────────────────────────────────────────────────────

    public function stats()
    {
        $promoted = Product::where('is_promoted', true)->where('is_active', true)
            ->whereNotNull('compare_at_price')->where('compare_at_price', '>', DB::raw('price'));

        $totalPromoted  = (clone $promoted)->count();
        $totalProducts  = Product::count();

        $avgDiscount = (clone $promoted)->get()->avg(function ($p) {
            return $p->compare_at_price > 0
                ? (($p->compare_at_price - $p->price) / $p->compare_at_price) * 100
                : 0;
        }) ?? 0;

        $maxDiscount = (clone $promoted)->get()->max(function ($p) {
            return $p->compare_at_price > 0
                ? (($p->compare_at_price - $p->price) / $p->compare_at_price) * 100
                : 0;
        }) ?? 0;

        $totalSavingsOffered = (clone $promoted)->get()->sum(function ($p) {
            return max(0, $p->compare_at_price - $p->price);
        });

        // Revenus générés par les produits promus (commandes payées)
        $revenueFromPromoted = Order::where('payment_status', 'paid')
            ->whereHas('items.product', fn($q) => $q->where('is_promoted', true))
            ->sum('total_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_promoted'       => $totalPromoted,
                'total_products'       => $totalProducts,
                'avg_discount'         => round($avgDiscount, 1),
                'max_discount'         => round($maxDiscount, 1),
                'total_savings_offered'=> round($totalSavingsOffered, 2),
                'revenue_from_promoted'=> round((float) $revenueFromPromoted, 2),
            ]
        ]);
    }

    // ─── LISTE ───────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $search   = $request->input('search', '');
        $filter   = $request->input('filter', 'all'); // all | promoted | not_promoted
        $perPage  = (int) $request->input('per_page', 15);

        $query = Product::with(['seller:id,store_name', 'category:id,name'])
            ->orderByDesc('is_promoted')
            ->orderByDesc('updated_at');

        if ($search) {
            $query->where(fn($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('sku', 'like', "%$search%"));
        }

        if ($filter === 'promoted') {
            $query->where('is_promoted', true);
        } elseif ($filter === 'not_promoted') {
            $query->where('is_promoted', false);
        }

        $products = $query->paginate($perPage);

        $items = $products->map(function ($p) {
            $discount = ($p->compare_at_price && $p->compare_at_price > $p->price)
                ? round((($p->compare_at_price - $p->price) / $p->compare_at_price) * 100)
                : 0;

            return [
                'id'               => $p->id,
                'name'             => $p->name,
                'sku'              => $p->sku,
                'price'            => (float) $p->price,
                'compare_at_price' => $p->compare_at_price ? (float) $p->compare_at_price : null,
                'discount'         => $discount,
                'savings'          => $p->compare_at_price ? round(max(0, $p->compare_at_price - $p->price), 2) : 0,
                'is_promoted'      => (bool) $p->is_promoted,
                'is_active'        => (bool) $p->is_active,
                'status'           => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
                'seller'           => $p->seller?->store_name ?? '—',
                'category'         => $p->category?->name ?? '—',
                'stock'            => $p->stock_quantity ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'products'   => $items,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page'    => $products->lastPage(),
                    'total'        => $products->total(),
                    'per_page'     => $products->perPage(),
                ],
            ]
        ]);
    }

    // ─── TOGGLE PROMOTION ────────────────────────────────────────────────────

    public function toggle(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $product->is_promoted = !$product->is_promoted;
        $product->save();

        return response()->json([
            'success' => true,
            'data'    => ['is_promoted' => $product->is_promoted],
            'message' => $product->is_promoted ? 'Produit mis en promotion' : 'Promotion retirée',
        ]);
    }

    // ─── MISE À JOUR PRIX BARRÉ ──────────────────────────────────────────────

    public function updatePrice(Request $request, int $id)
    {
        $request->validate([
            'compare_at_price' => 'nullable|numeric|min:0',
            'is_promoted'      => 'boolean',
        ]);

        $product = Product::findOrFail($id);
        $product->compare_at_price = $request->compare_at_price;
        if ($request->has('is_promoted')) {
            $product->is_promoted = $request->is_promoted;
        }
        $product->save();

        $discount = ($product->compare_at_price && $product->compare_at_price > $product->price)
            ? round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100)
            : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'compare_at_price' => $product->compare_at_price,
                'discount'         => $discount,
                'is_promoted'      => $product->is_promoted,
            ],
            'message' => 'Prix mis à jour',
        ]);
    }

    // ─── BULK TOGGLE ─────────────────────────────────────────────────────────

    public function bulkToggle(Request $request)
    {
        $request->validate([
            'ids'        => 'required|array',
            'is_promoted'=> 'required|boolean',
        ]);

        Product::whereIn('id', $request->ids)->update(['is_promoted' => $request->is_promoted]);

        return response()->json([
            'success' => true,
            'message' => count($request->ids) . ' produits mis à jour',
        ]);
    }
}
