<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SellerController extends Controller
{
    public function index(Request $request)
    {
        $query = Seller::with('user')->withCount('products');

        // Recherche
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%$search%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('fullname', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                  });
            });
        }

        // Filtre statut boutique
        if ($status = $request->input('status')) {
            if ($status === 'verified') $query->where('is_verified', true)->where('is_active', true);
            if ($status === 'active')   $query->where('is_active', true);
            if ($status === 'pending')  $query->where('is_verified', false)->where('is_active', false);
            if ($status === 'inactive') $query->where('is_active', false);
        }

        // Filtre date d'inscription
        if ($from = $request->input('registered_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('registered_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = (int) $request->input('per_page', 10);
        $sellers = $query
            ->withSum(['orders as total_revenue' => fn($q) => $q->where('payment_status', 'paid')], 'total_amount')
            ->withCount('orders as total_orders_count')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'sellers'    => $sellers->map(fn($s) => $this->formatSeller($s)),
                'pagination' => [
                    'total'        => $sellers->total(),
                    'per_page'     => $sellers->perPage(),
                    'current_page' => $sellers->currentPage(),
                    'last_page'    => $sellers->lastPage(),
                ],
            ],
        ]);
    }

    public function stats()
    {
        $total    = Seller::count();
        $verified = Seller::where('is_verified', true)->count();
        $pending  = Seller::where('is_verified', false)->where('is_active', false)->count();
        $active   = Seller::where('is_active', true)->count();
        $inactive = Seller::where('is_active', false)->count();

        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total_amount');
        $newThisMonth = Seller::whereMonth('created_at', Carbon::now()->month)
                              ->whereYear('created_at', Carbon::now()->year)
                              ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total'        => $total,
                'verified'     => $verified,
                'pending'      => $pending,
                'active'       => $active,
                'inactive'     => $inactive,
                'totalRevenue' => $totalRevenue,
                'newThisMonth' => $newThisMonth,
            ],
        ]);
    }

    public function show($id)
    {
        $seller = Seller::with(['user', 'restrictions'])->withCount('products')->findOrFail($id);

        $revenue = (float) Order::where('seller_id', $seller->id)
                        ->where('payment_status', 'paid')
                        ->sum('total_amount');

        $totalOrders = Order::where('seller_id', $seller->id)->count();

        $recentOrders = Order::where('seller_id', $seller->id)
            ->with('user:id,fullname,email')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($o) {
                return [
                    'id'           => $o->id,
                    'total_amount' => (float) $o->total_amount,
                    'status'       => $o->status,
                    'created_at'   => $o->created_at->toIso8601String(),
                    'client'       => $o->user ? $o->user->fullname : null,
                ];
            });

        $data = $this->formatSeller($seller);
        $data['revenue']       = $revenue;
        $data['total_orders']  = $totalOrders;
        $data['recent_orders'] = $recentOrders;

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function toggleStatus($id)
    {
        $seller = Seller::with('user')->findOrFail($id);
        $seller->is_active = !$seller->is_active;
        $seller->save();

        // Sync is_active sur le user aussi
        if ($seller->user) {
            $seller->user->is_active = $seller->is_active;
            $seller->user->save();
        }

        // Notifier le vendeur
        \App\Services\NotificationService::onSellerStatusChanged($seller->user_id, $seller->is_active);

        return response()->json([
            'success' => true,
            'message' => $seller->is_active ? 'Boutique activée.' : 'Boutique désactivée.',
            'data'    => $this->formatSeller($seller),
        ]);
    }

    public function verify($id)
    {
        $seller = Seller::with('user')->findOrFail($id);
        $seller->is_verified = true;
        $seller->is_active   = true;
        $seller->save();

        // Activer le compte user aussi
        if ($seller->user) {
            $seller->user->is_active = true;
            $seller->user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Vendeur vérifié et activé.',
            'data'    => $this->formatSeller($seller),
        ]);
    }

    public function export()
    {
        $sellers = Seller::with('user')
            ->withCount('products')
            ->withSum(['orders as total_revenue' => fn($q) => $q->where('payment_status', 'paid')], 'total_amount')
            ->withCount('orders as total_orders_count')
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get()
            ->map(fn($s) => $this->formatSeller($s));

        return response()->json(['success' => true, 'data' => $sellers]);
    }

    public function applyRestriction(Request $request, $id)
    {
        $seller = Seller::findOrFail($id);

        $validated = $request->validate([
            'type'       => 'required|string|in:limit_uploads,quality_review,limited_withdrawals,no_withdrawals,suspended',
            'reason'     => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        // Désactiver une restriction existante du même type avant d'en créer une nouvelle
        \App\Models\SellerRestriction::where('seller_id', $seller->id)
            ->where('type', $validated['type'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $labels = [
            'limit_uploads'       => 'Limite d\'uploads',
            'quality_review'      => 'Révision qualité',
            'limited_withdrawals' => 'Retraits limités',
            'no_withdrawals'      => 'Pas de retraits',
            'suspended'           => 'Suspendu',
        ];

        $restriction = \App\Models\SellerRestriction::create([
            'seller_id'  => $seller->id,
            'type'       => $validated['type'],
            'label'      => $labels[$validated['type']],
            'reason'     => $validated['reason'] ?? null,
            'applied_by' => auth()->id(),
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active'  => true,
        ]);

        // Si suspendu → désactiver la boutique
        if ($validated['type'] === 'suspended') {
            $seller->is_active = false;
            $seller->save();
            if ($seller->user) {
                $seller->user->is_active = false;
                $seller->user->save();
            }
        }

        $seller->load('restrictions');

        return response()->json([
            'success' => true,
            'message' => 'Restriction appliquée.',
            'data'    => $this->formatSeller($seller),
        ]);
    }

    public function removeRestriction($id, $restrictionId)
    {
        $restriction = \App\Models\SellerRestriction::where('seller_id', $id)
            ->findOrFail($restrictionId);

        $restriction->is_active = false;
        $restriction->save();

        $seller = Seller::with(['user', 'restrictions'])->withCount('products')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Restriction levée.',
            'data'    => $this->formatSeller($seller),
        ]);
    }

    // -------------------------------------------------------------------------

    public function sellerProducts(Request $request, $id)
    {
        $seller = Seller::findOrFail($id);

        $query = Product::with('category')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('seller_id', $seller->id);

        if ($status = $request->input('status')) {
            if ($status === 'active')   $query->where('is_active', true);
            if ($status === 'inactive') $query->where('is_active', false);
            if ($status === 'pending')  $query->where('status', 'pending');
            if ($status === 'approved') $query->where('status', 'approved');
            if ($status === 'rejected') $query->where('status', 'rejected');
        }

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%$search%");
        }

        $perPage  = (int) $request->input('per_page', 10);
        $products = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn($p) => [
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'price'         => (float) $p->price,
                    'status'        => $p->status ?? 'pending',
                    'is_active'     => (bool) $p->is_active,
                    'is_promoted'   => (bool) ($p->is_promoted ?? false),
                    'category'      => $p->category?->name,
                    'rating'        => round((float) ($p->reviews_avg_rating ?? 0), 1),
                    'reviews_count' => $p->reviews_count ?? 0,
                    'sku'           => $p->sku,
                    'created_at'    => $p->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'total'        => $products->total(),
                    'per_page'     => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page'    => $products->lastPage(),
                ],
            ],
        ]);
    }

    public function sellerOrders(Request $request, $id)
    {
        $seller = Seller::findOrFail($id);

        $query = Order::with('user:id,fullname,email')
            ->where('seller_id', $seller->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%$search%")
                  ->orWhereHas('user', fn($u) => $u->where('fullname', 'like', "%$search%"));
            });
        }

        // Stats globales pour cet ensemble de filtres
        $statsQuery = Order::where('seller_id', $seller->id);
        $totalRevenue = (float) (clone $statsQuery)->where('payment_status', 'paid')->sum('total_amount');
        $countByStatus = (clone $statsQuery)->selectRaw('status, count(*) as count')
            ->groupBy('status')->pluck('count', 'status');

        $perPage = (int) $request->input('per_page', 10);
        $orders  = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders->map(fn($o) => [
                    'id'             => $o->id,
                    'order_number'   => $o->order_number,
                    'total_amount'   => (float) $o->total_amount,
                    'status'         => $o->status,
                    'payment_status' => $o->payment_status,
                    'client'         => $o->user?->fullname,
                    'client_email'   => $o->user?->email,
                    'created_at'     => $o->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'total'        => $orders->total(),
                    'per_page'     => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                ],
                'stats' => [
                    'total_revenue' => $totalRevenue,
                    'by_status'     => $countByStatus,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------

    private function formatSeller(Seller $seller): array
    {
        $user = $seller->user;

        // Utilise les valeurs pré-chargées via withSum/withCount si disponibles (évite N+1)
        $revenue     = (float) ($seller->total_revenue ?? Order::where('seller_id', $seller->id)
                            ->where('payment_status', 'paid')
                            ->sum('total_amount'));
        $totalOrders = $seller->total_orders_count ?? Order::where('seller_id', $seller->id)->count();
        $totalProducts = isset($seller->products_count) ? $seller->products_count : $seller->products()->count();

        if ($seller->is_verified && $seller->is_active) {
            $status = 'verified';
        } elseif ($seller->is_active) {
            $status = 'active';
        } else {
            $status = 'pending';
        }

        return [
            'id'             => $seller->id,
            'store_name'     => $seller->store_name,
            'slug'           => $seller->slug,
            'description'    => $seller->description,
            'logo'           => $seller->logo ? "http://localhost:8000/storage/{$seller->logo}" : null,
            'banner'         => $seller->banner ? "http://localhost:8000/storage/{$seller->banner}" : null,
            'city'           => $seller->city,
            'country'        => $seller->country,
            'is_verified'    => (bool) $seller->is_verified,
            'is_active'      => (bool) $seller->is_active,
            'status'         => $status,
            'created_at'     => $seller->created_at->toIso8601String(),
            'revenue'        => $revenue,
            'total_orders'   => $totalOrders,
            'total_products' => $totalProducts,
            'user_id'        => $user ? $user->id : null,
            'owner'          => $user ? $user->fullname : null,
            'email'          => $user ? $user->email : null,
            'phone'          => $user ? $user->phone : null,
            'user_active'    => $user ? (bool) $user->is_active : false,
            'avatar'         => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($seller->store_name),
            'restrictions'   => $seller->relationLoaded('restrictions')
                ? $seller->restrictions->map(fn($r) => [
                    'id'         => $r->id,
                    'type'       => $r->type,
                    'label'      => $r->label,
                    'reason'     => $r->reason,
                    'expires_at' => $r->expires_at?->toIso8601String(),
                    'created_at' => $r->created_at->toIso8601String(),
                ])->values()
                : [],
        ];
    }
}
