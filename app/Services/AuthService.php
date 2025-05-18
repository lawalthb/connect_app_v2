<?php
namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use App\Helpers\SocialCircleHelper;
use App\Exceptions\AuthenticationException;



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
        // Generate OTP for email verification if not already set
        if (!isset($data['email_otp'])) {
            $data['email_otp'] = sprintf("%04d", mt_rand(1000, 9999));
        }


        if (isset($data['password']) && !$this->isHashedPassword($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Create the user with all allowed attributes
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'username' => $data['username'] ?? null,
            'bio' => $data['bio'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'interests' => $data['interests'] ?? null,
            'social_links' => $data['social_links'] ?? null,
            'profile' => $data['profile'] ?? null,
            'profile_url' => $data['profile_url'] ?? null,
            'device_token' => $data['device_token'] ?? null,
            'email_otp' => $data['email_otp'],
            'social_id' => $data['social_id'] ?? null,
            'social_type' => $data['social_type'] ?? null,
        ]);

        // Assign social circles if provided, otherwise assign default social circle (ID: 26)
        if (isset($data['social_circles']) && is_array($data['social_circles']) && !empty($data['social_circles'])) {
            $this->assignSocialCircles($user, $data['social_circles']);
        } else {
            // Assign default social circle (ID: 26)
            $this->assignSocialCircles($user, [26]);
        }
        //$this->assignDefaultSocialCircles($user);

        return $user;
    }


    /**
 * Assign specific social circles to a user
 *
 * @param User $user
 * @param array $socialCircleIds
 * @return void
 */
private function assignSocialCircles(User $user, array $socialCircleIds): void
{
    foreach ($socialCircleIds as $socialCircleId) {
        // Validate that the social circle ID is numeric
        if (is_numeric($socialCircleId)) {
            DB::table('user_social_circles')->insert([
                'user_id' => $user->id,
                'social_id' => $socialCircleId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
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
        // Check for deleted account first - use withTrashed() to include soft-deleted records
        $user = User::withTrashed()
            ->where('email', $email)
            ->where(function($query) {
                $query->where('deleted_flag', 'Y')
                      ->orWhereNotNull('deleted_at');
            })
            ->first();

        if ($user) {
            throw new AuthenticationException(
                'This account has been deleted. Please contact support if you wish to recover it.',
                403
            );
        }
        if (!Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return null;
        }

        $user = User::where('email', $email)->first();

        // Check if user is banned
        if ($user->isBanned()) {
            throw new AuthenticationException('Your account has been suspended', 403);
        }

        // Update last login
        $this->updateLastLogin($user);

        return $user;
    }

    /**
     * Update user's last login information
     *
     * @param User $user
     * @return void
     */
    public function updateLastLogin(User $user): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'is_online' => true,
            'login_count' => $user->login_count + 1,
            'last_activity_at' => now(),
        ]);
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
     * Generate OTP for password reset
     *
     * @param string $email
     * @return string|null
     */
    public function generatePasswordResetOTP(string $email): ?string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return null;
        }

        $otp = sprintf("%04d", mt_rand(1000, 9999));
        $user->reset_otp = $otp;
        $user->save();

        return $otp;
    }

    /**
     * Verify password reset OTP
     *
     * @param string $email
     * @param string $otp
     * @return bool
     */
    public function verifyPasswordResetOTP(string $email, string $otp): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user || $user->reset_otp !== $otp) {
            return false;
        }

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

    /**
     * Reset password with OTP
     *
     * @param string $email
     * @param string $otp
     * @param string $password
     * @return bool
     */
    public function resetPasswordWithOTP(string $email, string $otp, string $password): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user || $user->reset_otp !== $otp) {
            return false;
        }

        // Update password
        $user->password = Hash::make($password);
        $user->reset_otp = null;
        $user->save();

        return true;
    }

    /**
     * Generate email verification OTP
     *
     * @param User $user
     * @return string
     */
    public function generateEmailVerificationOTP(User $user): string
    {
        $otp = sprintf("%04d", mt_rand(1000, 9999));
        $user->email_otp = $otp;
        $user->save();

        return $otp;
    }

    /**
     * Verify email with OTP
     *
     * @param string $email
     * @param string $otp
     * @return bool
     */
    public function verifyEmail(string $email, string $otp): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user || $user->email_otp !== $otp) {
            return false;
        }

        // Verify email
        $user->email_verified_at = now();
        $user->is_verified = true;
        $user->verified_at = now();
        $user->email_otp = null;
        $user->save();

        return true;
    }

    /**
     * Handle social login
     *
     * @param string $provider
     * @param string $accessToken
     * @param array $userData
     * @return User
     */
    public function handleSocialLogin(string $provider, string $accessToken = null, array $userData = null): User
    {
        // Get user data either from token or from provided user data
        if ($accessToken) {
            $providerUser = Socialite::driver($provider)->userFromToken($accessToken);
            $email = $providerUser->getEmail();
            $socialId = $providerUser->getId();
            $name = $providerUser->getName();
            $avatar = $providerUser->getAvatar();
        } elseif ($userData) {
            $email = $userData['email'] ?? null;
            $socialId = $userData['id'] ?? null;
            $name = $userData['name'] ?? null;
            $avatar = $userData['avatar'] ?? null;
        } else {
            throw new AuthenticationException('Invalid social login data', 400);
        }

        // Ensure we have an email
        if (!$email) {
            throw new AuthenticationException('Email is required for social login', 400);
        }

        // Check if user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            // Update social info if needed
            if ($user->social_id != $socialId || $user->social_type != $provider) {
                $user->social_id = $socialId;
                $user->social_type = $provider;
                $user->save();
            }
        } else {
            // Create new user
            $username = $this->generateUniqueUsername(
                $userData['username'] ?? null ?:
                strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name))
            );

            $user = $this->register([
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => Hash::make(Str::random(16)),
                'social_id' => $socialId,
                'social_type' => $provider,
                'email_verified_at' => now(),
                'is_verified' => true,
                'verified_at' => now(),
                'profile' => $avatar,
                'device_token' => request('device_token')
            ]);
        }

        // Update last login
        $this->updateLastLogin($user);

        return $user;
    }

    /**
     * Generate a unique username
     *
     * @param string $baseUsername
     * @return string
     */
    public function generateUniqueUsername(string $baseUsername): string
    {
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Assign default social circles to a user
     *
     * @param User $user
     * @return void
     */
    private function assignDefaultSocialCircles(User $user): void
    {
        if (class_exists('App\Helpers\SocialCircleHelper')) {
            SocialCircleHelper::assignDefaultCirclesToUser($user);
        }
    }

    /**
     * Check if a password is already hashed
     *
     * @param string $password
     * @return bool
     */
    private function isHashedPassword(string $password): bool
    {
        return strlen($password) === 60 && preg_match('/^\$2y\$/', $password);
    }
}
