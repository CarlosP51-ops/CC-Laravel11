<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // "suspended" = soft-deleted → withTrashed + whereNotNull(deleted_at)
        $status = $request->input('status');

        if ($status === 'suspended') {
            $query = User::onlyTrashed();
        } else {
            $query = User::query(); // exclut automatiquement les soft-deleted
        }

        // Recherche
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        // Filtre par rôle
        if ($role = $request->input('role')) {
            if ($role !== 'all') $query->where('role', $role);
        }

        // Filtre par statut actif/en attente
        if ($status && $status !== 'suspended') {
            if ($status === 'active')  $query->where('is_active', true);
            if ($status === 'pending') $query->where('is_active', false);
        }

        // Filtre par date d'inscription
        if ($registeredFrom = $request->input('registered_from')) {
            $query->whereDate('created_at', '>=', $registeredFrom);
        }
        if ($registeredTo = $request->input('registered_to')) {
            $query->whereDate('created_at', '<=', $registeredTo);
        }

        // Filtre par dernière connexion (updated_at utilisé comme proxy)
        if ($lastLogin = $request->input('last_login')) {
            $date = match ($lastLogin) {
                'today'  => Carbon::today(),
                'week'   => Carbon::now()->subWeek(),
                'month'  => Carbon::now()->subMonth(),
                '3months'=> Carbon::now()->subMonths(3),
                default  => null,
            };
            if ($date) $query->where('updated_at', '>=', $date);
        }

        $perPage = (int) $request->input('per_page', 10);
        $users = $query
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn($q) => $q->where('payment_status', 'paid')], 'total_amount')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users->map(fn($u) => $this->formatUser($u)),
                'pagination' => [
                    'total'        => $users->total(),
                    'per_page'     => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page'    => $users->lastPage(),
                ],
            ],
        ]);
    }

    public function stats()
    {
        $total     = User::count();
        $vendors   = User::where('role', 'vendor')->count();
        $clients   = User::where('role', 'client')->count();
        $admins    = User::where('role', 'admin')->count();
        $active    = User::where('is_active', true)->count();
        $pending   = User::where('is_active', false)->count();
        $suspended = User::onlyTrashed()->count();
        $newToday  = User::whereDate('created_at', Carbon::today())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total'     => $total,
                'active'    => $active,
                'pending'   => $pending,
                'suspended' => $suspended,
                'newToday'  => $newToday,
                'sellers'   => $vendors,
                'buyers'    => $clients,
                'admins'    => $admins,
            ],
        ]);
    }

    public function show($id)
    {
        $user = User::withTrashed()->with('seller')->findOrFail($id);

        $role = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;

        $orders = Order::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($o) => [
                'id'             => $o->id,
                'total_amount'   => (float) $o->total_amount,
                'status'         => $o->status,
                'payment_status' => $o->payment_status,
                'created_at'     => $o->created_at->toIso8601String(),
            ]);

        $totalOrders = Order::where('user_id', $user->id)->count();
        $totalSpent  = (float) Order::where('user_id', $user->id)
                            ->where('payment_status', 'paid')
                            ->sum('total_amount');

        $data = [
            'id'               => $user->id,
            'name'             => $user->fullname,
            'email'            => $user->email,
            'phone'            => $user->phone ?? '',
            'role'             => $role,
            'status'           => $user->trashed() ? 'suspended' : ($user->is_active ? 'active' : 'pending'),
            'is_active'        => (bool) $user->is_active,
            'registrationDate' => $user->created_at->toIso8601String(),
            'lastLogin'        => $user->updated_at->toIso8601String(),
            'avatar'           => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($user->fullname),
            'orders'           => $totalOrders,
            'spent'            => $role === 'client' ? $totalSpent : 0,
            'revenue'          => $role === 'vendor' ? $totalSpent : 0,
            'recentOrders'     => $orders,
        ];

        if ($role === 'vendor' && $user->seller) {
            $s = $user->seller;
            $data['seller'] = [
                'store_name'  => $s->store_name,
                'slug'        => $s->slug,
                'description' => $s->description,
                'logo'        => $s->logo,
                'city'        => $s->city,
                'country'     => $s->country,
                'is_verified' => (bool) $s->is_verified,
                'is_active'   => (bool) $s->is_active,
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fullname' => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:client,vendor,admin',
            'phone'    => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'fullname' => $validated['fullname'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
            'phone'    => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès.',
            'data'    => $this->formatUser($user),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $id,
            'role'     => 'sometimes|in:client,vendor,admin',
            'phone'    => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // Mettre à jour les infos boutique si vendeur
        if ($user->role->value === 'vendor' || $user->role === 'vendor') {
            $sellerData = $request->validate([
                'store_name'  => 'sometimes|string|max:255',
                'slug'        => 'sometimes|string|max:255|unique:sellers,slug,' . optional($user->seller)->id,
                'description' => 'nullable|string',
                'address'     => 'nullable|string|max:255',
                'city'        => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'country'     => 'nullable|string|max:100',
            ]);

            if ($user->seller) {
                // Gérer logo
                if ($request->hasFile('logo')) {
                    $sellerData['logo'] = $request->file('logo')->store('logos', 'public');
                }
                // Gérer banner
                if ($request->hasFile('banner')) {
                    $sellerData['banner'] = $request->file('banner')->store('banners', 'public');
                }
                $user->seller->update($sellerData);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour.',
            'data'    => $this->formatUser($user),
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Empêcher la suppression de son propre compte
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Impossible de supprimer votre propre compte.'], 403);
        }

        $user->tokens()->delete();
        $user->delete(); // soft delete — la colonne deleted_at est renseignée, l'utilisateur disparaît des listes

        return response()->json(['success' => true, 'message' => 'Utilisateur supprimé.']);
    }

    public function toggleStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Un compte actif peut être désactivé, un compte inactif peut être activé
        $user->is_active = !$user->is_active;
        $user->save();

        $message = $user->is_active ? 'Compte activé.' : 'Compte désactivé.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $this->formatUser($user),
        ]);
    }

    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur réintégré avec succès.',
            'data'    => $this->formatUser($user),
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        // Exclure son propre compte ET les autres admins
        $ids = array_filter($ids, fn($id) => $id !== auth()->id());

        User::whereIn('id', $ids)->where('role', '!=', 'admin')->each(function ($user) {
            $user->tokens()->delete();
            $user->delete(); // soft delete
        });

        return response()->json(['success' => true, 'message' => count($ids) . ' utilisateur(s) supprimé(s).']);
    }

    public function bulkToggleStatus(Request $request)
    {
        $validated = $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
            'action' => 'required|in:active,inactive',
        ]);

        // Ne jamais modifier le statut d'autres admins
        User::whereIn('id', $validated['ids'])
            ->where('role', '!=', 'admin')
            ->update([
                'is_active' => $validated['action'] === 'active',
            ]);

        return response()->json(['success' => true, 'message' => 'Statut mis à jour.']);
    }

    public function export(Request $request)
    {
        $limit = min((int) $request->input('limit', 1000), 5000);
        $users = User::withCount('orders')
            ->withSum(['orders as total_spent' => fn($q) => $q->where('payment_status', 'paid')], 'total_amount')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($u) => $this->formatUser($u));

        return response()->json(['success' => true, 'data' => $users]);
    }

    // -------------------------------------------------------------------------

    private function formatUser(User $user, bool $detailed = false): array
    {
        $role = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;

        // Utilise les valeurs pré-chargées via withCount/withSum si disponibles (évite N+1)
        $totalOrders = $user->orders_count ?? Order::where('user_id', $user->id)->count();
        $totalSpent  = (float) ($user->total_spent ?? Order::where('user_id', $user->id)
                            ->where('payment_status', 'paid')
                            ->sum('total_amount'));

        $data = [
            'id'               => $user->id,
            'name'             => $user->fullname,
            'email'            => $user->email,
            'phone'            => $user->phone ?? '',
            'role'             => $role,
            'status'           => $user->trashed() ? 'suspended' : ($user->is_active ? 'active' : 'pending'),
            'verified'         => (bool) $user->is_active,
            'registrationDate' => $user->created_at->toIso8601String(),
            'lastLogin'        => $user->updated_at->toIso8601String(),
            'orders'           => $totalOrders,
            'spent'            => $role === 'client' ? $totalSpent : 0,
            'revenue'          => $role === 'vendor' ? $totalSpent : 0,
            'avatar'           => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($user->fullname),
            'device'           => 'desktop',
        ];

        return $data;
    }
}
