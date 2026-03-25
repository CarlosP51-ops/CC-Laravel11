<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // ─── STATS GLOBALES ───────────────────────────────────────────────────────
    public function stats()
    {
        $now        = now();
        $startMonth = $now->copy()->startOfMonth();
        $lastMonth  = $now->copy()->subMonth()->startOfMonth();
        $endLast    = $now->copy()->subMonth()->endOfMonth();

        $revenueMonth = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startMonth, $now])
            ->sum('total_amount');

        $revenueLast = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonth, $endLast])
            ->sum('total_amount');

        $ordersMonth = Order::whereBetween('created_at', [$startMonth, $now])->count();
        $ordersLast  = Order::whereBetween('created_at', [$lastMonth, $endLast])->count();

        $newUsersMonth = User::whereBetween('created_at', [$startMonth, $now])->count();
        $newUsersLast  = User::whereBetween('created_at', [$lastMonth, $endLast])->count();

        $totalRevenue  = Order::where('payment_status', 'paid')->sum('total_amount');
        $totalOrders   = Order::count();
        $totalUsers    = User::count();
        $totalVendors  = User::where('role', 'vendor')->count();
        $totalProducts = Product::count();

        $avgOrder = Order::where('payment_status', 'paid')->count() > 0
            ? Order::where('payment_status', 'paid')->avg('total_amount')
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue'     => round($totalRevenue, 2),
                'total_orders'      => $totalOrders,
                'total_users'       => $totalUsers,
                'total_vendors'     => $totalVendors,
                'total_products'    => $totalProducts,
                'avg_order_value'   => round($avgOrder, 2),
                'revenue_month'     => round($revenueMonth, 2),
                'revenue_last'      => round($revenueLast, 2),
                'revenue_growth'    => $revenueLast > 0 ? round((($revenueMonth - $revenueLast) / $revenueLast) * 100, 1) : 0,
                'orders_month'      => $ordersMonth,
                'orders_last'       => $ordersLast,
                'orders_growth'     => $ordersLast > 0 ? round((($ordersMonth - $ordersLast) / $ordersLast) * 100, 1) : 0,
                'new_users_month'   => $newUsersMonth,
                'new_users_last'    => $newUsersLast,
                'users_growth'      => $newUsersLast > 0 ? round((($newUsersMonth - $newUsersLast) / $newUsersLast) * 100, 1) : 0,
            ],
        ]);
    }

    // ─── GRAPHIQUE REVENUS / COMMANDES SUR 12 MOIS ───────────────────────────
    public function overview()
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        $revenueRaw = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $ordersRaw = Order::where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $labels   = [];
        $revenues = [];
        $orders   = [];

        foreach ($months as $m) {
            $dt = \Carbon\Carbon::createFromFormat('Y-m', $m);
            $labels[]   = $dt->locale('fr')->isoFormat('MMM YY');
            $revenues[] = round($revenueRaw[$m] ?? 0, 2);
            $orders[]   = (int)($ordersRaw[$m] ?? 0);
        }

        return response()->json([
            'success' => true,
            'data'    => compact('labels', 'revenues', 'orders'),
        ]);
    }

    // ─── RAPPORT VENTES ───────────────────────────────────────────────────────
    public function sales(Request $request)
    {
        $period = $request->input('period', 'month');
        $start  = $this->periodStart($period);

        // Top produits (on utilise total_price qui est déjà qty * unit_price)
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.created_at', '>=', $start)
            ->selectRaw('products.id, products.name, SUM(order_items.quantity) as qty, SUM(order_items.total_price) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Top vendeurs (products.seller_id → sellers.user_id → users)
        $topVendors = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('sellers', 'products.seller_id', '=', 'sellers.id')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->where('orders.created_at', '>=', $start)
            ->where('orders.payment_status', 'paid')
            ->selectRaw('users.id, users.fullname, COUNT(DISTINCT orders.id) as orders, SUM(order_items.total_price) as revenue')
            ->groupBy('users.id', 'users.fullname')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Répartition par statut
        $byStatus = Order::where('created_at', '>=', $start)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Répartition par catégorie
        $byCategory = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.created_at', '>=', $start)
            ->selectRaw('categories.name, SUM(order_items.total_price) as revenue')
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => compact('topProducts', 'topVendors', 'byStatus', 'byCategory'),
        ]);
    }

    // ─── RAPPORT UTILISATEURS ─────────────────────────────────────────────────
    public function users(Request $request)
    {
        $period = $request->input('period', 'month');
        $start  = $this->periodStart($period);

        // Croissance sur 12 mois
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        $newRaw = User::where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $labels   = [];
        $newUsers = [];

        foreach ($months as $m) {
            $dt       = \Carbon\Carbon::createFromFormat('Y-m', $m);
            $labels[] = $dt->locale('fr')->isoFormat('MMM YY');
            $newUsers[] = (int)($newRaw[$m] ?? 0);
        }

        // Répartition par rôle
        $byRole = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        // Nouveaux cette période
        $newThisPeriod = User::where('created_at', '>=', $start)->count();

        // Top clients (par montant dépensé)
        $topClients = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', $start)
            ->selectRaw('users.id, users.fullname, users.email, COUNT(orders.id) as orders, SUM(orders.total_amount) as spent')
            ->groupBy('users.id', 'users.fullname', 'users.email')
            ->orderByDesc('spent')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => compact('labels', 'newUsers', 'byRole', 'newThisPeriod', 'topClients'),
        ]);
    }

    // ─── EXPORT CSV ───────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        $type   = $request->input('type', 'sales');
        $period = $request->input('period', 'month');
        $start  = $this->periodStart($period);

        // Sanitise les valeurs pour éviter l'injection CSV (formula injection)
        $sanitize = fn($v) => preg_replace('/^[=+\-@\t\r]/', "'$0", (string) $v);

        if ($type === 'sales') {
            $rows = Order::with(['user:id,fullname,email'])
                ->where('created_at', '>=', $start)
                ->orderByDesc('created_at')
                ->get(['id', 'user_id', 'status', 'total_amount', 'created_at']);

            $csv = "ID,Client,Email,Statut,Montant,Date\n";
            foreach ($rows as $r) {
                $csv .= implode(',', [
                    $r->id,
                    '"' . $sanitize($r->user->fullname ?? '') . '"',
                    '"' . $sanitize($r->user->email ?? '') . '"',
                    $sanitize($r->status),
                    $r->total_amount,
                    $r->created_at->format('d/m/Y H:i'),
                ]) . "\n";
            }
            $filename = 'rapport_ventes_' . now()->format('Ymd') . '.csv';
        } else {
            $rows = User::where('created_at', '>=', $start)
                ->orderByDesc('created_at')
                ->get(['id', 'fullname', 'email', 'role', 'created_at']);

            $csv = "ID,Nom,Email,Rôle,Inscription\n";
            foreach ($rows as $r) {
                $csv .= implode(',', [
                    $r->id,
                    '"' . $sanitize($r->fullname) . '"',
                    '"' . $sanitize($r->email) . '"',
                    $sanitize($r->role),
                    $r->created_at->format('d/m/Y H:i'),
                ]) . "\n";
            }
            $filename = 'rapport_utilisateurs_' . now()->format('Ymd') . '.csv';
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    // ─── HELPER ───────────────────────────────────────────────────────────────
    private function periodStart(string $period): \Carbon\Carbon
    {
        return match ($period) {
            'week'    => now()->subWeek(),
            'month'   => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            'year'    => now()->subYear(),
            default   => now()->subMonth(),
        };
    }
}
