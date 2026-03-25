<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Withdrawal;
use App\Models\PaymentGateway;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    // ─── STATS ───────────────────────────────────────────────────────────────

    public function stats()
    {
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total_amount');
        $platformFeeRate = 0.05; // 5%

        $pendingWithdrawals = Withdrawal::where('status', 'pending')
            ->sum('amount');
        $processingWithdrawals = Withdrawal::where('status', 'processing')
            ->sum('amount');
        $completedWithdrawals = Withdrawal::where('status', 'completed')
            ->sum('net_amount');

        $pendingCount = Withdrawal::where('status', 'pending')->count();
        $processingCount = Withdrawal::where('status', 'processing')->count();
        $completedCount = Withdrawal::where('status', 'completed')->count();
        $rejectedCount = Withdrawal::where('status', 'rejected')->count();

        $avgWithdrawal = Withdrawal::where('status', 'completed')->avg('amount') ?? 0;

        $todayWithdrawals = Withdrawal::whereDate('created_at', today())->count();
        $todayAmount = Withdrawal::whereDate('created_at', today())->sum('amount');

        // Répartition par gateway
        $byGateway = Payment::where('status', 'completed')
            ->select('gateway', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('gateway')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_balance' => round($totalRevenue, 2),
                'platform_revenue' => round($totalRevenue * $platformFeeRate, 2),
                'pending_withdrawals' => round($pendingWithdrawals, 2),
                'processing_withdrawals' => round($processingWithdrawals, 2),
                'completed_withdrawals' => round($completedWithdrawals, 2),
                'pending_count' => $pendingCount,
                'processing_count' => $processingCount,
                'completed_count' => $completedCount,
                'rejected_count' => $rejectedCount,
                'average_withdrawal' => round($avgWithdrawal, 2),
                'today_withdrawals' => $todayWithdrawals,
                'today_amount' => round($todayAmount, 2),
                'commission_rate' => 15.0,
                'platform_fee_rate' => $platformFeeRate * 100,
                'by_gateway' => $byGateway,
            ]
        ]);
    }

    // ─── WITHDRAWALS LIST ─────────────────────────────────────────────────────

    public function withdrawals(Request $request)
    {
        $query = Withdrawal::with('seller.user')
            ->orderBy('created_at', 'desc');

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%$search%")
                  ->orWhereHas('seller', fn($s) => $s->where('store_name', 'like', "%$search%"));
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->method && $request->method !== 'all') {
            $query->where('method', $request->method);
        }

        $withdrawals = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $withdrawals->map(fn($w) => $this->formatWithdrawal($w)),
            'meta' => [
                'total' => $withdrawals->total(),
                'per_page' => $withdrawals->perPage(),
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
            ]
        ]);
    }

    // ─── APPROVE WITHDRAWAL ───────────────────────────────────────────────────

    public function approveWithdrawal(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (!in_array($withdrawal->status, ['pending', 'pending_verification'])) {
            return response()->json(['success' => false, 'message' => 'Ce retrait ne peut pas être approuvé'], 422);
        }

        $withdrawal->update([
            'status' => 'processing',
            'processed_at' => now(),
            'notes' => 'Approuvé par l\'admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Retrait approuvé',
            'data' => $this->formatWithdrawal($withdrawal->fresh('seller.user'))
        ]);
    }

    // ─── REJECT WITHDRAWAL ────────────────────────────────────────────────────

    public function rejectWithdrawal(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $withdrawal = Withdrawal::findOrFail($id);

        if (!in_array($withdrawal->status, ['pending', 'pending_verification', 'processing'])) {
            return response()->json(['success' => false, 'message' => 'Ce retrait ne peut pas être rejeté'], 422);
        }

        $withdrawal->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Retrait rejeté',
            'data' => $this->formatWithdrawal($withdrawal->fresh('seller.user'))
        ]);
    }

    // ─── PAYMENTS LIST ────────────────────────────────────────────────────────

    public function payments(Request $request)
    {
        $query = Payment::with('order.user')
            ->orderBy('created_at', 'desc');

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->gateway && $request->gateway !== 'all') {
            $query->where('gateway', $request->gateway);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%$search%")
                  ->orWhereHas('order', fn($o) => $o->where('order_number', 'like', "%$search%"));
            });
        }

        $payments = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $payments->map(fn($p) => [
                'id' => $p->id,
                'transaction_id' => $p->transaction_id,
                'gateway_transaction_id' => $p->gateway_transaction_id,
                'amount' => $p->amount,
                'fee' => $p->fee,
                'status' => $p->status,
                'gateway' => $p->gateway ?? 'manual',
                'payment_method' => $p->payment_method,
                'payment_date' => $p->payment_date,
                'order' => $p->order ? [
                    'id' => $p->order->id,
                    'order_number' => $p->order->order_number,
                    'total' => $p->order->total_amount,
                    'user' => $p->order->user ? [
                        'name' => $p->order->user->fullname,
                        'email' => $p->order->user->email,
                    ] : null,
                ] : null,
            ]),
            'meta' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ]
        ]);
    }

    // ─── GATEWAYS CONFIG ─────────────────────────────────────────────────────

    public function gateways()
    {
        $gateways = PaymentGateway::all();

        // Si aucune gateway en BDD, retourner les defaults
        if ($gateways->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => $this->defaultGateways()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $gateways->map(fn($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'icon' => $g->icon,
                'is_active' => $g->is_active,
                'is_test_mode' => $g->is_test_mode,
                'public_key' => $g->public_key ? '••••' . substr($g->public_key, -4) : null,
                'has_secret_key' => !empty($g->secret_key),
                'supported_methods' => $g->supported_methods,
                'settings' => $g->settings,
            ])
        ]);
    }

    public function updateGateway(Request $request, $slug)
    {
        $request->validate([
            'is_active' => 'boolean',
            'is_test_mode' => 'boolean',
            'public_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
        ]);

        $gateway = PaymentGateway::firstOrCreate(
            ['slug' => $slug],
            $this->getDefaultGatewayData($slug)
        );

        $updateData = array_filter([
            'is_active'    => $request->is_active,
            'is_test_mode' => $request->is_test_mode,
            'public_key'   => $request->public_key,
            // Chiffrement des clés sensibles avant stockage
            'secret_key'      => $request->secret_key      ? Crypt::encryptString($request->secret_key)      : null,
            'webhook_secret'  => $request->webhook_secret  ? Crypt::encryptString($request->webhook_secret)  : null,
        ], fn($v) => !is_null($v));

        $gateway->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Gateway mise à jour',
            'data' => [
                'slug' => $gateway->slug,
                'is_active' => $gateway->is_active,
                'is_test_mode' => $gateway->is_test_mode,
                'has_keys' => !empty($gateway->public_key) && !empty($gateway->secret_key),
            ]
        ]);
    }

    // ─── EXPORT ───────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $type   = $request->type ?? 'withdrawals';
        $format = $request->format ?? 'csv';
        $limit  = min((int) ($request->limit ?? 1000), 5000);

        $data = $type === 'withdrawals'
            ? Withdrawal::with('seller.user')->limit($limit)->get()->map(fn($w) => $this->formatWithdrawal($w))
            : Payment::with('order.user')->limit($limit)->get();

        return response()->json([
            'success' => true,
            'message' => "Export $type en $format généré",
            'data' => $data
        ]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function formatWithdrawal($w): array
    {
        return [
            'id' => $w->id,
            'reference' => $w->reference,
            'seller' => $w->seller ? [
                'id' => $w->seller->id,
                'name' => $w->seller->store_name,
                'email' => $w->seller->user->email ?? '',
                'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $w->seller->slug,
            ] : null,
            'amount' => $w->amount,
            'fee' => $w->fee,
            'net_amount' => $w->net_amount,
            'method' => $w->method,
            'gateway' => $w->gateway,
            'payout_details' => $w->payout_details,
            'status' => $w->status,
            'notes' => $w->notes,
            'rejection_reason' => $w->rejection_reason,
            'risk_score' => $w->risk_score,
            'requested_at' => $w->created_at,
            'processed_at' => $w->processed_at,
        ];
    }

    private function defaultGateways(): array
    {
        return [
            [
                'id' => null,
                'name' => 'Stripe',
                'slug' => 'stripe',
                'icon' => 'stripe',
                'is_active' => false,
                'is_test_mode' => true,
                'public_key' => null,
                'has_secret_key' => false,
                'supported_methods' => ['card', 'sepa'],
                'settings' => [],
            ],
            [
                'id' => null,
                'name' => 'FedaPay',
                'slug' => 'fedapay',
                'icon' => 'fedapay',
                'is_active' => false,
                'is_test_mode' => true,
                'public_key' => null,
                'has_secret_key' => false,
                'supported_methods' => ['mtn_momo', 'moov_money', 'card'],
                'settings' => [],
            ],
        ];
    }

    private function getDefaultGatewayData(string $slug): array
    {
        $defaults = [
            'stripe' => [
                'name' => 'Stripe',
                'slug' => 'stripe',
                'icon' => 'stripe',
                'supported_methods' => ['card', 'sepa'],
            ],
            'fedapay' => [
                'name' => 'FedaPay',
                'slug' => 'fedapay',
                'icon' => 'fedapay',
                'supported_methods' => ['mtn_momo', 'moov_money', 'card'],
            ],
        ];

        return $defaults[$slug] ?? ['name' => $slug, 'slug' => $slug];
    }
}
