<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    /**
     * Verify a reCAPTCHA token
     *
     * @param string $token
     * @return bool
     */
    public function verify(string $token): bool
    {
        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            $data = $response->json();

            // Minimum score threshold (0.0 to 1.0)
            $minimumScore = 0.5;

            return $data['success'] && $data['score'] >= $minimumScore;
        } catch (\Exception $e) {
            Log::error('reCAPTCHA verification failed: ' . $e->getMessage());

            // In production, you might want to return false here
            // For development, you might want to return true to not block testing
            return config('app.env') === 'production' ? false : true;
        }
    }
}