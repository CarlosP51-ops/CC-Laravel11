<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerFollow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /** Suivre / ne plus suivre un vendeur */
    public function toggle(int $sellerId): JsonResponse
    {
        $seller = Seller::where('is_active', true)->findOrFail($sellerId);
        $userId = auth()->id();

        $existing = SellerFollow::where('user_id', $userId)
            ->where('seller_id', $sellerId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'success'    => true,
                'following'  => false,
                'message'    => 'Vous ne suivez plus ce vendeur.',
            ]);
        }

        SellerFollow::create(['user_id' => $userId, 'seller_id' => $sellerId]);

        return response()->json([
            'success'   => true,
            'following' => true,
            'message'   => 'Vous suivez maintenant ' . $seller->store_name,
        ]);
    }

    /** Vérifier si l'utilisateur suit un vendeur */
    public function check(int $sellerId): JsonResponse
    {
        $following = SellerFollow::where('user_id', auth()->id())
            ->where('seller_id', $sellerId)
            ->exists();

        return response()->json(['success' => true, 'data' => ['following' => $following]]);
    }
}
