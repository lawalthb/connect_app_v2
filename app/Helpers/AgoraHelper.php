<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\CallParticipant;

class AgoraHelper
{
    private static string $appId;
    private static string $appCertificate;

    public static function init(): void
    {
        self::$appId = config('services.agora.app_id');
        self::$appCertificate = config('services.agora.app_certificate');
    }

    /**
     * Generate Agora RTC token
     */
    public static function generateRtcToken(
        string $channelName,
        int $uid,
        int $expireTimeInSeconds = 3600,
        string $role = 'publisher'
    ): ?string {
        try {
            self::init();

            if (!self::isConfigured()) {
                Log::error('Agora not configured properly');
                return null;
            }

            $currentTimestamp = now()->timestamp;
            $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;
            $roleValue = $role === 'publisher' ? 1 : 2;

            // Try different possible class names (without namespace first)
            $possibleClasses = [
                'RtcTokenBuilder',
                'RtcTokenBuilder2',
                '\RtcTokenBuilder',
                '\RtcTokenBuilder2',
            ];

            $token = null;
            $usedClass = null;

            foreach ($possibleClasses as $className) {
                if (class_exists($className)) {
                    $usedClass = $className;
                    Log::info('Found Agora class: ' . $className);

                    try {
                        // Try buildTokenWithUid method
                        if (method_exists($className, 'buildTokenWithUid')) {
                            $token = $className::buildTokenWithUid(
                                self::$appId,
                                self::$appCertificate,
                                $channelName,
                                $uid,
                                $roleValue,
                                $privilegeExpiredTs
                            );
                        }
                        // Try buildTokenWithUserAccount method
                        elseif (method_exists($className, 'buildTokenWithUserAccount')) {
                            $token = $className::buildTokenWithUserAccount(
                                self::$appId,
                                self::$appCertificate,
                                $channelName,
                                (string)$uid,
                                $roleValue,
                                $privilegeExpiredTs
                            );
                        }
                        // Try build method
                        elseif (method_exists($className, 'build')) {
                            $token = $className::build(
                                self::$appId,
                                self::$appCertificate,
                                $channelName,
                                $uid,
                                $roleValue,
                                $privilegeExpiredTs
                            );
                        }

                        if ($token) {
                            Log::info('Token generated successfully with class: ' . $className);
                            break;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed with class ' . $className . ': ' . $e->getMessage());
                        continue;
                    }
                }
            }

            // If no package class worked, use custom implementation
            if (!$token) {
                Log::info('No working Agora package class found, using custom implementation');
                $token = self::generateTokenCustom($channelName, $uid, $expireTimeInSeconds, $role);
                $usedClass = 'Custom';
            }

            if ($token) {
                Log::info('Agora token generated successfully', [
                    'channel' => $channelName,
                    'uid' => $uid,
                    'role' => $role,
                    'class_used' => $usedClass,
                    'token_length' => strlen($token),
                    'expires_at' => Carbon::createFromTimestamp($privilegeExpiredTs)->toISOString()
                ]);
                return $token;
            }

            Log::error('All token generation methods failed');
            return null;

        } catch (\Exception $e) {
            Log::error('Failed to generate Agora token', [
                'error' => $e->getMessage(),
                'channel' => $channelName,
                'uid' => $uid,
                'role' => $role,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Custom token generation implementation
     */
    private static function generateTokenCustom(
        string $channelName,
        int $uid,
        int $expireTimeInSeconds = 3600,
        string $role = 'publisher'
    ): ?string {
        try {
            self::init();

            $version = "007";
            $randomInt = mt_rand();
            $timestamp = time();
            $privilegeExpiredTs = $timestamp + $expireTimeInSeconds;
            $roleValue = $role === 'publisher' ? 1 : 2;

            // Build message
            $message = [
                'salt' => $randomInt,
                'ts' => $timestamp,
                'privileges' => []
            ];

            if ($roleValue == 1) { // Publisher
                $message['privileges'] = [
                    1 => $privilegeExpiredTs, // JOIN_CHANNEL
                    2 => $privilegeExpiredTs, // PUBLISH_AUDIO_STREAM
                    3 => $privilegeExpiredTs, // PUBLISH_VIDEO_STREAM
                    4 => $privilegeExpiredTs, // PUBLISH_DATA_STREAM
                ];
            } else { // Subscriber
                $message['privileges'] = [
                    1 => $privilegeExpiredTs, // JOIN_CHANNEL
                ];
            }

            // Pack message
            $packed = pack('V', $message['salt']);
            $packed .= pack('V', $message['ts']);
            $packed .= pack('v', count($message['privileges']));

            foreach ($message['privileges'] as $key => $value) {
                $packed .= pack('v', $key);
                $packed .= pack('V', $value);
            }

            // Generate signature
            $signature = hash_hmac('sha256', $packed, self::$appCertificate, true);

            // Combine signature and message
            $content = array_merge(unpack('C*', $signature), unpack('C*', $packed));
            $contentString = implode('', array_map('chr', $content));

            // Build final token
            $token = $version . self::$appId . base64_encode($contentString);

            Log::info('Custom token generated', [
                'channel' => $channelName,
                'uid' => $uid,
                'role' => $role,
                'token_length' => strlen($token)
            ]);

            return $token;

        } catch (\Exception $e) {
            Log::error('Custom token generation failed', [
                'error' => $e->getMessage(),
                'channel' => $channelName,
                'uid' => $uid
            ]);
            return null;
        }
    }

    /**
     * Generate tokens for multiple users
     */
    public static function generateTokensForUsers(
        string $channelName,
        array $userIds,
        int $expireTimeInSeconds = 3600
    ): array {
        $tokens = [];

        foreach ($userIds as $userId) {
            $agoraUid = CallParticipant::generateAgoraUid();
            $token = self::generateRtcToken($channelName, $agoraUid, $expireTimeInSeconds);

            if ($token) {
                $tokens[$userId] = [
                    'token' => $token,
                    'agora_uid' => $agoraUid,
                    'channel_name' => $channelName,
                    'expires_at' => now()->addSeconds($expireTimeInSeconds)->toISOString()
                ];
            } else {
                Log::warning('Failed to generate token for user', [
                    'user_id' => $userId,
                    'channel' => $channelName
                ]);
            }
        }

        return $tokens;
    }

    /**
     * Get Agora App ID
     */
    public static function getAppId(): string
    {
        self::init();
        return self::$appId;
    }

    /**
     * Validate Agora configuration
     */
    public static function isConfigured(): bool
    {
        self::init();
        return !empty(self::$appId) && !empty(self::$appCertificate);
    }

    /**
     * Test token generation with detailed debugging
     */
    public static function testTokenGeneration(): array
    {
        $testChannel = 'test_channel_' . time();
        $testUid = 12345;

        // Check what classes are available
        $availableClasses = [];
        $possibleClasses = [
            'RtcTokenBuilder',
            'RtcTokenBuilder2',
            '\RtcTokenBuilder',
            '\RtcTokenBuilder2',
        ];

        foreach ($possibleClasses as $className) {
            if (class_exists($className)) {
                $reflection = new \ReflectionClass($className);
                $availableClasses[$className] = array_map(function($method) {
                    return $method->getName();
                }, $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC));
            }
        }

        $startTime = microtime(true);
        $token = self::generateRtcToken($testChannel, $testUid, 3600);
        $endTime = microtime(true);

        return [
            'success' => !is_null($token),
            'token' => $token,
            'token_preview' => $token ? substr($token, 0, 50) . '...' : null,
            'channel' => $testChannel,
            'uid' => $testUid,
            'app_id' => self::getAppId(),
            'configured' => self::isConfigured(),
            'generation_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'available_classes' => $availableClasses,
            'config_check' => [
                'app_id_length' => strlen(self::$appId),
                'app_certificate_length' => strlen(self::$appCertificate),
            ]
        ];
    }
}
