<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\VendorNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VendorNotification::where('user_id', auth()->id())->latest();

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $limit         = min((int) $request->input('limit', 30), 100);
        $notifications = $query->limit($limit)->get();
        $unreadCount   = VendorNotification::where('user_id', auth()->id())->unread()->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'notifications' => $notifications,
                'unread_count'  => $unreadCount,
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = VendorNotification::where('user_id', auth()->id())->unread()->count();
        return response()->json(['success' => true, 'data' => ['count' => $count]]);
    }

    public function markRead(int $id): JsonResponse
    {
        VendorNotification::where('user_id', auth()->id())->where('id', $id)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        VendorNotification::where('user_id', auth()->id())->unread()->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        VendorNotification::where('user_id', auth()->id())->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
