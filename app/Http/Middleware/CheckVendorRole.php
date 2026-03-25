<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckVendorRole
{
    /**
     * Vérifier que l'utilisateur est authentifié et a le rôle vendeur
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $role = 'vendor')
    {
        $user = Auth::user();

        // Vérifier l'authentification
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié. Veuillez vous connecter.'
            ], 401);
        }

        // Vérifier le rôle (supporter enum et string)
        $userRole = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        if ($userRole !== $role) {
            return response()->json([
                'success' => false,
                'message' => "Accès refusé. Rôle requis : {$role}."
            ], 403);
        }

        // Bloquer tout compte désactivé, quel que soit le rôle
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => $userRole === 'vendor'
                    ? 'Votre compte vendeur est en attente d\'activation par un administrateur.'
                    : 'Votre compte est désactivé.',
                'pending_activation' => $userRole === 'vendor',
            ], 403);
        }

        return $next($request);
    }
}
