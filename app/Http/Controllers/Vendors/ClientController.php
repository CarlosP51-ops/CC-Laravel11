<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    /**
     * Récupérer la liste des clients du vendeur
     */
    public function index(Request $request)
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
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'total_spent');
        $sortOrder = $request->input('sort_order', 'desc');

        // Récupérer les clients qui ont commandé chez ce vendeur
        $query = User::whereHas('orders', function($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })
        ->withCount(['orders as total_orders' => function($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        }])
        ->withSum(['orders as total_spent' => function($q) use ($seller) {
            $q->where('seller_id', $seller->id)
              ->whereIn('status', ['completed', 'delivered']);
        }], 'total_amount')
        ->with(['orders' => function($q) use ($seller) {
            $q->where('seller_id', $seller->id)
              ->latest()
              ->limit(1);
        }]);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortBy, $sortOrder);

        $clients = $query->paginate($perPage);

        // Formater les données
        $formattedClients = $clients->map(function($client) {
            return [
                'id' => $client->id,
                'fullname' => $client->fullname,
                'email' => $client->email,
                'phone' => $client->phone,
                'total_orders' => $client->total_orders ?? 0,
                'total_spent' => $client->total_spent ?? 0,
                'last_order_date' => $client->orders->first()?->created_at,
                'created_at' => $client->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedClients,
            'pagination' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ]
        ]);
    }

    /**
     * Récupérer les détails d'un client
     */
    public function show($id)
    {
        $user = Auth::user();
        $seller = $user->seller;

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Profil vendeur non trouvé'
            ], 404);
        }

        $client = User::find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], 404);
        }

        // Vérifier que ce client a bien commandé chez ce vendeur
        $hasOrdered = Order::where('user_id', $client->id)
            ->where('seller_id', $seller->id)
            ->exists();

        if (!$hasOrdered) {
            return response()->json([
                'success' => false,
                'message' => 'Ce client n\'a pas commandé chez vous'
            ], 403);
        }

        // Statistiques du client
        $totalOrders = Order::where('user_id', $client->id)
            ->where('seller_id', $seller->id)
            ->count();

        $totalSpent = Order::where('user_id', $client->id)
            ->where('seller_id', $seller->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->sum('total_amount');

        $averageOrderValue = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;

        // Dernières commandes
        $recentOrders = Order::where('user_id', $client->id)
            ->where('seller_id', $seller->id)
            ->with(['items.product'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'fullname' => $client->fullname,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'created_at' => $client->created_at,
                ],
                'stats' => [
                    'total_orders' => $totalOrders,
                    'total_spent' => $totalSpent,
                    'average_order_value' => $averageOrderValue,
                ],
                'recent_orders' => $recentOrders,
            ]
        ]);
    }

    /**
     * Récupérer les statistiques globales des clients
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

        // Nombre total de clients
        $totalClients = User::whereHas('orders', function($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->count();

        // Nouveaux clients ce mois
        $newClientsThisMonth = User::whereHas('orders', function($q) use ($seller) {
            $q->where('seller_id', $seller->id)
              ->whereMonth('created_at', now()->month)
              ->whereYear('created_at', now()->year);
        })->count();

        // Clients actifs (commandé dans les 30 derniers jours)
        $activeClients = User::whereHas('orders', function($q) use ($seller) {
            $q->where('seller_id', $seller->id)
              ->where('created_at', '>=', now()->subDays(30));
        })->count();

        // Valeur moyenne par client
        $averageClientValue = Order::where('seller_id', $seller->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->avg('total_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_clients' => $totalClients,
                'new_clients_this_month' => $newClientsThisMonth,
                'active_clients' => $activeClients,
                'average_client_value' => $averageClientValue ?? 0,
            ]
        ]);
    }
}
