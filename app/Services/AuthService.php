<?php
namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    /**
     * Register a new user
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'username' => $data['username'] ?? null,
            'bio' => $data['bio'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);
    }

    /**
     * Attempt to log in a user
     *
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return User|null
     */
    public function attemptLogin(string $email, string $password, bool $remember = false): ?User
    {
        if (!Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return null;
        }

        $user = User::where('email', $email)->first();

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        return $user;
    }

    /**
     * Create a token for the user
     *
     * @param User $user
     * @param bool $remember
     * @return string
     */
    public function createToken(User $user, bool $remember = false): string
    {
        // Revoke previous tokens if needed
        // $user->tokens()->delete();

        // Create new token
        $tokenExpiration = $remember ? now()->addMonths(6) : now()->addDay();

        $token = $user->createToken('auth_token', ['*'], $tokenExpiration);

        return $token->plainTextToken;
    }

    /**
     * Send password reset link
     *
     * @param string $email
     * @return bool
     */
    public function sendPasswordResetLink(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        // Generate token
        $token = Str::random(60);

        // Store token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        // Send notification
        $user->notify(new ResetPasswordNotification($token));

        return true;
    }

    /**
     * Reset password
     *
     * @param string $email
     * @param string $token
     * @param string $password
     * @return bool
     */
    public function resetPassword(string $email, string $token, string $password): bool
    {
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$resetRecord || !Hash::check($token, $resetRecord->token)) {
            return false;
        }

        // Check if token is expired (60 minutes)
        if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
            return false;
        }

        // Update password
        $user = User::where('email', $email)->first();
        $user->update(['password' => Hash::make($password)]);

        // Delete token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return true;
    }
}
