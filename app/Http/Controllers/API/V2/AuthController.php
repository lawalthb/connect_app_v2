<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user with enhanced features
     */
    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register($request->validated());
        $token = $this->authService->createToken($user);

        // V2 enhancement: Send verification email
        $this->authService->sendEmailVerificationLink($user);

        return $this->sendResponse('User registered successfully', [
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user with enhanced features
     */
    public function login(LoginRequest $request)
    {
        $user = $this->authService->attemptLogin(
            $request->email,
            $request->password,
            $request->remember_me ?? false
        );

        if (!$user) {
            return $this->sendError('Invalid credentials', null, 401);
        }

        $token = $this->authService->createToken($user, $request->remember_me ?? false);

        // V2 enhancement: Include user permissions
        $permissions = $user->getAllPermissions()->pluck('name');

        return $this->sendResponse('Login successful', [
            'user' => new UserResource($user),
            'token' => $token,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        // V2 enhancement: Log the logout event
        activity()
            ->causedBy($request->user())
            ->log('User logged out');

        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse('Logout successful');
    }

    /**
     * Get authenticated user with enhanced details
     */
    public function user(Request $request)
    {
        $user = $request->user();

        // V2 enhancement: Include additional user data
        return $this->sendResponse('User retrieved successfully', [
            'user' => new UserResource($user),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $success = $this->authService->sendPasswordResetLink($request->email);

        if (!$success) {
            return $this->sendError('Failed to send password reset link');
        }

        return $this->sendResponse('Password reset link sent');
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $success = $this->authService->resetPassword(
            $request->email,
            $request->token,
            $request->password
        );

        if (!$success) {
            return $this->sendError('Invalid or expired token', null, 422);
        }

        return $this->sendResponse('Password reset successful');
    }
}
