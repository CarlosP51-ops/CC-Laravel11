<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    /**
     * Envoyer une newsletter
     * POST /admin/newsletter/send
     *
     * Body: { subject, message, target: 'all_clients'|'all_vendors'|'all_users'|{user_id} }
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'target'  => 'required|string', // 'all_clients' | 'all_vendors' | 'all_users' | numeric user_id
        ]);

        $target = $validated['target'];

        // Valider la cible
        $validTargets = ['all_clients', 'all_vendors', 'all_users'];
        if (!in_array($target, $validTargets) && !is_numeric($target)) {
            return response()->json(['success' => false, 'message' => 'Cible invalide.'], 422);
        }

        // Si user_id spécifique, vérifier qu'il existe
        if (is_numeric($target)) {
            $user = User::find((int) $target);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
            }
        }

        $result = NotificationService::adminNewsletter(
            $validated['subject'],
            $validated['message'],
            is_numeric($target) ? (int) $target : $target
        );

        if ($result['total'] === 0) {
            return response()->json(['success' => false, 'message' => 'Aucun destinataire trouvé pour cette cible.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Newsletter envoyée à {$result['sent']} destinataire(s)" . ($result['failed'] > 0 ? ", {$result['failed']} échec(s)" : "") . ".",
            'data'    => $result,
        ]);
    }

    /**
     * Prévisualiser le nombre de destinataires selon la cible
     * GET /admin/newsletter/preview?target=all_clients
     */
    public function preview(Request $request): JsonResponse
    {
        $target = $request->input('target', 'all_users');

        $count = match($target) {
            'all_clients' => User::where('role', 'client')->count(),
            'all_vendors' => User::where('role', 'vendor')->count(),
            'all_users'   => User::whereIn('role', ['client', 'vendor'])->count(),
            default       => is_numeric($target) ? (User::where('id', $target)->exists() ? 1 : 0) : 0,
        };

        return response()->json(['success' => true, 'data' => ['count' => $count, 'target' => $target]]);
    }
}
