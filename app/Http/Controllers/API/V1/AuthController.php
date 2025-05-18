<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use App\Mail\WelcomeEmail;
use App\Mail\VerificationEmail;
use App\Models\User;
use App\Services\EmailValidationService;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Mail;


class AuthController extends BaseController
{
    protected $authService;
    protected $recaptchaService;
    protected $emailValidationService;

    public function __construct(
        AuthService $authService,
        RecaptchaService $recaptchaService,
        EmailValidationService $emailValidationService
    ) {
        $this->authService = $authService;
        $this->recaptchaService = $recaptchaService;
        $this->emailValidationService = $emailValidationService;
    }

    /**
     * Register a new user
     *
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Verify reCAPTCHA
            // if (!$this->recaptchaService->verify($request->recaptcha_token)) {
            //     return $this->sendError('Bot verification failed. Please try again.', null, 400);
            // }

            // Check for suspicious email
            if (!$this->emailValidationService->isValidEmail($request->email)) {
                return $this->sendError('Invalid or suspicious email address.', null, 400);
            }

            $user = $this->authService->register($request->validated());

            // Generate OTP for email verification
            $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            // Store OTP in database
            $user->email_otp = $otp;
            $user->email_otp_expires_at = now()->addHours(1); // OTP expires in 1 hour
            $user->save();

            // Queue emails instead of sending them immediately
            try {
                // The emails will be sent in the background
                Mail::to($user->email)->queue(new WelcomeEmail($user));
                Mail::to($user->email)->queue(new VerificationEmail($user, $otp));
            } catch (\Exception $mailException) {
                // Log the email error but don't fail the registration
                \Log::error('Failed to queue registration emails: ' . $mailException->getMessage());
            }

            $token = $this->authService->createToken($user);

            return $this->sendResponse('User registered successfully. Please check your email for verification instructions.', [
                'user' => new UserResource($user),
                'token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return $this->sendError('Registration failed: ' . $e->getMessage(), null, 500);
        }
    }
    /**
     * Login user
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        try {
            $user = $this->authService->attemptLogin(
                $request->email,
                $request->password,
                $request->remember_me ?? false
            );

            if (!$user) {
                return $this->sendError('Invalid credentials', null, 401);
            }

            $token = $this->authService->createToken($user, $request->remember_me ?? false);

            return $this->sendResponse('Login successful', [
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), null, $e->getCode());
        } catch (\Exception $e) {
            return $this->sendError('Login failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Send forgot password OTP
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $otp = $this->authService->generatePasswordResetOTP($request->email);

            if (!$otp) {
                return $this->sendError('User not found', null, 404);
            }

            // Here you would normally send the OTP via email
            // For example: Mail::to($request->email)->send(new ResetPasswordOTPMail($otp));

            return $this->sendResponse(
                'Password reset OTP has been sent to your email',
                ['email' => $request->email]
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to send reset OTP: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Verify password reset OTP
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyResetOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:4'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $isValid = $this->authService->verifyPasswordResetOTP(
            $request->email,
            $request->otp
        );

        if (!$isValid) {
            return $this->sendError('Invalid OTP', null, 400);
        }

        return $this->sendResponse('OTP verified successfully', null);
    }

    /**
     * Reset password with OTP
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:4',
            'password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $success = $this->authService->resetPasswordWithOTP(
            $request->email,
            $request->otp,
            $request->password
        );

        if (!$success) {
            return $this->sendError('Invalid OTP or reset token expired', null, 400);
        }

        return $this->sendResponse('Password has been reset successfully', null);
    }

    /**
     * Verify email with OTP
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:4'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->sendError('User not found', null, 404);
        }

        if ($user->email_otp != $request->otp) {
            return $this->sendError('Invalid OTP', null, 400);
        }

        if ($user->email_otp_expires_at < now()) {
            return $this->sendError('OTP has expired', null, 400);
        }

        // Mark email as verified
        $user->email_verified_at = now();
        $user->email_otp = null;
        $user->email_otp_expires_at = null;
        $user->save();

        return $this->sendResponse('Email verified successfully', [
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Resend email verification OTP
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $user = \App\Models\User::where('email', $request->email)->first();

            if ($user->email_verified_at) {
                return $this->sendError('Email already verified', null, 400);
            }

            $otp = $this->authService->generateEmailVerificationOTP($user);


            // Queue the email for sending
            Mail::to($user->email)->queue(new VerificationEmail($user, $otp));
            return $this->sendResponse(
                'Verification OTP has been sent to your email',
                ['email' => $user->email]
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to send verification OTP: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Redirect to OAuth provider
     *
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function redirectToProvider($provider)
    {
        $validProviders = ['google', 'facebook', 'twitter', 'apple'];

        if (!in_array($provider, $validProviders)) {
            return $this->sendError('Unsupported social provider', null, 400);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle provider callback
     *
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();

            $user = $this->authService->handleSocialLogin(
                $provider,
                null,
                [
                    'id' => $socialUser->getId(),
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'username' => $socialUser->getNickname()
                ]
            );

            $token = $this->authService->createToken($user, true);

            // If API request, return JSON
            if (request()->expectsJson()) {
                return $this->sendResponse('Social login successful', [
                    'user' => new UserResource($user),
                    'token' => $token,
                ]);
            }

            // For web flow, redirect with token
            return redirect()->to(config('app.frontend_url') . '/auth/social-callback?token=' . $token);

        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return $this->sendError('Social login failed: ' . $e->getMessage(), null, 500);
            }

            return redirect()->to(config('app.frontend_url') . '/auth/social-callback?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Handle social login from mobile app
     *
     * @param Request $request
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleSocialLoginFromApp(Request $request, $provider)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'device_token' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $user = $this->authService->handleSocialLogin($provider, $request->access_token);

            // Update device token if provided
            if ($request->device_token) {
                $user->device_token = $request->device_token;
                $user->save();
            }

            $token = $this->authService->createToken($user, true);

            return $this->sendResponse('Social login successful', [
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Social login failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Handle social login with user data directly from app
     * Used when social SDK is handled on client side
     *
     * @param Request $request
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleSocialLoginWithUserData(Request $request, $provider)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'email' => 'required|email',
            'name' => 'required|string',
            'avatar' => 'nullable|string',
            'device_token' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $user = $this->authService->handleSocialLogin(
                $provider,
                null,
                [
                    'id' => $request->id,
                    'name' => $request->name,
                    'email' => $request->email,
                    'avatar' => $request->avatar,
                    'username' => $request->username ?? null
                ]
            );

            // Update device token if provided
            if ($request->device_token) {
                $user->device_token = $request->device_token;
                $user->save();
            }

            $token = $this->authService->createToken($user, true);

            return $this->sendResponse('Social login successful', [
                'user' => new UserResource($user),
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Social login failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Logout user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            // Set user offline
            $user->is_online = false;
            $user->save();

            // Revoke the token
            $request->user()->currentAccessToken()->delete();

            return $this->sendResponse('Logged out successfully', null);
        } catch (\Exception $e) {
            return $this->sendError('Logout failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get authenticated user profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUser(Request $request)
    {
        try {
            $user = $request->user();

            // Update last activity
            $user->last_activity_at = now();
            $user->save();

            // Load relevant relationships
            $user->load(['country', 'socialCircles']);

            return $this->sendResponse('User profile retrieved successfully', [
                'user' => new UserResource($user)
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve user profile: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update user password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $user = $request->user();

            // Verify current password
            if (!\Hash::check($request->current_password, $user->password)) {
                return $this->sendError('Current password is incorrect', null, 400);
            }

            // Update password
            $user->password = \Hash::make($request->password);
            $user->save();

            return $this->sendResponse('Password updated successfully', null);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update password: ' . $e->getMessage(), null, 500);
        }
    }

    /**
 * Resend verification email
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function resendVerificationEmail(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email',
    ]);

    if ($validator->fails()) {
        return $this->sendError('Validation Error', $validator->errors(), 422);
    }

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return $this->sendError('User not found', null, 404);
    }

    if ($user->email_verified_at) {
        return $this->sendError('Email already verified', null, 400);
    }

    // Generate new OTP
    $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $user->email_otp = $otp;
    $user->email_otp_expires_at = now()->addHours(1);
    $user->save();

    // Send verification email with new OTP
    Mail::to($user->email)->send(new VerificationEmail($user, $otp));

    return $this->sendResponse('Verification email sent successfully', [
        'email' => $user->email
    ]);
}
}

