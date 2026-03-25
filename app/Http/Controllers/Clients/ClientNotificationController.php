<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\ClientNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = ClientNotification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        $unread = ClientNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'notifications' => $notifications,
                'unread_count'  => $unread,
            ],
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        ClientNotification::where('user_id', auth()->id())
            ->where('id', $id)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        ClientNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        ClientNotification::where('user_id', auth()->id())
            ->where('id', $id)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = ClientNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json(['success' => true, 'data' => ['count' => $count]]);
    }
}
