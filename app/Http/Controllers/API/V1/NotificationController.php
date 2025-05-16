<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * Display a listing of the notifications.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = Notification::where('user_id', $user->id)
            ->where('deleted_flag', 'N')
            ->latest()
            ->paginate(20);

        // Mark all notifications as read
        Notification::where('user_id', $user->id)
            ->where('receive_flag', 'N')
            ->update(['receive_flag' => 'Y']);

        return $this->sendResponse('Notifications retrieved successfully', [
            'notifications' => NotificationResource::collection($notifications),
            'pagination' => [
                'total' => $notifications->total(),
                'count' => $notifications->count(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'total_pages' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Get the count of unread notifications.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = Notification::where('user_id', $user->id)
            ->where('receive_flag', 'N')
            ->where('deleted_flag', 'N')
            ->count();

        return $this->sendResponse('Unread notifications count retrieved successfully', [
            'count' => $count,
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        Notification::where('user_id', $user->id)
            ->where('receive_flag', 'N')
            ->update(['receive_flag' => 'Y']);

        return $this->sendResponse('All notifications marked as read');
    }
}
