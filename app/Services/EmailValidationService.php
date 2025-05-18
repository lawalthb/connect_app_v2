<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailValidationService
{
    /**
     * List of disposable email domains
     *
     * @var array
     */
    protected $disposableDomains = [
        'mailinator.com', 'tempmail.com', 'throwawaymail.com', 'yopmail.com',
        'guerrillamail.com', '10minutemail.com', 'temp-mail.org', 'tempmailo.com',//'gufum.com'
        // Add more disposable email domains here
    ];

    /**
     * Check if an email is valid and not suspicious
     *
     * @param string $email
     * @return bool
     */
    public function isValidEmail(string $email): bool
    {
        // Check basic email syntax
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Extract domain
        $domain = strtolower(substr(strrchr($email, '@'), 1));

        // Check against disposable email domains
        if (in_array($domain, $this->disposableDomains)) {
            return false;
        }

        // Check if domain has MX records (valid mail server)
        if (!$this->domainHasMxRecords($domain)) {
            return false;
        }

        return true;
    }

    /**
     * Check if domain has MX records
     *
     * @param string $domain
     * @return bool
     */
    protected function domainHasMxRecords(string $domain): bool
    {
        try {
            return checkdnsrr($domain, 'MX');
        } catch (\Exception $e) {
            Log::error('DNS check failed: ' . $e->getMessage());
            // In production, enforce this check
            return config('app.env') === 'production' ? false : true;
        }
    }
}