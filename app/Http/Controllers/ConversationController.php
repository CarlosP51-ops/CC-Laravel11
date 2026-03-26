<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ConversationController extends Controller
{
    // ─── LISTE DES CONVERSATIONS ──────────────────────────────────────────────
    public function index(Request $request)
    {
        $userId = Auth::id();
        $search = $request->input('search', '');

        $conversations = Conversation::where('participant_1_id', $userId)
            ->orWhere('participant_2_id', $userId)
            ->with([
                'participant1:id,fullname,role',
                'participant2:id,fullname,role',
                'lastMessage.attachments',
            ])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($conv) use ($userId, $search) {
                // Ignorer les conversations supprimées par cet utilisateur
                if ($conv->isDeletedFor($userId)) return null;

                $other = $conv->otherParticipant($userId);

                // Ignorer les conversations avec un participant supprimé
                if (!$other) return null;

                if ($search && !str_contains(strtolower($other->fullname), strtolower($search))) {
                    return null;
                }

                $unread = Message::where('conversation_id', $conv->id)
                    ->where('sender_id', '!=', $userId)
                    ->where('is_read', false)
                    ->count();

                $last = $conv->lastMessage;

                return [
                    'id'                => $conv->id,
                    'other_participant' => [
                        'id'       => $other->id,
                        'fullname' => $other->fullname,
                        'role'     => $other->role,
                    ],
                    'last_message' => $last ? [
                        'body'           => $last->body,
                        'created_at'     => $last->created_at->toISOString(),
                        'is_mine'        => $last->sender_id === $userId,
                        'has_attachment' => $last->attachments->isNotEmpty(),
                    ] : null,
                    'unread_count'    => $unread,
                    'last_message_at' => $conv->last_message_at?->toISOString(),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    // ─── STATS ────────────────────────────────────────────────────────────────
    public function stats()
    {
        $userId = Auth::id();

        $convIds = Conversation::where('participant_1_id', $userId)
            ->orWhere('participant_2_id', $userId)
            ->pluck('id');

        return response()->json([
            'success' => true,
            'data' => [
                'conversations' => $convIds->count(),
                'unread'        => Message::whereIn('conversation_id', $convIds)
                    ->where('sender_id', '!=', $userId)
                    ->where('is_read', false)
                    ->count(),
                'sent'          => Message::whereIn('conversation_id', $convIds)
                    ->where('sender_id', $userId)
                    ->count(),
            ],
        ]);
    }

    // ─── THREAD D'UNE CONVERSATION ────────────────────────────────────────────
    public function thread(int $conversationId)
    {
        $userId = Auth::id();

        $conv = Conversation::where('id', $conversationId)
            ->where(function ($q) use ($userId) {
                $q->where('participant_1_id', $userId)->orWhere('participant_2_id', $userId);
            })->firstOrFail();

        $messages = Message::where('conversation_id', $conv->id)
            ->with(['sender:id,fullname,role', 'attachments'])
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => $this->formatMessage($m, $userId));

        // Marquer comme lus
        Message::where('conversation_id', $conv->id)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data'    => $messages,
        ]);
    }

    // ─── DÉMARRER OU RÉCUPÉRER UNE CONVERSATION ──────────────────────────────
    public function findOrCreate(Request $request)
    {
        \Log::info('findOrCreate called', [
            'auth_id' => Auth::id(),
            'user_id' => $request->user_id,
            'all'     => $request->all(),
        ]);

        $request->validate(['user_id' => 'required|integer|min:1|exists:users,id']);

        $userId  = Auth::id();
        $otherId = (int) $request->user_id;

        if ($userId === $otherId) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas vous écrire à vous-même.'], 422);
        }

        $other = User::select('id', 'fullname', 'role')->find($otherId);

        if (!$other) {
            return response()->json(['success' => false, 'message' => 'Utilisateur introuvable'], 404);
        }

        $conv = Conversation::findOrCreate($userId, $otherId);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                => $conv->id,
                'other_participant' => [
                    'id'       => $other->id,
                    'fullname' => $other->fullname,
                    'role'     => $other->role,
                ],
            ],
        ]);
    }

    // ─── ENVOYER UN MESSAGE (texte + fichiers) ────────────────────────────────
    public function send(Request $request, int $conversationId)
    {
        $request->validate([
            'body'          => 'nullable|string|max:5000',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240', // 10MB max par fichier
        ]);

        if (!$request->body && !$request->hasFile('attachments')) {
            return response()->json(['success' => false, 'message' => 'Message ou fichier requis'], 422);
        }

        $userId = Auth::id();

        $conv = Conversation::where('id', $conversationId)
            ->where(function ($q) use ($userId) {
                $q->where('participant_1_id', $userId)->orWhere('participant_2_id', $userId);
            })->firstOrFail();

        $message = Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => $userId,
            'body'            => $request->body,
            'is_read'         => false,
        ]);

        // Upload des fichiers
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $uploaded = \App\Services\StorageService::uploadFile($file, 'messages/' . date('Y/m'));
                MessageAttachment::create([
                    'message_id'    => $message->id,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path'   => $uploaded['path'],
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        }

        // Mettre à jour last_message_at
        $conv->update(['last_message_at' => now()]);

        $message->load(['sender:id,fullname,role', 'attachments']);

        // Broadcast au destinataire
        $recipientId = $conv->participant_1_id === $userId
            ? $conv->participant_2_id
            : $conv->participant_1_id;

        broadcast(new MessageSent($message, $recipientId))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($message, $userId),
            'message' => 'Message envoyé',
        ]);
    }

    // ─── SUPPRIMER UNE CONVERSATION (soft delete par participant) ────────────
    public function destroy(int $conversationId)
    {
        $userId = Auth::id();

        $conv = Conversation::where('id', $conversationId)
            ->where(function ($q) use ($userId) {
                $q->where('participant_1_id', $userId)->orWhere('participant_2_id', $userId);
            })->firstOrFail();

        $conv->softDeleteFor($userId);

        return response()->json([
            'success' => true,
            'message' => 'Conversation supprimée.',
        ]);
    }

    // ─── LISTE DES USERS CONTACTABLES ────────────────────────────────────────
    public function searchUsers(Request $request)
    {
        $userId  = Auth::id();
        $search  = $request->input('search', '');
        $role    = Auth::user()->role;

        $query = User::where('id', '!=', $userId)
            ->select('id', 'fullname', 'email', 'role');

        // Admin peut contacter tout le monde
        // Vendeur peut contacter admin + clients
        // Client peut contacter admin + vendeurs
        if ($role === 'vendor') {
            $query->whereIn('role', ['admin', 'client']);
        } elseif ($role === 'client') {
            $query->whereIn('role', ['admin', 'vendor']);
        }

        if ($search) {
            $query->where(fn($q) => $q->where('fullname', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"));
        }

        return response()->json(['success' => true, 'data' => $query->limit(20)->get()]);
    }

    // ─── FORMAT MESSAGE ───────────────────────────────────────────────────────
    private function formatMessage(Message $m, int $userId): array
    {
        return [
            'id'              => $m->id,
            'conversation_id' => $m->conversation_id,
            'body'            => $m->body,
            'is_read'         => $m->is_read,
            'is_mine'         => $m->sender_id === $userId,
            'created_at'      => $m->created_at->toISOString(),
            'sender'          => [
                'id'       => $m->sender->id,
                'fullname' => $m->sender->fullname,
                'role'     => $m->sender->role,
            ],
            'attachments' => $m->attachments->map(fn($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'url'           => Storage::url($a->stored_path),
                'mime_type'     => $a->mime_type,
                'size'          => $a->formatted_size,
                'is_image'      => $a->is_image,
            ])->toArray(),
        ];
    }
}
