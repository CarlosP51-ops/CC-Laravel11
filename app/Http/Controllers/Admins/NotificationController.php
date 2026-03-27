<?php

namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private AdminNotificationService $service) {}

    // ── GET /admin/notifications ──────────────────────────────────────────────
    public function index(Request $request)
    {
        try {
            // Synchronise les nouvelles notifications depuis les données réelles
            $this->service->sync();
        } catch (\Exception $e) {
            // Si sync échoue (colonnes manquantes), on continue sans sync
            \Log::warning('Notification sync failed: ' . $e->getMessage());
        }

        try {
            $query = AdminNotification::latest();

            if ($request->boolean('unread_only')) {
                $query->unread();
            }

            if ($type = $request->input('type')) {
                $query->where('type', $type);
            }

            $limit         = min((int) $request->input('limit', 30), 100);
            $notifications = $query->limit($limit)->get();
            $unreadCount   = AdminNotification::unread()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $notifications->map(fn($n) => $this->format($n)),
                    'unread_count'  => $unreadCount,
                    'total'         => $notifications->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => ['notifications' => [], 'unread_count' => 0, 'total' => 0],
            ]);
        }
    }

    // ── PATCH /admin/notifications/{id}/read ─────────────────────────────────
    public function markRead($id)
    {
        $notif = AdminNotification::findOrFail($id);
        $notif->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue.',
            'data'    => $this->format($notif),
        ]);
    }

    // ── POST /admin/notifications/read-all ───────────────────────────────────
    public function markAllRead()
    {
        AdminNotification::unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications marquées comme lues.',
        ]);
    }

    // ── DELETE /admin/notifications/{id} ─────────────────────────────────────
    public function destroy($id)
    {
        AdminNotification::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Notification supprimée.']);
    }

    // ── DELETE /admin/notifications/clear-read ───────────────────────────────
    public function clearRead()
    {
        $count = AdminNotification::where('is_read', true)->delete();

        return response()->json([
            'success' => true,
            'message' => "$count notification(s) supprimée(s).",
        ]);
    }

    // ── GET /admin/notifications/stats ───────────────────────────────────────
    public function stats()
    {
        $unread = AdminNotification::unread()->count();

        $byType = AdminNotification::unread()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unread,
                'by_type'      => $byType,
            ],
        ]);
    }

    // ── Formatter ─────────────────────────────────────────────────────────────
    private function format(AdminNotification $n): array
    {
        return [
            'id'          => $n->id,
            'type'        => $n->type,
            'title'       => $n->title,
            'subtitle'    => $n->subtitle,
            'body'        => $n->body,
            'link'        => $n->link,
            'entity_type' => $n->entity_type,
            'entity_id'   => $n->entity_id,
            'meta'        => $n->meta,
            'is_read'     => $n->is_read,
            'read_at'     => $n->read_at?->toIso8601String(),
            'created_at'  => $n->created_at->toIso8601String(),
            'time_ago'    => $n->created_at->diffForHumans(),
        ];
    }
}
