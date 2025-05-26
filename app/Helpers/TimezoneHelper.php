<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User; // Import User model

class TimezoneHelper
{
    /**
     * Convert a datetime string or Carbon instance to the specified user's timezone.
     * If no user is provided, it defaults to the authenticated user's timezone.
     * If no authenticated user or user timezone is invalid, defaults to UTC.
     *
     * @param string|Carbon|null $datetime The datetime to convert.
     * @param User|null $targetUser The user whose timezone should be used for conversion.
     * @return Carbon|null Null if input datetime is null.
     */
    public static function convertToUserTimezone($datetime, ?User $targetUser = null): ?Carbon
    {
        if (is_null($datetime)) {
            return null;
        }

        $readerTimezone = config('app.timezone', 'UTC'); // Default to app's timezone or UTC

        if ($targetUser) {
            $tz = $targetUser->getTimezone(); // Use the getTimezone() method from User model
            if (self::isValidTimezone($tz)) {
                $readerTimezone = $tz;
            } else {
                Log::warning("Invalid timezone '{$tz}' for target user ID {$targetUser->id}. Defaulting to {$readerTimezone}.");
            }
        } elseif (Auth::check()) {
            /** @var User $authUser */
            $authUser = Auth::user();
            $tz = $authUser->getTimezone();
            if (self::isValidTimezone($tz)) {
                $readerTimezone = $tz;
            } else {
                Log::warning("Invalid timezone '{$tz}' for authenticated user ID {$authUser->id}. Defaulting to {$readerTimezone}.");
            }
        }

        try {
            // Ensure the input datetime is a Carbon instance, then convert timezone
            return Carbon::parse($datetime)->setTimezone($readerTimezone);
        } catch (\Exception $e) {
            Log::error("Timezone conversion error for datetime '{$datetime}' to '{$readerTimezone}': " . $e->getMessage(), [
                'datetime_original' => $datetime,
                'target_timezone' => $readerTimezone,
                'user_id' => $targetUser ? $targetUser->id : (Auth::check() ? Auth::id() : 'guest')
            ]);
            // Fallback: return the original datetime parsed, possibly in UTC or app default
            return Carbon::parse($datetime)->setTimezone(config('app.timezone', 'UTC'));
        }
    }

    /**
     * Format a datetime string or Carbon instance in the specified user's timezone.
     *
     * @param string|Carbon|null $datetime
     * @param string $format
     * @param User|null $targetUser
     * @return string|null
     */
    public static function formatInUserTimezone($datetime, string $format = 'Y-m-d H:i:s', ?User $targetUser = null): ?string
    {
        $carbonDate = self::convertToUserTimezone($datetime, $targetUser);
        return $carbonDate ? $carbonDate->format($format) : null;
    }

    /**
     * Get human-readable diff for a datetime string or Carbon instance in the user's timezone.
     *
     * @param string|Carbon|null $datetime
     * @param User|null $targetUser
     * @return string|null
     */
    public static function humanInUserTimezone($datetime, ?User $targetUser = null): ?string
    {
        $carbonDate = self::convertToUserTimezone($datetime, $targetUser);
        return $carbonDate ? $carbonDate->diffForHumans() : null;
    }

    /**
     * Check if a timezone identifier is valid.
     *
     * @param string|null $timezone
     * @return bool
     */
    public static function isValidTimezone($timezone): bool
    {
        if (empty($timezone)) {
            return false;
        }
        return in_array($timezone, timezone_identifiers_list());
    }

    /**
     * Get list of common timezones.
     * @return array
     */
    public static function getTimezonesList(): array
    {
        // This list can be customized or fetched dynamically
        return [
           // Universal
           'UTC' => 'UTC',
           'Etc/GMT+12' => 'GMT-12:00 International Date Line West',
           'Etc/GMT+1' => 'GMT-01:00',
           'Etc/GMT-1' => 'GMT+01:00',

           // North America
           'America/New_York' => 'Eastern Time (US & Canada)',
           'America/Detroit' => 'Eastern Time - Detroit',
           'America/Toronto' => 'Eastern Time - Toronto',
           'America/Chicago' => 'Central Time (US & Canada)',
           'America/Denver' => 'Mountain Time (US & Canada)',
           'America/Los_Angeles' => 'Pacific Time (US & Canada)',
           'America/Phoenix' => 'Arizona',
           'America/Anchorage' => 'Alaska',
           'America/Honolulu' => 'Hawaii',

           // South America
           'America/Sao_Paulo' => 'Brasilia',
           'America/Buenos_Aires' => 'Buenos Aires',
           'America/Bogota' => 'Bogotá',
           'America/Caracas' => 'Caracas',
           'America/Lima' => 'Lima',
           'America/Mexico_City' => 'Mexico City',

           // Europe
           'Europe/London' => 'London',
           'Europe/Paris' => 'Paris',
           'Europe/Berlin' => 'Berlin',
           'Europe/Madrid' => 'Madrid',
           'Europe/Rome' => 'Rome',
           'Europe/Moscow' => 'Moscow',
           'Europe/Istanbul' => 'Istanbul',
           'Europe/Warsaw' => 'Warsaw',

           // Africa
           'Africa/Lagos' => 'Lagos',
           'Africa/Cairo' => 'Cairo',
           'Africa/Nairobi' => 'Nairobi',
           'Africa/Johannesburg' => 'Johannesburg',
           'Africa/Casablanca' => 'Casablanca',
           'Africa/Accra' => 'Accra',

           // Asia
           'Asia/Tokyo' => 'Tokyo',
           'Asia/Shanghai' => 'Beijing',
           'Asia/Hong_Kong' => 'Hong Kong',
           'Asia/Singapore' => 'Singapore',
           'Asia/Kuala_Lumpur' => 'Kuala Lumpur',
           'Asia/Seoul' => 'Seoul',
           'Asia/Bangkok' => 'Bangkok',
           'Asia/Dubai' => 'Dubai',
           'Asia/Kolkata' => 'Mumbai, Kolkata, New Delhi',
           'Asia/Manila' => 'Manila',
           'Asia/Jakarta' => 'Jakarta',

           // Oceania / Australia
           'Australia/Sydney' => 'Sydney',
           'Australia/Melbourne' => 'Melbourne',
           'Australia/Brisbane' => 'Brisbane',
           'Pacific/Auckland' => 'Auckland',
           'Pacific/Fiji' => 'Fiji',

           // Middle East
           'Asia/Riyadh' => 'Riyadh',
           'Asia/Tehran' => 'Tehran',
           'Asia/Jerusalem' => 'Jerusalem',
           'Asia/Kuwait' => 'Kuwait',

           // Others
           'Antarctica/Palmer' => 'Antarctica - Palmer',
           'Atlantic/Azores' => 'Azores',
           'Indian/Mauritius' => 'Mauritius',
        ];
    }
}
