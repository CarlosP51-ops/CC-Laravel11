<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Récupérer l'historique des paiements de l'utilisateur
     */
    public function getPaymentHistory(Request $request)
    {
        $user = $request->user();

        // Récupérer les commandes payées avec leurs détails
        $payments = $user->orders()
            ->where('payment_status', 'paid')
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'date' => $order->created_at->format('d M Y'),
                    'amount' => number_format($order->total, 2) . '€',
                    'status' => 'success',
                    'product' => $order->items->first()->product->name ?? 'Produit',
                    'method' => $order->payment_method === 'card' ? 'Carte bancaire' : ucfirst($order->payment_method),
                    'items_count' => $order->items->count(),
                ];
            });

        // Calculer les statistiques
        $totalSpent = $user->orders()
            ->where('payment_status', 'paid')
            ->sum('total');

        $totalTransactions = $user->orders()
            ->where('payment_status', 'paid')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'stats' => [
                    'total_spent' => number_format($totalSpent, 2),
                    'total_transactions' => $totalTransactions,
                ]
            ]
        ]);
    }

    /**
     * Récupérer les statistiques de paiement
     */
    public function getPaymentStats(Request $request)
    {
        $user = $request->user();

        $totalSpent = $user->orders()
            ->where('payment_status', 'paid')
            ->sum('total');

        $totalTransactions = $user->orders()
            ->where('payment_status', 'paid')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_spent' => number_format($totalSpent, 2),
                'total_transactions' => $totalTransactions,
            ]
        ]);
    }
}
