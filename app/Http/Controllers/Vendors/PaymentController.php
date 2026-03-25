<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Récupérer les statistiques de paiement du vendeur
     */
    
    public function stats()
    {
        $user = Auth::user();
        $seller = $user->seller;

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Profil vendeur non trouvé'
            ], 404);
        }

        // Calculer les revenus
        $totalRevenue = Order::where('seller_id', $seller->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->sum('total_amount');

        $pendingRevenue = Order::where('seller_id', $seller->id)
            ->whereIn('status', ['pending', 'processing', 'shipped'])
            ->sum('total_amount');

        $availableBalance = $seller->balance ?? 0;
        $totalWithdrawn = $seller->total_withdrawn ?? 0;

        // Commandes par statut
        $completedOrders = Order::where('seller_id', $seller->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'pending_revenue' => $pendingRevenue,
                'available_balance' => $availableBalance,
                'total_withdrawn' => $totalWithdrawn,
                'completed_orders' => $completedOrders,
            ]
        ]);
    }

    /**
     * Récupérer l'historique des transactions
     */
    public function transactions(Request $request)
    {
        $user = Auth::user();
        $seller = $user->seller;

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Profil vendeur non trouvé'
            ], 404);
        }

        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $search = $request->input('search');

        $query = Order::where('seller_id', $seller->id)
            ->with(['user', 'items.product']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('fullname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    /**
     * Demander un retrait
     */
    public function requestWithdrawal(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:10',
            'payment_method' => 'required|string|in:bank_transfer,paypal,stripe',
            'account_details' => 'required|string',
        ]);

        $user = Auth::user();
        $seller = $user->seller;

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Profil vendeur non trouvé'
            ], 404);
        }

        $availableBalance = $seller->balance ?? 0;

        if ($validated['amount'] > $availableBalance) {
            return response()->json([
                'success' => false,
                'message' => 'Solde insuffisant'
            ], 400);
        }

        // Créer la demande de retrait (vous devrez créer le modèle Withdrawal)
        // Pour l'instant, on simule
        
        return response()->json([
            'success' => true,
            'message' => 'Demande de retrait créée avec succès',
            'data' => [
                'amount' => $validated['amount'],
                'status' => 'pending',
                'created_at' => now(),
            ]
        ]);
    }

    /**
     * Récupérer les méthodes de paiement du vendeur
     */
    public function paymentMethods()
    {
        $user = Auth::user();
        $seller = $user->seller;

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Profil vendeur non trouvé'
            ], 404);
        }

        // Simuler les méthodes de paiement (à adapter selon votre structure)
        $methods = [
            [
                'id' => 1,
                'type' => 'bank_transfer',
                'label' => 'Virement bancaire',
                'is_default' => true,
                'details' => [
                    'bank_name' => 'Banque Exemple',
                    'account_number' => '****1234',
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $methods
        ]);
    }
}
