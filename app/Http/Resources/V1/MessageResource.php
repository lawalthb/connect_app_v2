<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\FileUploadHelper;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'message' => $this->message,
            'type' => $this->type,
            'is_edited' => $this->is_edited,
            'edited_at' => $this->edited_at,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'profile_url' => $this->user->profile_url,
            ],
            'created_at' => $this->created_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];

        // Add file information if message has metadata
        if ($this->metadata) {
            $data['file'] = $this->formatFileData($this->metadata, $this->type);
        }

        // Add location information if it's a location message
        if ($this->type === 'location' && $this->metadata) {
            $data['location'] = [
                'latitude' => $this->metadata['latitude'] ?? null,
                'longitude' => $this->metadata['longitude'] ?? null,
                'address' => $this->metadata['address'] ?? null,
            ];
        }

        // Add reply information if this is a reply
        if ($this->replyToMessage) {
            $data['reply_to_message'] = [
                'id' => $this->replyToMessage->id,
                'message' => $this->replyToMessage->message,
                'type' => $this->replyToMessage->type,
                'user' => [
                    'id' => $this->replyToMessage->user->id,
                    'name' => $this->replyToMessage->user->name,
                    'username' => $this->replyToMessage->user->username,
                ],
            ];
        }

        return $data;
    }

    /**
     * Format file data based on type
     */
    private function formatFileData($metadata, $type)
    {
        $fileData = [
            'url' => $metadata['file_url'] ?? null,
            'filename' => $metadata['original_name'] ?? $metadata['filename'] ?? null,
            'size' => $metadata['file_size'] ?? null,
            'size_formatted' => isset($metadata['file_size']) ? FileUploadHelper::formatFileSize($metadata['file_size']) : null,
            'mime_type' => $metadata['mime_type'] ?? null,
            'extension' => $metadata['extension'] ?? null,
        ];

        // Add type-specific data
        switch ($type) {
            case 'image':
                $fileData['dimensions'] = [
                    'width' => $metadata['width'] ?? null,
                    'height' => $metadata['height'] ?? null,
                    'aspect_ratio' => $metadata['aspect_ratio'] ?? null,
                    'orientation' => $metadata['orientation'] ?? null,
                ];
                $fileData['thumbnail_url'] = $metadata['thumbnail_url'] ?? null;
                break;

            case 'video':
                $fileData['duration'] = $metadata['duration'] ?? null;
                $fileData['format'] = $metadata['format'] ?? null;
                // Generate video thumbnail (you can implement this)
                $fileData['thumbnail_url'] = $this->generateVideoThumbnail($metadata['file_url'] ?? null);
                break;

            case 'audio':
                $fileData['duration'] = $metadata['duration'] ?? null;
                break;
        }

        return $fileData;
    }

    /**
     * Generate thumbnail URL for images
     */
    private function generateThumbnailUrl($imageUrl)
    {
        if (!$imageUrl) return null;

        // You can implement thumbnail generation here
        // For now, return the original URL
        return $imageUrl;
    }

    /**
     * Generate thumbnail for videos
     */
    private function generateVideoThumbnail($videoUrl)
    {
        if (!$videoUrl) return null;

        // You can implement video thumbnail generation here
        // For now, return null
        return null;
    }
}
