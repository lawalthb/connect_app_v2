<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class FileUploadHelper
{
    /**
     * Upload file for messaging
     *
     * @param UploadedFile $file
     * @param string $type
     * @param int $userId
     * @return array
     */
    public static function uploadMessageFile(UploadedFile $file, string $type, int $userId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Generate unique filename
        $filename = time() . '_' . uniqid() . '.' . $extension;

        // Determine upload path based on type
        $uploadPath = self::getUploadPath($type);
        $fullPath = $uploadPath . '/' . $filename;

        // Upload to local storage for testing (change to 's3' for production)
        $uploaded = Storage::disk('public')->put($fullPath, file_get_contents($file));

        if (!$uploaded) {
            throw new \Exception('Failed to upload file');
        }

        $fileUrl = Storage::disk('public')->url($fullPath);

        // Prepare metadata
        $metadata = [
            'original_name' => $originalName,
            'filename' => $filename,
            'file_path' => $fullPath,
            'file_url' => $fileUrl,
            'file_size' => $size,
            'mime_type' => $mimeType,
            'extension' => $extension,
        ];

        // Add specific metadata based on file type
        if ($type === 'image') {
            $metadata = array_merge($metadata, self::getImageMetadata($file));
        } elseif ($type === 'video') {
            $metadata = array_merge($metadata, self::getVideoMetadata($file));
        }

        return $metadata;
    }

    /**
     * Get upload path based on file type
     *
     * @param string $type
     * @return string
     */
    private static function getUploadPath(string $type): string
    {
        $basePath = 'messages';

        switch ($type) {
            case 'image':
                return $basePath . '/images';
            case 'video':
                return $basePath . '/videos';
            case 'audio':
                return $basePath . '/audio';
            case 'file':
                return $basePath . '/files';
            default:
                return $basePath . '/others';
        }
    }

    /**
     * Get image metadata using PHP's built-in functions
     *
     * @param UploadedFile $file
     * @return array
     */
    private static function getImageMetadata(UploadedFile $file): array
    {
        try {
            // Use PHP's built-in getimagesize function instead of Intervention Image
            $imageInfo = getimagesize($file->getPathname());

            if ($imageInfo !== false) {
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'aspect_ratio' => $imageInfo[1] > 0 ? round($imageInfo[0] / $imageInfo[1], 2) : 1,
                ];
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get image metadata: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get video metadata
     *
     * @param UploadedFile $file
     * @return array
     */
    private static function getVideoMetadata(UploadedFile $file): array
    {
        return [
            'duration' => null, // Can implement with FFMpeg later
            'format' => $file->getClientOriginalExtension(),
        ];
    }

    /**
     * Validate file type
     *
     * @param UploadedFile $file
     * @param string $type
     * @return bool
     */
    public static function validateFileType(UploadedFile $file, string $type): bool
    {
        $allowedTypes = self::getAllowedMimeTypes();

        if (!isset($allowedTypes[$type])) {
            return false;
        }

        return in_array($file->getMimeType(), $allowedTypes[$type]);
    }

    /**
     * Get allowed MIME types for each file type
     *
     * @return array
     */
    public static function getAllowedMimeTypes(): array
    {
        return [
            'image' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/bmp'
            ],
            'video' => [
                'video/mp4',
                'video/avi',
                'video/mov',
                'video/wmv',
                'video/webm',
                'video/3gp'
            ],
            'audio' => [
                'audio/mp3',
                'audio/wav',
                'audio/ogg',
                'audio/aac',
                'audio/m4a'
            ],
            'file' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'application/zip',
                'application/x-rar-compressed'
            ]
        ];
    }

    /**
     * Get max file size for each type (in MB)
     *
     * @return array
     */
    public static function getMaxFileSizes(): array
    {
        return [
            'image' => 10, // 10MB
            'video' => 100, // 100MB
            'audio' => 50, // 50MB
            'file' => 25, // 25MB
        ];
    }

    /**
     * Format file size
     *
     * @param int $bytes
     * @return string
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
