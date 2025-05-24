<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TimezoneHelper
{
    /**
     * Convert a timestamp to user's timezone
     *
     * @param mixed $timestamp
     * @param string|null $userTimezone
     * @param string $format
     * @return string
     */
    public static function convertToUserTimezone($timestamp, $userTimezone = null, $format = 'Y-m-d H:i:s')
    {
        if (!$timestamp) {
            return null;
        }

        // Get user timezone
        $timezone = $userTimezone ?? self::getUserTimezone();

        // Convert timestamp to Carbon instance
        $carbon = $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp);

        // Convert to user timezone and format
        return $carbon->setTimezone($timezone)->format($format);
    }

    /**
     * Convert a timestamp to user's timezone and return Carbon instance
     *
     * @param mixed $timestamp
     * @param string|null $userTimezone
     * @return Carbon
     */
    public static function convertToUserTimezoneCarbon($timestamp, $userTimezone = null)
    {
        if (!$timestamp) {
            return null;
        }

        // Get user timezone
        $timezone = $userTimezone ?? self::getUserTimezone();

        // Convert timestamp to Carbon instance
        $carbon = $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp);

        // Convert to user timezone
        return $carbon->setTimezone($timezone);
    }

    /**
     * Get the current authenticated user's timezone
     *
     * @return string
     */
    public static function getUserTimezone()
    {
        $user = Auth::user();
        return $user && $user->timezone ? $user->timezone : config('app.timezone', 'UTC');
    }

    /**
     * Get current time in user's timezone
     *
     * @param string|null $userTimezone
     * @return Carbon
     */
    public static function now($userTimezone = null)
    {
        $timezone = $userTimezone ?? self::getUserTimezone();
        return Carbon::now($timezone);
    }

    /**
     * Format time for display with timezone info
     *
     * @param mixed $timestamp
     * @param string|null $userTimezone
     * @param string $format
     * @return string
     */
    public static function formatForDisplay($timestamp, $userTimezone = null, $format = 'M j, Y g:i A T')
    {
        return self::convertToUserTimezone($timestamp, $userTimezone, $format);
    }

    /**
     * Get relative time (e.g., "2 hours ago") in user's timezone
     *
     * @param mixed $timestamp
     * @param string|null $userTimezone
     * @return string
     */
    public static function diffForHumans($timestamp, $userTimezone = null)
    {
        $carbon = self::convertToUserTimezoneCarbon($timestamp, $userTimezone);
        return $carbon ? $carbon->diffForHumans() : null;
    }

    /**
     * Get list of common timezones
     *
     * @return array
     */
    public static function getTimezoneList()
    {
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
