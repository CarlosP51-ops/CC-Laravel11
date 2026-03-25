<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Seller;
use App\Models\Product;
use App\Models\Withdrawal;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    // ─── VUE D'ENSEMBLE ──────────────────────────────────────────────────────

    public function overview(Request $request)
    {
        $range = (int) $request->input('range', 30);
        $from  = now()->subDays($range);
        $prev  = now()->subDays($range * 2);

        $revenueCurrent  = (float) Order::where('payment_status', 'paid')->where('created_at', '>=', $from)->sum('total_amount');
        $revenuePrevious = (float) Order::where('payment_status', 'paid')->whereBetween('created_at', [$prev, $from])->sum('total_amount');

        $ordersCurrent  = Order::where('created_at', '>=', $from)->count();
        $ordersPrevious = Order::whereBetween('created_at', [$prev, $from])->count();

        $clientsCurrent  = User::where('role', 'client')->where('created_at', '>=', $from)->count();
        $clientsPrevious = User::where('role', 'client')->whereBetween('created_at', [$prev, $from])->count();

        // Vendeurs actifs = ont au moins 1 commande sur la période (via seller_id direct)
        $activeVendors = Order::where('created_at', '>=', $from)
            ->whereNotNull('seller_id')
            ->distinct('seller_id')
            ->count('seller_id');

        $avgOrder     = (float) (Order::where('payment_status', 'paid')->where('created_at', '>=', $from)->avg('total_amount') ?? 0);
        $avgOrderPrev = (float) (Order::where('payment_status', 'paid')->whereBetween('created_at', [$prev, $from])->avg('total_amount') ?? 0);

        // Évolution CA sur 12 mois — 1 seule requête SQL au lieu de 12
        $revenueRaw = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as value")
            ->groupBy('month')
            ->pluck('value', 'month');

        $revenueChart = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key   = $month->format('Y-m');
            $revenueChart[] = [
                'label' => $month->format('M'),
                'value' => round((float) ($revenueRaw[$key] ?? 0), 2),
            ];
        }

        // Répartition commandes par statut (cast enum → string)
        $ordersByStatus = Order::select(DB::raw('status, COUNT(*) as count'))
            ->where('created_at', '>=', $from)
            ->groupBy('status')
            ->get()
            ->map(fn($row) => [
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                'count'  => $row->count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'revenue'          => ['current' => round($revenueCurrent, 2), 'previous' => round($revenuePrevious, 2), 'growth' => $this->growth($revenueCurrent, $revenuePrevious)],
                'orders'           => ['current' => $ordersCurrent, 'previous' => $ordersPrevious, 'growth' => $this->growth($ordersCurrent, $ordersPrevious)],
                'clients'          => ['current' => $clientsCurrent, 'previous' => $clientsPrevious, 'growth' => $this->growth($clientsCurrent, $clientsPrevious)],
                'active_vendors'   => $activeVendors,
                'avg_order'        => ['current' => round($avgOrder, 2), 'previous' => round($avgOrderPrev, 2), 'growth' => $this->growth($avgOrder, $avgOrderPrev)],
                'revenue_chart'    => $revenueChart,
                'orders_by_status' => $ordersByStatus,
            ]
        ]);
    }

    // ─── VENDEURS ─────────────────────────────────────────────────────────────

    public function sellers(Request $request)
    {
        $range = (int) $request->input('range', 30);
        $from  = now()->subDays($range);

        // Top vendeurs : agrégation SQL directe, pas de boucle PHP
        $topSellers = Seller::with('user:id,fullname,email')
            ->withCount('products')
            ->withSum(['orders as revenue' => fn($q) =>
                $q->where('payment_status', 'paid')->where('created_at', '>=', $from)
            ], 'total_amount')
            ->withCount(['orders as orders_count' => fn($q) => $q->where('created_at', '>=', $from)])
            ->withAvg(['reviews as avg_rating' => fn($q) =>
                $q->whereHas('product', fn($p) => $p->whereColumn('seller_id', 'sellers.id'))
            ], 'rating')
            ->orderByDesc('revenue')
            ->take(10)
            ->get()
            ->map(fn($seller) => [
                'id'             => $seller->id,
                'name'           => $seller->store_name,
                'email'          => $seller->user?->email ?? '',
                'avatar'         => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $seller->slug,
                'revenue'        => round((float) ($seller->revenue ?? 0), 2),
                'orders_count'   => $seller->orders_count ?? 0,
                'products_count' => $seller->products_count ?? 0,
                'avg_rating'     => round((float) ($seller->avg_rating ?? 0), 1),
                'is_verified'    => $seller->is_verified,
            ])
            ->values();

        $newSellers = Seller::with('user:id,fullname,email')
            ->where('created_at', '>=', $from)
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->store_name,
                'email'       => $s->user?->email ?? '',
                'is_verified' => $s->is_verified,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'top_sellers'      => $topSellers,
                'new_sellers'      => $newSellers,
                'total_sellers'    => Seller::count(),
                'verified_sellers' => Seller::where('is_verified', true)->count(),
                'active_sellers'   => Seller::where('is_active', true)->count(),
            ]
        ]);
    }

    // ─── PRODUITS ─────────────────────────────────────────────────────────────

    public function products(Request $request)
    {
        $range = (int) $request->input('range', 30);
        $from  = now()->subDays($range);

        $topProducts = Product::with(['seller:id,store_name', 'category:id,name'])
            ->withCount(['orderItems as sales_count' => fn($q) =>
                $q->whereHas('order', fn($o) =>
                    $o->where('payment_status', 'paid')->where('created_at', '>=', $from)
                )
            ])
            ->orderByDesc('sales_count')
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'seller_name' => $p->seller?->store_name ?? '—',
                'category'    => $p->category?->name ?? '—',
                'price'       => (float) $p->price,
                'sales_count' => $p->sales_count,
                'revenue'     => round($p->sales_count * (float) $p->price, 2),
                'status'      => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
            ]);

        $pendingProducts = Product::with('seller:id,store_name')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'seller_name' => $p->seller?->store_name ?? '—',
                'price'       => (float) $p->price,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'top_products'     => $topProducts,
                'pending_products' => $pendingProducts,
                'total_products'   => Product::count(),
                'active_products'  => Product::where('is_active', true)->count(),
                'pending_count'    => Product::where('status', 'pending')->count(),
            ]
        ]);
    }

    // ─── ALERTES ─────────────────────────────────────────────────────────────

    public function alerts()
    {
        $pendingWithdrawals = Withdrawal::with('seller.user')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn($w) => [
                'id'          => $w->id,
                'reference'   => $w->reference,
                'seller_name' => $w->seller?->store_name ?? '—',
                'amount'      => (float) $w->amount,
                'created_at'  => $w->created_at->diffForHumans(),
            ]);

        $flaggedProducts = Product::with('seller:id,store_name')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subHours(24))
            ->orderBy('created_at')
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'seller_name' => $p->seller?->store_name ?? '—',
                'hours_ago'   => now()->diffInHours($p->created_at),
            ]);

        // Vendeurs inactifs = is_active mais aucune commande depuis 30 jours
        $inactiveSellers = Seller::with('user:id,fullname,email')
            ->where('is_active', true)
            ->whereNotIn('id', function ($q) {
                $q->select('seller_id')
                  ->from('orders')
                  ->where('created_at', '>=', now()->subDays(30))
                  ->whereNotNull('seller_id');
            })
            ->take(8)
            ->get()
            ->map(fn($s) => [
                'id'    => $s->id,
                'name'  => $s->store_name,
                'email' => $s->user?->email ?? '',
            ]);

        $badReviews = Review::with(['product:id,name', 'user:id,fullname'])
            ->where('rating', '<=', 2)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'id'      => $r->id,
                'product' => $r->product?->name ?? '—',
                'user'    => $r->user?->fullname ?? '—',
                'rating'  => $r->rating,
                'comment' => $r->comment,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'pending_withdrawals' => $pendingWithdrawals,
                'flagged_products'    => $flaggedProducts,
                'inactive_sellers'    => $inactiveSellers,
                'bad_reviews'         => $badReviews,
                'counts' => [
                    'withdrawals' => $pendingWithdrawals->count(),
                    'products'    => $flaggedProducts->count(),
                    'sellers'     => $inactiveSellers->count(),
                    'reviews'     => $badReviews->count(),
                ],
            ]
        ]);
    }

    // ─── HELPER ──────────────────────────────────────────────────────────────

    private function growth(float $current, float $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100.0 : 0.0;
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
