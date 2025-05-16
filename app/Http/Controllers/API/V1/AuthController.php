<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user
     *
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register($request->validated());
        $token = $this->authService->createToken($user);

        return $this->sendResponse('User registered successfully', [
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
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

        return $this->sendResponse('Login successful', [
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Logout user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse('Logout successful');
    }

    /**
     * Get authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return $this->sendResponse('User retrieved successfully', [
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Send password reset link
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
