<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;

class FileUploadHelper
{
    /**
     * Static ImageManager instance for reuse
     */
    private static ?ImageManager $manager = null;

    /**
     * Get ImageManager instance (singleton pattern)
     *
     * @return ImageManager
     */
    private static function getImageManager(): ImageManager
    {
        if (self::$manager === null) {
            self::$manager = new ImageManager(new Driver());
        }

        return self::$manager;
    }

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

        // Handle image processing and upload
        if ($type === 'image') {
            $uploaded = self::processAndUploadImage($file, $fullPath, $extension);
        } else {
            // Upload non-image files directly to S3
            $uploaded = Storage::disk('s3')->put($fullPath, file_get_contents($file));
        }

        if (!$uploaded) {
            throw new \Exception('Failed to upload file');
        }

        $fileUrl = Storage::disk('s3')->url($fullPath);

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
            // Add thumbnail URL
            $metadata['thumbnail_url'] = self::generateThumbnail($file, $fullPath, $extension);
        } elseif ($type === 'video') {
            $metadata = array_merge($metadata, self::getVideoMetadata($file));
        }

        return $metadata;
    }

    /**
     * Process and upload image with Intervention Image v3 to S3
     *
     * @param UploadedFile $file
     * @param string $fullPath
     * @param string $extension
     * @return bool
     */
    private static function processAndUploadImage(UploadedFile $file, string $fullPath, string $extension): bool
    {
        try {
            $manager = self::getImageManager();

            // Read image using Intervention Image v3
            $image = $manager->read($file->getPathname());

            // Resize image if it's too large (max 2000px width)
            if ($image->width() > 2000) {
                $image = $image->resize(2000, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Optimize and encode based on file type
            $encodedImage = match(strtolower($extension)) {
                'jpg', 'jpeg' => $image->encode(new JpegEncoder(quality: 85)),
                'png' => $image->encode(new PngEncoder()),
                'webp' => $image->encode(new WebpEncoder(quality: 85)),
                default => $image->encode(new JpegEncoder(quality: 85))
            };

            // Upload to S3 with proper content type
            $uploaded = Storage::disk('s3')->put($fullPath, $encodedImage->toString(), [
                'ContentType' => 'image/' . $extension,
                'CacheControl' => 'max-age=31536000', // 1 year cache
                'ACL' => 'public-read', // Make file publicly accessible
            ]);

            return $uploaded;

        } catch (\Exception $e) {
            \Log::error('Image processing failed: ' . $e->getMessage());
            // Fallback to direct upload if image processing fails
            return Storage::disk('s3')->put($fullPath, file_get_contents($file), [
                'ContentType' => $file->getMimeType(),
                'CacheControl' => 'max-age=31536000',
                'ACL' => 'public-read',
            ]);
        }
    }

    /**
     * Generate thumbnail for images (S3 compatible)
     *
     * @param UploadedFile $originalFile
     * @param string $fullPath
     * @param string $extension
     * @return string|null
     */
    private static function generateThumbnail(UploadedFile $originalFile, string $fullPath, string $extension): ?string
    {
        try {
            // Create thumbnail path
            $thumbnailPath = str_replace('.' . $extension, '_thumb.' . $extension, $fullPath);

            $manager = self::getImageManager();

            // Read from the original uploaded file (not from S3)
            $image = $manager->read($originalFile->getPathname());

            // Create thumbnail (300x300 max, maintain aspect ratio)
            $thumbnail = $image->resize(300, 300, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Encode thumbnail
            $encodedThumbnail = match(strtolower($extension)) {
                'jpg', 'jpeg' => $thumbnail->encode(new JpegEncoder(quality: 80)),
                'png' => $thumbnail->encode(new PngEncoder()),
                'webp' => $thumbnail->encode(new WebpEncoder(quality: 80)),
                default => $thumbnail->encode(new JpegEncoder(quality: 80))
            };

            // Upload thumbnail to S3
            $thumbnailSaved = Storage::disk('s3')->put($thumbnailPath, $encodedThumbnail->toString(), [
                'ContentType' => 'image/' . $extension,
                'CacheControl' => 'max-age=31536000',
                'ACL' => 'public-read',
            ]);

            if ($thumbnailSaved) {
                return Storage::disk('s3')->url($thumbnailPath);
            }

        } catch (\Exception $e) {
            \Log::warning('Thumbnail generation failed: ' . $e->getMessage());
        }

        return null;
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
     * Get image metadata using Intervention Image v3
     *
     * @param UploadedFile $file
     * @return array
     */
    private static function getImageMetadata(UploadedFile $file): array
    {
        try {
            $manager = self::getImageManager();

            // Read image using Intervention Image v3
            $image = $manager->read($file->getPathname());

            return [
                'width' => $image->width(),
                'height' => $image->height(),
                'aspect_ratio' => $image->height() > 0 ? round($image->width() / $image->height(), 2) : 1,
                'orientation' => $image->width() > $image->height() ? 'landscape' : ($image->width() < $image->height() ? 'portrait' : 'square'),
                'color_space' => $image->colorspace()->value ?? 'unknown',
            ];
        } catch (\Exception $e) {
            \Log::warning('Failed to get image metadata with Intervention Image: ' . $e->getMessage());

            // Fallback to PHP's built-in function
            try {
                $imageInfo = getimagesize($file->getPathname());

                if ($imageInfo !== false) {
                    return [
                        'width' => $imageInfo[0],
                        'height' => $imageInfo[1],
                        'aspect_ratio' => $imageInfo[1] > 0 ? round($imageInfo[0] / $imageInfo[1], 2) : 1,
                        'orientation' => $imageInfo[0] > $imageInfo[1] ? 'landscape' : ($imageInfo[0] < $imageInfo[1] ? 'portrait' : 'square'),
                    ];
                }
            } catch (\Exception $fallbackError) {
                \Log::warning('Fallback image metadata also failed: ' . $fallbackError->getMessage());
            }
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

    /**
     * Create optimized image for different use cases (S3 compatible)
     *
     * @param UploadedFile $file
     * @param array $sizes
     * @return array
     */
    public static function createImageVariants(UploadedFile $file, array $sizes = []): array
    {
        $variants = [];
        $defaultSizes = [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 500, 'height' => 500],
            'large' => ['width' => 1200, 'height' => 1200],
        ];

        $sizes = array_merge($defaultSizes, $sizes);

        try {
            $manager = self::getImageManager();
            $image = $manager->read($file->getPathname());

            foreach ($sizes as $sizeName => $dimensions) {
                $variant = clone $image;
                $variant = $variant->resize($dimensions['width'], $dimensions['height'], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                $filename = time() . '_' . $sizeName . '_' . uniqid() . '.jpg';
                $path = 'messages/images/variants/' . $filename;

                $encoded = $variant->encode(new JpegEncoder(quality: 85));

                // Upload variant to S3
                Storage::disk('s3')->put($path, $encoded->toString(), [
                    'ContentType' => 'image/jpeg',
                    'CacheControl' => 'max-age=31536000',
                    'ACL' => 'public-read',
                ]);

                $variants[$sizeName] = [
                    'url' => Storage::disk('s3')->url($path),
                    'path' => $path,
                    'width' => $variant->width(),
                    'height' => $variant->height(),
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Image variant creation failed: ' . $e->getMessage());
        }

        return $variants;
    }

    /**
     * Delete file from S3
     *
     * @param string $filePath
     * @return bool
     */
    public static function deleteFile(string $filePath): bool
    {
        try {
            return Storage::disk('s3')->delete($filePath);
        } catch (\Exception $e) {
            \Log::error('Failed to delete file from S3: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset the ImageManager instance (useful for testing or memory management)
     *
     * @return void
     */
    public static function resetImageManager(): void
    {
        self::$manager = null;
    }

    /**
     * Configure ImageManager with custom driver
     *
     * @param string $driverClass
     * @return void
     */
    public static function setImageDriver(string $driverClass): void
    {
        self::$manager = new ImageManager(new $driverClass());
    }
}
