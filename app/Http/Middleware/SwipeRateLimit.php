<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\UserHelper;

class SwipeRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            // Check if user can swipe
            if (!UserHelper::canUserSwipe($user->id)) {
                $swipeStats = UserHelper::getSwipeStats($user->id);

                return response()->json([
                    'status' => 0,
                    'message' => 'Daily swipe limit reached. You have used ' . $swipeStats->total_swipes . ' out of ' . $swipeStats->daily_limit . ' swipes today.',
                    'data' => [
                        'swipes_used' => $swipeStats->total_swipes,
                        'daily_limit' => $swipeStats->daily_limit,
                        'remaining_swipes' => $swipeStats->remaining_swipes,
                        'resets_at' => now()->addDay()->startOfDay()->toISOString()
                    ]
                ], 429);
            }

            return $next($request);
        } catch (\Exception $e) {
            // Log the error and allow the request to continue
            \Log::error('SwipeRateLimit middleware error: ' . $e->getMessage());

            return $next($request);
        }
    }
}
