<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\SubscriptionResource;
use App\Models\Subscribe;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends BaseController
{
    /**
     * Display a listing of the subscriptions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $subscriptions = Subscribe::all();
        $user = auth()->user();

        foreach ($subscriptions as $subscription) {
            $activeSubscription = UserSubscription::where('user_id', $user->id)
                ->where('subscription_id', $subscription->id)
                ->where('expired_at', '>', now())
                ->first();

            $subscription->status = $activeSubscription ? 'Active' : 'Inactive';
            $subscription->payment_status = $activeSubscription ? $activeSubscription->payment_type : null;
        }

        return $this->sendResponse('Subscriptions retrieved successfully', [
            'subscriptions' => SubscriptionResource::collection($subscriptions),
        ]);
    }

    /**
     * Purchase a subscription.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function purchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'payment_id' => 'required|string',
            'subscription_id' => 'required|exists:subscribes,id',
            'payment_type' => 'required|in:pay_stack,nomba,stripe',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $user = $request->user();
        $subscriptionId = $request->subscription_id;

        // Map subscription IDs if needed
        if ($subscriptionId == "connect_premium_s03") {
            $subscriptionId = 8;
        } elseif ($subscriptionId == "connect_travel_s02") {
            $subscriptionId = 4;
        } elseif ($subscriptionId == "connect_boost_s04") {
            $subscriptionId = 9;
        } elseif ($subscriptionId == "connect_unlimited_s01") {
            $subscriptionId = 6;
        }

        // Check if the user already has an active subscription
        $activeSubscription = UserSubscription::where('user_id', $user->id)
            ->where('subscription_id', $subscriptionId)
            ->where('expired_at', '>', now())
            ->first();

        if ($activeSubscription) {
            return $this->sendError('You already have an active subscription', null, 422);
        }

        // Create the subscription
        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'customer_id' => $request->payment_id,
            'payment_type' => $request->payment_type,
            'card_holder_name' => 'User',
            'subscription_id' => $subscriptionId,
            'stripe_response' => $request->payment_id,
            'email' => $user->email,
        ]);

        return $this->sendResponse('Subscription purchased successfully', [
            'subscription' => $subscription,
        ]);
    }

    /**
     * Cancel a subscription.
     *
     * @param Request $request
     * @param int $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request, $subscriptionId)
    {
        $validator = Validator::make($request->all(), [
            'payment_type' => 'required|in:pay_stack,nomba,stripe',
            'stripe_subscription_id' => 'required_if:payment_type,stripe',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $user = $request->user();

        // Find the active subscription
        $activeSubscription = UserSubscription::where('user_id', $user->id)
            ->where('subscription_id', $subscriptionId)
            ->where('payment_type', $request->payment_type)
            ->where('expired_at', '>', now())
            ->first();

        if (!$activeSubscription) {
            return $this->sendError('Subscription not found or already expired', null, 404);
        }

        // If it's a Stripe subscription, cancel it through the Stripe API
        if ($request->payment_type === 'stripe' && $request->stripe_subscription_id) {
            try {
                \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
                $subscription = \Stripe\Subscription::retrieve($request->stripe_subscription_id);
                $subscription->cancel();
            } catch (\Exception $e) {
                return $this->sendError('Failed to cancel Stripe subscription: ' . $e->getMessage(), null, 500);
            }
        }

        // Update the subscription to be expired
        $activeSubscription->update([
            'expired_at' => now(),
        ]);

        return $this->sendResponse('Subscription cancelled successfully');
    }

    /**
     * Restore subscriptions (for app reinstalls).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request)
    {
        $user = $request->user();
        $activeSubscriptions = UserSubscription::where('user_id', $user->id)
            ->where('expired_at', '>', now())
            ->get();

        return $this->sendResponse('Subscriptions restored successfully', [
            'subscriptions' => $activeSubscriptions,
        ]);
    }

    /**
     * Activate a premium boost.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateBoost(Request $request)
    {
        $user = $request->user();

        // Check if the user has a premium subscription
        $premiumSubscription = UserSubscription::where('user_id', $user->id)
            ->where('subscription_id', 8) // Premium subscription ID
            ->where('expired_at', '>', now())
            ->first();

        if (!$premiumSubscription) {
            return $this->sendError('You need to purchase a premium subscription first', null, 422);
        }

        // Check if the user already has an active boost
        $activeBoost = UserSubscription::where('user_id', $user->id)
            ->where('subscription_id', 9) // Boost subscription ID
            ->where('expired_at', '>', now())
            ->first();

        if ($activeBoost) {
            return $this->sendError('You already have an active boost', null, 422);
        }

        // Check if the user has used the maximum number of boosts for this premium subscription
        $boostCount = UserSubscription::where('user_id', $user->id)
            ->where('subscription_id', 9) // Boost subscription ID
            ->where('premium_id', 8) // Premium subscription ID
            ->where('parent_id', $premiumSubscription->id)
            ->count();

        if ($boostCount >= 2) {
            return $this->sendError('You have already used the maximum number of boosts for this premium subscription', null, 422);
        }

        // Create the boost
        $boost = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 9, // Boost subscription ID
            'premium_id' => 8, // Premium subscription ID
            'parent_id' => $premiumSubscription->id,
            'payment_type' => 'premium_boost',
            'email' => $user->email,
        ]);

        return $this->sendResponse('Boost activated successfully', [
            'boost' => $boost,
        ]);
    }
}
