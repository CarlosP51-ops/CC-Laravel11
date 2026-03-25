<?php

namespace App\Http\Controllers\Admins;

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
        $adminId = Auth::id();

        return response()->json([
            'success' => true,
            'data' => [
                'total'        => AdminMessage::where('to_user_id', $adminId)->orWhere('from_user_id', $adminId)->count(),
                'unread'       => AdminMessage::where('to_user_id', $adminId)->where('is_read', false)->count(),
                'sent'         => AdminMessage::where('from_user_id', $adminId)->count(),
                'users_count'  => User::whereIn('role', ['vendor', 'client'])->count(),
            ],
        ]);
    }

    // ─── LISTE DES CONVERSATIONS (threads par utilisateur) ───────────────────
    public function conversations(Request $request)
    {
        $adminId = Auth::id();
        $search  = $request->input('search', '');

        // Récupère tous les user_ids avec qui l'admin a échangé
        $userIds = AdminMessage::where('from_user_id', $adminId)
            ->orWhere('to_user_id', $adminId)
            ->get()
            ->map(fn($m) => $m->from_user_id === $adminId ? $m->to_user_id : $m->from_user_id)
            ->unique()
            ->values();

        $conversations = User::whereIn('id', $userIds)
            ->when($search, fn($q) => $q->where('fullname', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"))
            ->get()
            ->map(function ($user) use ($adminId) {
                $last = AdminMessage::where(function ($q) use ($adminId, $user) {
                    $q->where('from_user_id', $adminId)->where('to_user_id', $user->id);
                })->orWhere(function ($q) use ($adminId, $user) {
                    $q->where('from_user_id', $user->id)->where('to_user_id', $adminId);
                })->latest()->first();

                $unread = AdminMessage::where('from_user_id', $user->id)
                    ->where('to_user_id', $adminId)
                    ->where('is_read', false)
                    ->count();

                return [
                    'user_id'    => $user->id,
                    'fullname'   => $user->fullname,
                    'email'      => $user->email,
                    'role'       => $user->role,
                    'last_message' => $last ? [
                        'body'       => $last->body,
                        'created_at' => $last->created_at->toISOString(),
                        'is_mine'    => $last->from_user_id === $adminId,
                    ] : null,
                    'unread_count' => $unread,
                ];
            })
            ->sortByDesc(fn($c) => $c['last_message']['created_at'] ?? '')
            ->values();

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    // ─── MESSAGES D'UNE CONVERSATION ─────────────────────────────────────────
    public function thread(int $userId)
    {
        $adminId = Auth::id();

        $messages = AdminMessage::where(function ($q) use ($adminId, $userId) {
            $q->where('from_user_id', $adminId)->where('to_user_id', $userId);
        })->orWhere(function ($q) use ($adminId, $userId) {
            $q->where('from_user_id', $userId)->where('to_user_id', $adminId);
        })->with(['sender:id,fullname,role'])
          ->orderBy('created_at')
          ->get()
          ->map(fn($m) => [
              'id'         => $m->id,
              'body'       => $m->body,
              'subject'    => $m->subject,
              'is_read'    => $m->is_read,
              'is_mine'    => $m->from_user_id === $adminId,
              'created_at' => $m->created_at->toISOString(),
              'sender'     => [
                  'id'       => $m->sender->id,
                  'fullname' => $m->sender->fullname,
                  'role'     => $m->sender->role,
              ],
          ]);

        // Marquer comme lus
        AdminMessage::where('from_user_id', $userId)
            ->where('to_user_id', $adminId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    // ─── ENVOYER UN MESSAGE ───────────────────────────────────────────────────
    public function send(Request $request)
    {
        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'body'       => 'required|string|max:5000',
            'subject'    => 'nullable|string|max:255',
        ]);

        $message = AdminMessage::create([
            'from_user_id' => Auth::id(),
            'to_user_id'   => $request->to_user_id,
            'subject'      => $request->subject ?? 'Message admin',
            'body'         => $request->body,
            'is_read'      => false,
        ]);

        $message->load('sender:id,fullname,role');

        // Broadcast via Reverb
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

    // ─── LISTE DES USERS CONTACTABLES ────────────────────────────────────────
    public function users(Request $request)
    {
        $search = $request->input('search', '');

        $users = User::whereIn('role', ['vendor', 'client'])
            ->when($search, fn($q) => $q->where('fullname', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"))
            ->select('id', 'fullname', 'email', 'role')
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    // ─── MARQUER COMME LU ────────────────────────────────────────────────────
    public function markRead(int $userId)
    {
        $adminId = Auth::id();

        AdminMessage::where('from_user_id', $userId)
            ->where('to_user_id', $adminId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Messages marqués comme lus']);
    }
}
