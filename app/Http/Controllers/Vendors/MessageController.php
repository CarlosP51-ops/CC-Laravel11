<?php

namespace App\Http\Controllers\Vendors;

use App\Events\NewAdminMessage;
use App\Http\Controllers\Controller;
use App\Models\AdminMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    // ─── STATS ───────────────────────────────────────────────────────────────
    public function stats()
    {
        $vendorId = Auth::id();

        // L'admin avec qui le vendeur échange
        $adminId = User::where('role', 'admin')->value('id');

        return response()->json([
            'success' => true,
            'data' => [
                'total'  => AdminMessage::where(function ($q) use ($vendorId, $adminId) {
                    $q->where('from_user_id', $vendorId)->where('to_user_id', $adminId);
                })->orWhere(function ($q) use ($vendorId, $adminId) {
                    $q->where('from_user_id', $adminId)->where('to_user_id', $vendorId);
                })->count(),
                'unread' => AdminMessage::where('from_user_id', $adminId)
                    ->where('to_user_id', $vendorId)
                    ->where('is_read', false)
                    ->count(),
                'sent'   => AdminMessage::where('from_user_id', $vendorId)->count(),
            ],
        ]);
    }

    // ─── THREAD AVEC L'ADMIN ─────────────────────────────────────────────────
    public function thread()
    {
        $vendorId = Auth::id();
        $adminId  = User::where('role', 'admin')->value('id');

        if (!$adminId) {
            return response()->json(['success' => false, 'message' => 'Aucun admin trouvé'], 404);
        }

        $messages = AdminMessage::where(function ($q) use ($vendorId, $adminId) {
            $q->where('from_user_id', $vendorId)->where('to_user_id', $adminId);
        })->orWhere(function ($q) use ($vendorId, $adminId) {
            $q->where('from_user_id', $adminId)->where('to_user_id', $vendorId);
        })->with(['sender:id,fullname,role'])
          ->orderBy('created_at')
          ->get()
          ->map(fn($m) => [
              'id'         => $m->id,
              'body'       => $m->body,
              'subject'    => $m->subject,
              'is_read'    => $m->is_read,
              'is_mine'    => $m->from_user_id === $vendorId,
              'created_at' => $m->created_at->toISOString(),
              'sender'     => [
                  'id'       => $m->sender->id,
                  'fullname' => $m->sender->fullname,
                  'role'     => $m->sender->role,
              ],
          ]);

        // Marquer les messages de l'admin comme lus
        AdminMessage::where('from_user_id', $adminId)
            ->where('to_user_id', $vendorId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data'    => [
                'messages' => $messages,
                'admin_id' => $adminId,
            ],
        ]);
    }

    // ─── ENVOYER UN MESSAGE À L'ADMIN ────────────────────────────────────────
    public function send(Request $request)
    {
        $request->validate([
            'body'    => 'required|string|max:5000',
            'subject' => 'nullable|string|max:255',
        ]);

        $vendorId = Auth::id();
        $adminId  = User::where('role', 'admin')->value('id');

        if (!$adminId) {
            return response()->json(['success' => false, 'message' => 'Aucun admin disponible'], 404);
        }

        $message = AdminMessage::create([
            'from_user_id' => $vendorId,
            'to_user_id'   => $adminId,
            'subject'      => $request->subject ?? 'Message vendeur',
            'body'         => $request->body,
            'is_read'      => false,
        ]);

        $message->load('sender:id,fullname,role');

        // Broadcast à l'admin via Reverb
        broadcast(new NewAdminMessage($message))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $message->id,
                'body'       => $message->body,
                'subject'    => $message->subject,
                'is_read'    => $message->is_read,
                'is_mine'    => true,
                'created_at' => $message->created_at->toISOString(),
                'sender'     => [
                    'id'       => $message->sender->id,
                    'fullname' => $message->sender->fullname,
                    'role'     => $message->sender->role,
                ],
            ],
            'message' => 'Message envoyé',
        ]);
    }
}
