<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $timeRange = $request->input('time_range', 'month');
        $cacheKey  = 'admin.dashboard.stats.' . $timeRange;

        $data = Cache::remember($cacheKey, 300, function () use ($timeRange) {
            $dates     = $this->getDateRange($timeRange);
            $start     = $dates['start'];
            $end       = $dates['end'];
            $prevStart = $dates['previous_start'];
            $prevEnd   = $dates['previous_end'];

            // Users
            $totalUsers      = User::count();
            $newUsersToday   = User::whereDate('created_at', Carbon::today())->count();
            $newUsersCurrent = User::whereBetween('created_at', [$start, $end])->count();
            $newUsersPrev    = User::whereBetween('created_at', [$prevStart, $prevEnd])->count();
            $usersGrowth     = $this->percentChange($newUsersPrev, $newUsersCurrent);

            // Products
            $totalProducts      = Product::count();
            $pendingProducts    = Product::where('status', 'pending')->count();
            $activeProducts     = Product::where('status', 'active')->count();
            $newProductsCurrent = Product::whereBetween('created_at', [$start, $end])->count();
            $newProductsPrev    = Product::whereBetween('created_at', [$prevStart, $prevEnd])->count();
            $productsGrowth     = $this->percentChange($newProductsPrev, $newProductsCurrent);

            // Revenue
            $totalRevenue    = (float) Order::where('payment_status', 'paid')->sum('total_amount');
            $currentRevenue  = (float) Order::where('payment_status', 'paid')->whereBetween('created_at', [$start, $end])->sum('total_amount');
            $prevRevenue     = (float) Order::where('payment_status', 'paid')->whereBetween('created_at', [$prevStart, $prevEnd])->sum('total_amount');
            $revenueGrowth   = $this->percentChange($prevRevenue, $currentRevenue);
            $commission      = round($totalRevenue * 0.15, 2);
            $thisMonthRevenue = (float) Order::where('payment_status', 'paid')
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('total_amount');

            // Transactions
            $totalTransactions      = Order::count();
            $successfulTransactions = Order::where('payment_status', 'paid')->count();
            $failedTransactions     = Order::where('payment_status', 'failed')->count();
            $currentTransactions    = Order::whereBetween('created_at', [$start, $end])->count();
            $prevTransactions       = Order::whereBetween('created_at', [$prevStart, $prevEnd])->count();
            $transactionsGrowth     = $this->percentChange($prevTransactions, $currentTransactions);

            // Platform
            $activeSellers = Seller::where('is_active', true)->count();
            $activeBuyers  = User::where('role', 'client')
                ->whereHas('orders', function ($q) use ($start, $end) {
                    $q->whereBetween('created_at', [$start, $end]);
                })->count();

            return [
                'users' => [
                    'total'    => $totalUsers,
                    'newToday' => $newUsersToday,
                    'growth'   => round($usersGrowth, 1),
                ],
                'products' => [
                    'total'   => $totalProducts,
                    'active'  => $activeProducts,
                    'pending' => $pendingProducts,
                    'growth'  => round($productsGrowth, 1),
                ],
                'revenue' => [
                    'total'              => round($totalRevenue, 2),
                    'commission'         => $commission,
                    'thisMonth'          => round($thisMonthRevenue, 2),
                    'growth'             => round($revenueGrowth, 1),
                    'averageTransaction' => $totalTransactions > 0
                        ? round($totalRevenue / $totalTransactions, 2)
                        : 0,
                ],
                'transactions' => [
                    'total'      => $totalTransactions,
                    'successful' => $successfulTransactions,
                    'failed'     => $failedTransactions,
                    'growth'     => round($transactionsGrowth, 1),
                ],
                'platform' => [
                    'activeSellers' => $activeSellers,
                    'activeBuyers'  => $activeBuyers,
                ],
                'period' => $timeRange,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getRevenueChart(Request $request)
    {
        $timeRange = $request->input('time_range', 'month');
        $dates     = $this->getDateRange($timeRange);
        $groupBy   = $this->getGroupByFormat($timeRange);

        $data = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$dates['start'], $dates['end']])
            ->select(
                DB::raw($groupBy . ' as period'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getTopSellers(Request $request)
    {
        $timeRange = $request->input('time_range', 'month');
        $limit     = (int) $request->input('limit', 5);
        $dates     = $this->getDateRange($timeRange);

        $sellers = Seller::with('user')
            ->withSum(['orders as total_revenue' => function ($q) use ($dates) {
                $q->where('payment_status', 'paid')
                  ->whereBetween('created_at', [$dates['start'], $dates['end']]);
            }], 'total_amount')
            ->withSum(['orders as prev_revenue' => function ($q) {
                $q->where('payment_status', 'paid')
                  ->whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)]);
            }], 'total_amount')
            ->withCount(['orders as orders_count' => function ($q) use ($dates) {
                $q->whereBetween('created_at', [$dates['start'], $dates['end']]);
            }])
            ->withCount('products')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->filter(fn($s) => ($s->total_revenue ?? 0) > 0)
            ->map(function ($seller) {
                $growth = $this->percentChange(
                    (float) ($seller->prev_revenue ?? 0),
                    (float) ($seller->total_revenue ?? 0)
                );

                return [
                    'id'         => 'seller_' . $seller->id,
                    'name'       => $seller->store_name,
                    'revenue'    => round((float) ($seller->total_revenue ?? 0), 2),
                    'commission' => round((float) ($seller->total_revenue ?? 0) * 0.15, 2),
                    'products'   => $seller->products_count,
                    'orders'     => $seller->orders_count,
                    'rating'     => 4.5,
                    'growth'     => round($growth, 1),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $sellers]);
    }

    public function getRecentActivity(Request $request)
    {
        $limit = (int) $request->input('limit', 10);

        $recentOrders = Order::with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                $status = $order->payment_status instanceof \BackedEnum
                    ? $order->payment_status->value
                    : (string) $order->payment_status;

                return [
                    'id'          => 'order_' . $order->id,
                    'type'        => 'order',
                    'user'        => $order->user->fullname ?? 'Client',
                    'description' => 'Nouvelle commande passée',
                    'amount'      => '$' . number_format((float) $order->total_amount, 2),
                    'status'      => $status,
                    'time'        => $order->created_at->toIso8601String(),
                ];
            });

        $recentUsers = User::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id'          => 'user_' . $user->id,
                    'type'        => 'new_user',
                    'user'        => $user->fullname,
                    'description' => 'Nouvel utilisateur inscrit',
                    'amount'      => null,
                    'status'      => 'success',
                    'time'        => $user->created_at->toIso8601String(),
                ];
            });

        $activity = $recentOrders->concat($recentUsers)
            ->sortByDesc('time')
            ->values()
            ->take($limit);

        return response()->json(['success' => true, 'data' => $activity]);
    }

    // -------------------------------------------------------------------------

    private function getDateRange(string $timeRange): array
    {
        $now = Carbon::now();

        switch ($timeRange) {
            case 'today':
                $start     = $now->copy()->startOfDay();
                $end       = $now->copy()->endOfDay();
                $prevStart = $now->copy()->subDay()->startOfDay();
                $prevEnd   = $now->copy()->subDay()->endOfDay();
                break;
            case 'week':
                $start     = $now->copy()->subDays(7);
                $end       = $now->copy();
                $prevStart = $now->copy()->subDays(14);
                $prevEnd   = $now->copy()->subDays(7);
                break;
            case 'quarter':
                $start     = $now->copy()->subDays(90);
                $end       = $now->copy();
                $prevStart = $now->copy()->subDays(180);
                $prevEnd   = $now->copy()->subDays(90);
                break;
            case 'year':
                $start     = $now->copy()->subYear();
                $end       = $now->copy();
                $prevStart = $now->copy()->subYears(2);
                $prevEnd   = $now->copy()->subYear();
                break;
            default: // month
                $start     = $now->copy()->subDays(30);
                $end       = $now->copy();
                $prevStart = $now->copy()->subDays(60);
                $prevEnd   = $now->copy()->subDays(30);
        }

        return [
            'start'          => $start,
            'end'            => $end,
            'previous_start' => $prevStart,
            'previous_end'   => $prevEnd,
        ];
    }

    private function getGroupByFormat(string $timeRange): string
    {
        return match ($timeRange) {
            'today'           => "DATE_FORMAT(created_at, '%H:00')",
            'week', 'month'   => "DATE(created_at)",
            'quarter', 'year' => "DATE_FORMAT(created_at, '%Y-%m')",
            default           => "DATE(created_at)",
        };
    }

    private function percentChange(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return (($current - $previous) / $previous) * 100;
    }
}
