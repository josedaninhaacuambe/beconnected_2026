<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StoreNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = StoreNotification::where('user_id', $request->user()->id)
            ->latest()
            ->take(50)
            ->get();

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Cache per-user for 5s — this endpoint is polled frequently for badge counts
        $count = Cache::remember("notif_unread_{$userId}", 5, fn() =>
            StoreNotification::where('user_id', $userId)->unread()->count()
        );

        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        StoreNotification::where('user_id', $userId)
            ->where('id', $id)
            ->update(['read_at' => now()]);

        Cache::forget("notif_unread_{$userId}");

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        StoreNotification::where('user_id', $userId)
            ->unread()
            ->update(['read_at' => now()]);

        Cache::forget("notif_unread_{$userId}");

        return response()->json(['ok' => true]);
    }
}
