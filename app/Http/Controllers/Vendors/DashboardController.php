<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Récupérer les statistiques du dashboard vendeur
     */
    public function getStats(Request $request)
    {
        $user = Auth::user();
        $seller = $user->seller;
        $timeRange = $request->input('time_range', 'month');
        
        // Définir les dates selon la période
        $dates = $this->getDateRange($timeRange);
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        $previousStartDate = $dates['previous_start'];
        $previousEndDate = $dates['previous_end'];

        // Statistiques actuelles
        $currentStats = $this->getStatsForPeriod($seller->id, $startDate, $endDate);
        
        // Statistiques période précédente pour calculer les changements
        $previousStats = $this->getStatsForPeriod($seller->id, $previousStartDate, $previousEndDate);

        // Calculer les pourcentages de changement
        $revenueChange = $this->calculatePercentageChange($previousStats['revenue'], $currentStats['revenue']);
        $ordersChange = $this->calculatePercentageChange($previousStats['orders'], $currentStats['orders']);
        $customersChange = $this->calculatePercentageChange($previousStats['customers'], $currentStats['customers']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => round($currentStats['revenue'], 2),
                'total_orders' => $currentStats['orders'],
                'total_products' => $currentStats['products'],
                'total_customers' => $currentStats['customers'],
                'revenue_change' => round($revenueChange, 1),
                'orders_change' => round($ordersChange, 1),
                'customers_change' => round($customersChange, 1),
                'period' => $timeRange,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ]
            ]
        ]);
    }

    /**
     * Récupérer les données du graphique de revenus
     */
    public function getRevenueChart(Request $request)
    {
        $user = $request->user();
        $seller = $user->seller;
        $timeRange = $request->input('time_range', 'month');

        $dates = $this->getDateRange($timeRange);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // Grouper par jour, semaine ou mois selon la période
        $groupBy = $this->getGroupByFormat($timeRange);
        
        $revenueData = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('products.seller_id', $seller->id)
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->select(
                DB::raw($groupBy . ' as period'),
                DB::raw('SUM(order_items.price * order_items.quantity) as revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as orders_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revenueData
        ]);
    }

    /**
     * Récupérer les commandes récentes
     */
    public function getRecentOrders(Request $request)
    {
        $user = $request->user();
        $seller = $user->seller;
        $limit = $request->input('limit', 10);

        $orders = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('products.seller_id', $seller->id)
            ->with(['user', 'items.product'])
            ->select('orders.*')
            ->distinct()
            ->orderBy('orders.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($order) use ($seller) {
                // Calculer le total pour ce vendeur seulement
                $vendorTotal = $order->items
                    ->where('product.seller_id', $seller->id)
                    ->sum(function ($item) {
                        return $item->price * $item->quantity;
                    });

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user->fullname ?? 'Client',
                    'customer_email' => $order->user->email ?? '',
                    'total' => round($vendorTotal, 2),
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'items_count' => $order->items->where('product.seller_id', $seller->id)->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Récupérer les produits les plus vendus
     */
    public function getTopProducts(Request $request)
    {
        $user = $request->user();
        $seller = $user->seller;
        $limit = $request->input('limit', 5);
        $timeRange = $request->input('time_range', 'month');

        $dates = $this->getDateRange($timeRange);

        $topProducts = Product::where('seller_id', $seller->id)
            ->withCount(['orderItems as sales_count' => function ($query) use ($dates) {
                $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.payment_status', 'paid')
                    ->whereBetween('orders.created_at', [$dates['start'], $dates['end']]);
            }])
            ->with(['category'])
            ->having('sales_count', '>', 0)
            ->orderBy('sales_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'category' => $product->category->name ?? 'Non catégorisé',
                    'sales_count' => $product->sales_count,
                    'image' => $product->images[0] ?? null,
                    'stock' => $product->stock,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topProducts
        ]);
    }

    /**
     * Récupérer les statistiques pour une période donnée
     */
    private function getStatsForPeriod($sellerId, $startDate, $endDate)
    {
        // Revenus
        $revenue = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('products.seller_id', $sellerId)
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->sum(DB::raw('order_items.price * order_items.quantity'));

        // Nombre de commandes
        $orders = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('products.seller_id', $sellerId)
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->distinct('orders.id')
            ->count('orders.id');

        // Nombre de produits
        $products = Product::where('seller_id', $sellerId)->count();

        // Nombre de clients uniques
        $customers = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('products.seller_id', $sellerId)
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->distinct('orders.user_id')
            ->count('orders.user_id');

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'products' => $products,
            'customers' => $customers,
        ];
    }

    /**
     * Définir les plages de dates selon la période
     */
    private function getDateRange($timeRange)
    {
        $now = Carbon::now();
        
        switch ($timeRange) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $previousStart = $now->copy()->subDay()->startOfDay();
                $previousEnd = $now->copy()->subDay()->endOfDay();
                break;
                
            case 'week':
                $start = $now->copy()->subDays(7);
                $end = $now->copy();
                $previousStart = $now->copy()->subDays(14);
                $previousEnd = $now->copy()->subDays(7);
                break;
                
            case 'quarter':
                $start = $now->copy()->subDays(90);
                $end = $now->copy();
                $previousStart = $now->copy()->subDays(180);
                $previousEnd = $now->copy()->subDays(90);
                break;
                
            case 'year':
                $start = $now->copy()->subYear();
                $end = $now->copy();
                $previousStart = $now->copy()->subYears(2);
                $previousEnd = $now->copy()->subYear();
                break;
                
            default: // month
                $start = $now->copy()->subDays(30);
                $end = $now->copy();
                $previousStart = $now->copy()->subDays(60);
                $previousEnd = $now->copy()->subDays(30);
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
        ];
    }

    /**
     * Calculer le pourcentage de changement
     */
    private function calculatePercentageChange($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Obtenir le format de groupement pour les graphiques
     */
    private function getGroupByFormat($timeRange)
    {
        switch ($timeRange) {
            case 'today':
                return "DATE_FORMAT(orders.created_at, '%H:00')";
            case 'week':
                return "DATE(orders.created_at)";
            case 'month':
                return "DATE(orders.created_at)";
            case 'quarter':
            case 'year':
                return "DATE_FORMAT(orders.created_at, '%Y-%m')";
            default:
                return "DATE(orders.created_at)";
        }
    }
}