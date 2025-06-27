<?php

namespace App\Helpers;

class Utility
{
    /**
     * Convert string data and handle URL concatenation
     */
    public static function convertString($data)
    {
        if (is_object($data) || is_array($data)) {
            $data = json_decode(json_encode($data), true);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = self::convertString($value);
                } else {
                    $data[$key] = self::processStringValue($value);
                }
            }
        }

        return $data;
    }

    /**
     * Process individual string values
     */
    private static function processStringValue($value)
    {
        if (is_string($value)) {
            // Handle URL processing if needed
            return $value;
        }

        return $value;
    }

    /**
     * Format time ago
     */
    public static function timeAgo($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

    /**
     * Generate random string
     */
    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Format currency
     */
    public static function formatCurrency($amount, $currency = 'USD')
    {
        switch ($currency) {
            case 'USD':
                return '$' . number_format($amount, 2);
            case 'NGN':
                return '₦' . number_format($amount, 2);
            case 'EUR':
                return '€' . number_format($amount, 2);
            case 'GBP':
                return '£' . number_format($amount, 2);
            default:
                return $currency . ' ' . number_format($amount, 2);
        }
    }

    /**
     * Clean phone number
     */
    public static function cleanPhoneNumber($phone)
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    /**
     * Validate email
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate slug from string
     */
    public static function generateSlug($string)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    }

    /**
     * Truncate text
     */
    public static function truncate($text, $length = 100, $suffix = '...')
    {
        if (strlen($text) > $length) {
            return substr($text, 0, $length) . $suffix;
        }
        return $text;
    }

    /**
     * Get file size in human readable format
     */
    public static function formatFileSize($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Convert array to object
     */
    public static function arrayToObject($array)
    {
        return json_decode(json_encode($array));
    }

    /**
     * Convert object to array
     */
    public static function objectToArray($object)
    {
        return json_decode(json_encode($object), true);
    }

    /**
     * Check if string is JSON
     */
    public static function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Generate OTP
     */
    public static function generateOTP($length = 6)
    {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Validate URL
     */
    public static function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get age from date of birth
     */
    public static function getAge($dateOfBirth)
    {
        $today = new \DateTime();
        $birthDate = new \DateTime($dateOfBirth);
        $age = $today->diff($birthDate);
        return $age->y;
    }

    /**
     * Distance between two coordinates (in kilometers)
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth radius in kilometers

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Sanitize input
     */
    public static function sanitizeInput($input)
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate unique filename
     */
    public static function generateUniqueFilename($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }
}
