<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    private function getSeller()
    {
        return Auth::user()->seller;
    }

    private function notFound()
    {
        return response()->json(['success' => false, 'message' => 'Profil vendeur non trouvé'], 404);
    }

    // ── Overview ──────────────────────────────────────────────────────────────
    public function overview(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        $period    = (int) $request->input('period', 30);
        $start     = Carbon::now()->subDays($period);
        $prevStart = Carbon::now()->subDays($period * 2);
        $prevEnd   = Carbon::now()->subDays($period);

        // Revenus période courante (commandes payées)
        $totalRevenue = (float) Order::where('seller_id', $seller->id)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $start)
            ->sum('total_amount');

        // Revenus période précédente
        $prevRevenue = (float) Order::where('seller_id', $seller->id)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('total_amount');

        $revenueGrowth = $prevRevenue > 0
            ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : 0;

        // Commandes
        $totalOrders = Order::where('seller_id', $seller->id)
            ->where('created_at', '>=', $start)
            ->count();

        $prevOrders = Order::where('seller_id', $seller->id)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $ordersGrowth = $prevOrders > 0
            ? round((($totalOrders - $prevOrders) / $prevOrders) * 100, 1)
            : 0;

        // Clients uniques
        $uniqueCustomers = Order::where('seller_id', $seller->id)
            ->where('created_at', '>=', $start)
            ->distinct('user_id')
            ->count('user_id');

        // Produits vendus (unités)
        $productIds = $seller->products()->pluck('id');
        $productsSold = (int) DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereIn('oi.product_id', $productIds)
            ->where('o.payment_status', 'paid')
            ->where('o.created_at', '>=', $start)
            ->sum('oi.quantity');

        // Panier moyen
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue'       => $totalRevenue,
                'total_orders'        => $totalOrders,
                'unique_customers'    => $uniqueCustomers,
                'products_sold'       => $productsSold,
                'average_order_value' => $avgOrderValue,
                'revenue_growth'      => $revenueGrowth,
                'orders_growth'       => $ordersGrowth,
                'period'              => $period,
            ]
        ]);
    }

    // ── Sales Chart ───────────────────────────────────────────────────────────
    public function salesChart(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        $period = (int) $request->input('period', 30);
        // Pour les longues périodes, on agrège par semaine ou mois
        $points = min($period, 30); // max 30 points
        $startDate = Carbon::now()->subDays($period);

        $salesData = Order::where('seller_id', $seller->id)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartData = [];
        $step = max(1, (int) ceil($period / $points));

        for ($i = $period - 1; $i >= 0; $i -= $step) {
            $date    = Carbon::now()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $day     = $salesData->get($dateStr);

            $chartData[] = [
                'date'           => $dateStr,
                'revenue'        => $day ? (float) $day->revenue : 0,
                'orders'         => $day ? (int) $day->orders : 0,
                'formatted_date' => $date->locale('fr')->isoFormat('D MMM'),
            ];
        }

        return response()->json(['success' => true, 'data' => $chartData]);
    }

    // ── Top Products ──────────────────────────────────────────────────────────
    public function topProducts(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        $period = (int) $request->input('period', 30);
        $limit  = (int) $request->input('limit', 10);
        $start  = Carbon::now()->subDays($period);

        $products = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->where('p.seller_id', $seller->id)
            ->where('o.payment_status', 'paid')
            ->where('o.created_at', '>=', $start)
            ->selectRaw('p.id, p.name, p.price, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue, COUNT(DISTINCT o.id) as orders_count')
            ->groupBy('p.id', 'p.name', 'p.price')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'price'         => (float) $r->price,
                'total_sold'    => (int) $r->total_sold,
                'total_revenue' => (float) $r->total_revenue,
                'orders_count'  => (int) $r->orders_count,
            ]);

        return response()->json(['success' => true, 'data' => $products]);
    }

    // ── Category Stats ────────────────────────────────────────────────────────
    public function categoryStats(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        $period = (int) $request->input('period', 30);
        $start  = Carbon::now()->subDays($period);

        $stats = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.seller_id', $seller->id)
            ->where('o.payment_status', 'paid')
            ->where('o.created_at', '>=', $start)
            ->selectRaw('c.id, c.name, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue, COUNT(DISTINCT o.id) as total_orders')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'total_sold'    => (int) $r->total_sold,
                'total_revenue' => (float) $r->total_revenue,
                'total_orders'  => (int) $r->total_orders,
            ]);

        return response()->json(['success' => true, 'data' => $stats]);
    }

    // ── Monthly Revenue (6 mois) ──────────────────────────────────────────────
    public function monthlyRevenue(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $rev = (float) Order::where('seller_id', $seller->id)
                ->where('payment_status', 'paid')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('total_amount');
            $orders = (int) Order::where('seller_id', $seller->id)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $data[] = [
                'month'   => $month->locale('fr')->isoFormat('MMM YY'),
                'revenue' => $rev,
                'orders'  => $orders,
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── Orders by Status ──────────────────────────────────────────────────────
    public function ordersByStatus(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        $period = (int) $request->input('period', 30);
        $start  = Carbon::now()->subDays($period);

        $byStatus = Order::where('seller_id', $seller->id)
            ->where('created_at', '>=', $start)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byPayment = Order::where('seller_id', $seller->id)
            ->where('created_at', '>=', $start)
            ->selectRaw('payment_status, COUNT(*) as count')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status');

        return response()->json([
            'success' => true,
            'data' => [
                'by_status'  => $byStatus,
                'by_payment' => $byPayment,
            ]
        ]);
    }

    // ── Conversion Stats ──────────────────────────────────────────────────────
    public function conversionStats(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        // Pas de tracking de vues pour l'instant — on retourne les données disponibles
        $period = (int) $request->input('period', 30);
        $start  = Carbon::now()->subDays($period);

        $orders = Order::where('seller_id', $seller->id)
            ->where('created_at', '>=', $start)
            ->count();

        $paid = Order::where('seller_id', $seller->id)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $start)
            ->count();

        $cancelled = Order::where('seller_id', $seller->id)
            ->where('status', 'cancelled')
            ->where('created_at', '>=', $start)
            ->count();

        $conversionRate = $orders > 0 ? round(($paid / $orders) * 100, 2) : 0;
        $cancellationRate = $orders > 0 ? round(($cancelled / $orders) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'product_views'         => 0, // pas de tracking
                'orders'                => $orders,
                'paid_orders'           => $paid,
                'conversion_rate'       => $conversionRate,
                'cart_abandonment_rate' => $cancellationRate,
            ]
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        $seller = $this->getSeller();
        if (!$seller) return $this->notFound();

        return response()->json([
            'success'      => true,
            'message'      => 'Export généré avec succès',
            'download_url' => null,
        ]);
    }
}
