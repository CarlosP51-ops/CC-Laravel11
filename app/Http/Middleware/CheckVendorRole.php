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
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // Vérifier l'authentification
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié. Veuillez vous connecter.'
            ], 401);
        }
        
        // Vérifier le rôle vendeur (supporter enum et string)
        $userRole = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;
        
        if ($userRole !== 'vendor') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous devez être vendeur pour accéder à cette ressource.'
            ], 403);
        }
        
        return $next($request);
    }
}
