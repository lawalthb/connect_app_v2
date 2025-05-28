<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Log;

class MediaProcessingService
{
    protected $s3Disk;
    protected $allowedImageTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    protected $allowedVideoTypes = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm'];

    public function __construct()
    {
        $this->s3Disk = Storage::disk('s3');
    }

    /**
     * Process and upload media file
     */
    public function processMedia(UploadedFile $file, string $postId): array
    {
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();

        // Generate unique filename
        $filename = $this->generateFilename($fileExtension);
        $basePath = "posts/{$postId}";

        // Determine file type
        $type = $this->determineFileType($fileExtension, $mimeType);

        $result = [
            'type' => $type,
            'original_name' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $mimeType,
        ];

        try {
            if ($type === 'image') {
                $result = array_merge($result, $this->processImage($file, $basePath, $filename));
            } elseif ($type === 'video') {
                $result = array_merge($result, $this->processVideo($file, $basePath, $filename));
            } else {
                // Handle other file types (documents, etc.)
                $result = array_merge($result, $this->processDocument($file, $basePath, $filename));
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Media processing failed', [
                'file' => $originalName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to process media file: ' . $e->getMessage());
        }
    }

    /**
     * Process image file
     */
    protected function processImage(UploadedFile $file, string $basePath, string $filename): array
    {
        $image = Image::make($file);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Compress and resize original
        $image->orientate(); // Fix orientation based on EXIF data

        // Upload original (compressed)
        $originalPath = "{$basePath}/original/{$filename}";
        $compressedOriginal = $image->encode('jpg', 85);
        $this->s3Disk->put($originalPath, $compressedOriginal->__toString());

        // Create different sizes
        $compressedVersions = [];
        $sizes = [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'small' => ['width' => 300, 'height' => 300],
            'medium' => ['width' => 600, 'height' => 600],
            'large' => ['width' => 1200, 'height' => 1200],
        ];

        foreach ($sizes as $sizeName => $dimensions) {
            $resizedImage = $image->fit($dimensions['width'], $dimensions['height']);
            $resizedPath = "{$basePath}/{$sizeName}/{$filename}";
            $resizedCompressed = $resizedImage->encode('jpg', 80);

            $this->s3Disk->put($resizedPath, $resizedCompressed->__toString());
            $compressedVersions[$sizeName] = $this->s3Disk->url($resizedPath);
        }

        return [
            'file_path' => $originalPath,
            'file_url' => $this->s3Disk->url($originalPath),
            'width' => $originalWidth,
            'height' => $originalHeight,
            'compressed_versions' => $compressedVersions,
        ];
    }

    /**
     * Process video file
     */
    protected function processVideo(UploadedFile $file, string $basePath, string $filename): array
    {
        // Upload original video
        $originalPath = "{$basePath}/videos/{$filename}";
        $this->s3Disk->putFileAs($basePath . '/videos', $file, $filename);

        // Get video info (you might need ffprobe for this)
        $videoInfo = $this->getVideoInfo($file);

        // Generate thumbnail
        $thumbnailPath = $this->generateVideoThumbnail($file, $basePath, $filename);

        return [
            'file_path' => $originalPath,
            'file_url' => $this->s3Disk->url($originalPath),
            'width' => $videoInfo['width'] ?? null,
            'height' => $videoInfo['height'] ?? null,
            'duration' => $videoInfo['duration'] ?? null,
            'thumbnail_path' => $thumbnailPath,
            'thumbnail_url' => $thumbnailPath ? $this->s3Disk->url($thumbnailPath) : null,
        ];
    }

    /**
     * Process document file
     */
    protected function processDocument(UploadedFile $file, string $basePath, string $filename): array
    {
        $documentPath = "{$basePath}/documents/{$filename}";
        $this->s3Disk->putFileAs($basePath . '/documents', $file, $filename);

        return [
            'file_path' => $documentPath,
            'file_url' => $this->s3Disk->url($documentPath),
        ];
    }

    /**
     * Generate video thumbnail
     */
    protected function generateVideoThumbnail(UploadedFile $file, string $basePath, string $filename): ?string
    {
        try {
            // This is a simplified version - you'd use FFMpeg for real implementation
            $thumbnailFilename = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbnailPath = "{$basePath}/thumbnails/{$thumbnailFilename}";

            // Here you would use FFMpeg to extract frame at 1 second
            // For now, return null or implement based on your needs

            return $thumbnailPath;
        } catch (\Exception $e) {
            Log::warning('Thumbnail generation failed', ['file' => $filename, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get video information
     */
    protected function getVideoInfo(UploadedFile $file): array
    {
        // This would use FFProbe to get video information
        // Simplified for now
        return [
            'width' => null,
            'height' => null,
            'duration' => null,
        ];
    }

    /**
     * Determine file type based on extension and mime type
     */
    protected function determineFileType(string $extension, string $mimeType): string
    {
        if (in_array($extension, $this->allowedImageTypes) || str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (in_array($extension, $this->allowedVideoTypes) || str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }

    /**
     * Generate unique filename
     */
    protected function generateFilename(string $extension): string
    {
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Delete media files from S3
     */
    public function deleteMedia(array $filePaths): void
    {
        foreach ($filePaths as $path) {
            if ($path) {
                $this->s3Disk->delete($path);
            }
        }
    }
}
