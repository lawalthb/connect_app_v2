<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscribe;
use App\Models\UserSubscription;
use App\Helpers\UserSubscriptionHelper;
use App\Helpers\NombaPyamentHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription as StripeSubscription;
use Exception;

class SubscriptionController extends Controller
{
    public $successStatus = 200;

    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    /**
     * Get all subscription plans
     */
    public function index()
    {
        try {
            $auth = auth()->user();
            $plans = Subscribe::active()->ordered()->get();

            // Add user's current subscription status to each plan
            foreach ($plans as $plan) {
                $userSubscription = UserSubscriptionHelper::getPremiumByUserId($auth->id, $plan->id);
                $plan->is_subscribed = $userSubscription ? true : false;
                $plan->subscription_status = $userSubscription ? $userSubscription->status : null;
                $plan->expires_at = $userSubscription ? $userSubscription->expires_at : null;
            }

            return response()->json([
                'status' => 1,
                'message' => 'Subscription plans retrieved successfully',
                'data' => $plans
            ], $this->successStatus);
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving subscription plans: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Get user's active subscriptions
     */
    public function userSubscriptions()
    {
        try {
            $auth = auth()->user();
            $subscriptions = UserSubscriptionHelper::getActiveSubscriptionsWithDetails($auth->id);

            return response()->json([
                'status' => 1,
                'message' => 'User subscriptions retrieved successfully',
                'data' => $subscriptions
            ], $this->successStatus);
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving user subscriptions: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Initialize payment for subscription (Stripe)
     */
    public function initializeStripePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|exists:subscribes,id',
            'payment_method_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
                'data' => []
            ], $this->successStatus);
        }

        try {
            $auth = auth()->user();
            $subscription = Subscribe::findOrFail($request->subscription_id);

            // Check if user already has this subscription
            if (UserSubscriptionHelper::hasSubscription($auth->id, $subscription->id)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'You already have an active subscription for this plan',
                    'data' => []
                ], $this->successStatus);
            }

            // Create or retrieve Stripe customer
            $customer = Customer::create([
                'email' => $auth->email,
                'name' => $auth->name,
                'payment_method' => $request->payment_method_id,
                'invoice_settings' => [
                    'default_payment_method' => $request->payment_method_id,
                ],
            ]);

            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => $subscription->price * 100, // Amount in cents
                'currency' => strtolower($subscription->currency),
                'customer' => $customer->id,
                'payment_method' => $request->payment_method_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => env('APP_URL') . '/subscription/success',
            ]);

            if ($paymentIntent->status === 'succeeded') {
                // Create user subscription record
                $subscriptionData = [
                    'user_id' => $auth->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $subscription->price,
                    'currency' => $subscription->currency,
                    'payment_method' => 'stripe',
                    'payment_status' => 'completed',
                    'transaction_reference' => $paymentIntent->id,
                    'customer_id' => $customer->id,
                    'payment_details' => json_encode($paymentIntent->toArray()),
                    'status' => 'active'
                ];

                $userSubscriptionId = UserSubscriptionHelper::insert($subscriptionData);

                return response()->json([
                    'status' => 1,
                    'message' => 'Payment successful! Subscription activated.',
                    'data' => [
                        'payment_intent_id' => $paymentIntent->id,
                        'subscription_id' => $userSubscriptionId,
                        'status' => 'succeeded'
                    ]
                ], $this->successStatus);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Payment failed or requires additional authentication',
                    'data' => [
                        'payment_intent_id' => $paymentIntent->id,
                        'client_secret' => $paymentIntent->client_secret,
                        'status' => $paymentIntent->status
                    ]
                ], $this->successStatus);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Payment failed: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Initialize payment for subscription (Nomba)
     */
    public function initializeNombaPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|exists:subscribes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
                'data' => []
            ], $this->successStatus);
        }

        try {
            $auth = auth()->user();
            $subscription = Subscribe::findOrFail($request->subscription_id);

            // Check if user already has this subscription
            if (UserSubscriptionHelper::hasSubscription($auth->id, $subscription->id)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'You already have an active subscription for this plan',
                    'data' => []
                ], $this->successStatus);
            }

            // Convert USD to NGN (approximate rate)
            $amountInNGN = $subscription->price * 1500; // 1 USD = 1500 NGN (update as needed)

            $nombaHelper = new NombaPyamentHelper();
            $callbackUrl = env('APP_URL') . '/api/v1/subscriptions/nomba/callback';

            $paymentResult = $nombaHelper->processPayment(
                $amountInNGN,
                $auth->email,
                $callbackUrl,
                'SUB_' . $subscription->id . '_' . $auth->id . '_' . time()
            );

            if ($paymentResult['status']) {
                // Store pending subscription
                $subscriptionData = [
                    'user_id' => $auth->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $subscription->price,
                    'currency' => $subscription->currency,
                    'payment_method' => 'nomba',
                    'payment_status' => 'pending',
                    'transaction_reference' => $paymentResult['orderReference'],
                    'payment_details' => json_encode($paymentResult),
                    'status' => 'pending'
                ];

                $userSubscriptionId = UserSubscriptionHelper::insert($subscriptionData);

                return response()->json([
                    'status' => 1,
                    'message' => 'Payment initialized successfully',
                    'data' => [
                        'checkout_url' => $paymentResult['checkoutLink'],
                        'order_reference' => $paymentResult['orderReference'],
                        'subscription_id' => $userSubscriptionId
                    ]
                ], $this->successStatus);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to initialize payment: ' . $paymentResult['message'],
                    'data' => []
                ], $this->successStatus);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Payment initialization failed: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Handle Nomba payment callback
     */
    public function handleNombaCallback(Request $request)
    {
        try {
            $orderReference = $request->query('orderReference');

            if (!$orderReference) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Order reference not provided',
                    'data' => []
                ], $this->successStatus);
            }

            $nombaHelper = new NombaPyamentHelper();
            $verificationResult = $nombaHelper->verifyPayment($orderReference);

            // Find the pending subscription
            $userSubscription = UserSubscription::where('transaction_reference', $orderReference)
                ->where('payment_status', 'pending')
                ->first();

            if (!$userSubscription) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Subscription not found',
                    'data' => []
                ], $this->successStatus);
            }

            if ($verificationResult['status']) {
                // Payment successful - activate subscription
                UserSubscriptionHelper::update([
                    'payment_status' => 'completed',
                    'status' => 'active',
                    'payment_details' => json_encode($verificationResult)
                ], ['id' => $userSubscription->id]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Payment verified and subscription activated successfully',
                    'data' => [
                        'subscription_id' => $userSubscription->id,
                        'status' => 'active'
                    ]
                ], $this->successStatus);
            } else {
                // Payment failed
                UserSubscriptionHelper::update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                    'payment_details' => json_encode($verificationResult)
                ], ['id' => $userSubscription->id]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Payment verification failed',
                    'data' => []
                ], $this->successStatus);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Callback processing failed: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_reference' => 'required|string',
            'payment_method' => 'required|in:stripe,nomba'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
                'data' => []
            ], $this->successStatus);
        }

        try {
            $userSubscription = UserSubscription::where('transaction_reference', $request->transaction_reference)
                ->where('user_id', auth()->id())
                ->first();

            if (!$userSubscription) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Transaction not found',
                    'data' => []
                ], $this->successStatus);
            }

            if ($request->payment_method === 'nomba') {
                $nombaHelper = new NombaPyamentHelper();
                $verificationResult = $nombaHelper->verifyPayment($request->transaction_reference);

                if ($verificationResult['status'] && $userSubscription->payment_status === 'pending') {
                    UserSubscriptionHelper::update([
                        'payment_status' => 'completed',
                        'status' => 'active',
                        'payment_details' => json_encode($verificationResult)
                    ], ['id' => $userSubscription->id]);
                }
            }

            return response()->json([
                'status' => 1,
                'message' => 'Payment status retrieved successfully',
                'data' => [
                    'payment_status' => $userSubscription->payment_status,
                    'subscription_status' => $userSubscription->status,
                    'expires_at' => $userSubscription->expires_at
                ]
            ], $this->successStatus);
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Verification failed: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request, $subscriptionId)
    {
        try {
            $auth = auth()->user();

            $userSubscription = UserSubscription::where('user_id', $auth->id)
                ->where('subscription_id', $subscriptionId)
                ->where('status', 'active')
                ->first();

            if (!$userSubscription) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Active subscription not found',
                    'data' => []
                ], $this->successStatus);
            }

            // Cancel on payment gateway if needed
            if ($userSubscription->payment_method === 'stripe' && $userSubscription->customer_id) {
                try {
                    // Cancel Stripe subscription if it exists
                    $stripeSubscriptions = StripeSubscription::all([
                        'customer' => $userSubscription->customer_id,
                        'status' => 'active'
                    ]);

                    foreach ($stripeSubscriptions->data as $stripeSub) {
                        $stripeSub->cancel();
                    }
                } catch (Exception $e) {
                    // Log error but continue with local cancellation
                    \Log::error('Stripe cancellation error: ' . $e->getMessage());
                }
            }

            // Update local subscription
            UserSubscriptionHelper::update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ], ['id' => $userSubscription->id]);

            return response()->json([
                'status' => 1,
                'message' => 'Subscription cancelled successfully',
                'data' => []
            ], $this->successStatus);
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Cancellation failed: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Restore purchases (for iOS)
     */
    public function restore()
    {
        try {
            $auth = auth()->user();
            UserSubscriptionHelper::getPremiumByUserIdRestore($auth->id);

            return response()->json([
                'status' => 1,
                'message' => 'Purchases restored successfully',
                'data' => []
            ], $this->successStatus);
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Restore failed: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Activate boost (for premium users)
     */
    public function activateBoost()
    {
        try {
            $auth = auth()->user();

            // Check if user has premium subscription
            $premiumSubscription = UserSubscriptionHelper::getPremiumByUserId($auth->id, 3);
            if (!$premiumSubscription) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Premium subscription required to activate boost',
                    'data' => []
                ], $this->successStatus);
            }

            // Check current boost usage
            $currentBoostCount = UserSubscriptionHelper::getCheckBoostPremium($premiumSubscription->id);
            if ($currentBoostCount >= 2) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Maximum boost usage reached (2 per month)',
                    'data' => []
                ], $this->successStatus);
            }

            // Check if boost is already active
            $activeBoost = UserSubscriptionHelper::getPremiumByUserId($auth->id, 4);
            if ($activeBoost) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Boost is already active',
                    'data' => []
                ], $this->successStatus);
            }

            // Create boost subscription
            $boostData = [
                'user_id' => $auth->id,
                'subscription_id' => 4, // Boost subscription ID
                'amount' => 0, // Free for premium users
                'currency' => 'USD',
                'payment_method' => 'premium_benefit',
                'payment_status' => 'completed',
                'transaction_reference' => 'PREMIUM_BOOST_' . time(),
                'status' => 'active',
                'parent_id' => $premiumSubscription->id
            ];

            $boostSubscriptionId = UserSubscriptionHelper::insert($boostData);

            return response()->json([
                'status' => 1,
                'message' => 'Boost activated successfully',
                'data' => [
                    'boost_subscription_id' => $boostSubscriptionId,
                    'remaining_boosts' => 2 - ($currentBoostCount + 1)
                ]
            ], $this->successStatus);
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Boost activation failed: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * Get subscription features and limits
     */
    public function getFeatures()
    {
        try {
            $auth = auth()->user();
            $activeSubscriptions = UserSubscriptionHelper::getActiveSubscriptionsWithDetails($auth->id);

            $features = [
                'unlimited_swipes' => false,
                'travel_connections' => false,
                'boost_available' => false,
                'daily_swipe_limit' => 50,
                'boost_count_used' => 0,
                'boost_count_limit' => 0
            ];

            foreach ($activeSubscriptions as $subscription) {
                $plan = $subscription->subscription;

                if ($plan->slug === 'connect-unlimited' || $plan->slug === 'connect-premium') {
                    $features['unlimited_swipes'] = true;
                    $features['daily_swipe_limit'] = 999999;
                }

                if ($plan->slug === 'connect-travel' || $plan->slug === 'connect-premium') {
                    $features['travel_connections'] = true;
                }

                if ($plan->slug === 'connect-boost' || $plan->slug === 'connect-premium') {
                    $features['boost_available'] = true;
                }

                if ($plan->slug === 'connect-premium') {
                    $features['boost_count_limit'] = 2;
                    $features['boost_count_used'] = UserSubscriptionHelper::getCheckBoostPremium($subscription->id);
                }
            }

            return response()->json([
                'status' => 1,
                'message' => 'Features retrieved successfully',
                'data' => $features
            ], $this->successStatus);
        } catch (Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve features: ' . $e->getMessage(),
                'data' => []
            ], $this->successStatus);
        }
    }
}
