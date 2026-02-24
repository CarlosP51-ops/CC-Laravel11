<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
 use App\Models\Seller; 
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
   // Assurez-vous d'importer le modèle Seller



public function register(RegisterRequest $request)
{
    DB::beginTransaction(); // Commence la transaction

    try {
        // Créer l'utilisateur
        $user = User::create([
            'fullname' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role,
        ]);
        
        if ($request->role === 'vendor') {
            // Vérifiez si la boutique existe déjà
            if (Seller::where('store_name', $request->store_name)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nom de boutique est déjà pris.',
                ], 409);
            }

            $logoPath = $request->hasFile('logo') ? $request->file('logo')->store('logos', 'public') : null;
            $bannerPath = $request->hasFile('banner') ? $request->file('banner')->store('banners', 'public') : null;

            // Créer le vendeur
            Seller::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'slug' => $request->slug,
                'description' => $request->description,
                'logo' => $logoPath,
                'banner' => $bannerPath,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'is_verified' => false,
                'is_active' => false,
            ]);
        }

        // Création du token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        DB::commit(); // Valider la transaction

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie.',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack(); // Annuler la transaction en cas de problème

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'inscription : ' . $e->getMessage(),
        ], 500);
    }
}
    /**
     * Connexion
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        $user->tokens()->delete(); // Révoquer les anciens tokens

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => new UserResource($user), // Utiliser la ressource
            'token' => $token,
        ]);
    }

    /**
     * Déconnexion (révoquer le token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.'
        ]);
    }

    /**
     * Récupérer l'utilisateur connecté
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => new UserResource($request->user())
        ]);
    }
}