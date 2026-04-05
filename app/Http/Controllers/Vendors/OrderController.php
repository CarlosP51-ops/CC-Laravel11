<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Récupérer toutes les commandes du vendeur
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $seller = $user->seller;

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil vendeur non trouvé'
                ], 404);
            }
            
            // Paramètres de filtrage
            $status = $request->input('status');
            $search = $request->input('search');
            $dateRange = $request->input('date_range', '30days');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // Query de base - commandes du vendeur
            $query = Order::where('seller_id', $seller->id)
                ->with([
                    'user:id,fullname,email,phone',
                    'items.product:id,name,price,images'
                ]);

            // Filtre par statut
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            // Filtre par recherche
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($query) use ($search) {
                          $query->where('fullname', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Filtre par date
            $now = now();
            switch ($dateRange) {
                case 'today':
                    $query->whereDate('created_at', $now->toDateString());
                    break;
                case '7days':
                    $query->where('created_at', '>=', $now->subDays(7));
                    break;
                case '30days':
                    $query->where('created_at', '>=', $now->subDays(30));
                    break;
                case '90days':
                    $query->where('created_at', '>=', $now->subDays(90));
                    break;
            }

            // Tri
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $orders = $query->paginate($perPage);

            // Calculer les statistiques
            $stats = $this->calculateStats($seller->id);

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les détails d'une commande spécifique
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $seller = $user->seller;

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil vendeur non trouvé'
                ], 404);
            }

            $order = Order::with([
                'user:id,fullname,email,phone,created_at',
                'items.product:id,name,description,price,images',
                'shippingAddress',
                'billingAddress'
            ])->find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée'
                ], 404);
            }

            // Vérifier que la commande appartient au vendeur
            if ($order->seller_id !== $seller->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette commande'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $seller = $user->seller;

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil vendeur non trouvé'
                ], 404);
            }

            $request->validate([
                'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
            ]);

            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée'
                ], 404);
            }

            // Vérifier que la commande appartient au vendeur
            if ($order->seller_id !== $seller->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette commande'
                ], 403);
            }

            $order->status = $request->status;
            $order->save();

            // Notifier le client du changement de statut
            NotificationService::onOrderStatusChanged($order, $request->status);

            return response()->json([
                'success' => true,
                'message' => 'Statut de la commande mis à jour avec succès',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les informations de suivi
     */
    public function updateTracking(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $seller = $user->seller;

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil vendeur non trouvé'
                ], 404);
            }

            $request->validate([
                'tracking_number' => 'required|string|max:255',
                'carrier' => 'nullable|string|max:255',
                'tracking_url' => 'nullable|url'
            ]);

            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée'
                ], 404);
            }

            // Vérifier que la commande appartient au vendeur
            if ($order->seller_id !== $seller->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette commande'
                ], 403);
            }

            $order->tracking_number = $request->tracking_number;
            $order->carrier = $request->carrier;
            $order->tracking_url = $request->tracking_url;
            $order->status = 'shipped';
            $order->save();

            // Notifier le client
            NotificationService::onOrderStatusChanged($order, 'shipped');

            return response()->json([
                'success' => true,
                'message' => 'Informations de suivi mises à jour avec succès',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du suivi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajouter une note à une commande
     */
    public function addNote(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $seller = $user->seller;

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil vendeur non trouvé'
                ], 404);
            }

            $request->validate([
                'note' => 'required|string|max:1000'
            ]);

            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande non trouvée'
                ], 404);
            }

            // Vérifier que la commande appartient au vendeur
            if ($order->seller_id !== $seller->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette commande'
                ], 403);
            }

            // Ajouter la note aux notes existantes
            $notes = $order->notes ? json_decode($order->notes, true) : [];
            $notes[] = [
                'seller_id' => $seller->id,
                'note' => $request->note,
                'created_at' => now()->toDateTimeString()
            ];

            $order->notes = json_encode($notes);
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Note ajoutée avec succès',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de la note',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer les statistiques des commandes du vendeur
     */
    private function calculateStats($sellerId)
    {
        $orders = Order::where('seller_id', $sellerId)
            ->with('items')
            ->get();

        $totalRevenue = $orders->sum('total_amount');

        return [
            'total' => $orders->count(),
            'pending' => $orders->where('status', 'pending')->count(),
            'processing' => $orders->where('status', 'processing')->count(),
            'shipped' => $orders->where('status', 'shipped')->count(),
            'delivered' => $orders->where('status', 'delivered')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
            'total_revenue' => $totalRevenue,
            'avg_order_value' => $orders->count() > 0 ? $totalRevenue / $orders->count() : 0,
        ];
    }
}
