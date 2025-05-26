<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\FileUploadHelper; // Assuming you still use this
use App\Helpers\TimezoneHelper;
use App\Models\User; // Import User model

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $reader */
        $reader = $request->user(); // Get the currently authenticated user (the reader)

        $data = [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'message' => $this->message,
            'type' => $this->type,
            'is_edited' => $this->is_edited,
            'edited_at' => TimezoneHelper::convertToUserTimezone($this->edited_at, $reader)?->toISOString(),
            'user' => [ // Information about the sender of this message
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'profile_url' => $this->user->profile_url,
            ],
            // Timestamps converted to the $reader's timezone
            'created_at' => TimezoneHelper::convertToUserTimezone($this->created_at, $reader)?->toISOString(),
            'created_at_human' => TimezoneHelper::humanInUserTimezone($this->created_at, $reader),
            'created_at_formatted' => TimezoneHelper::formatInUserTimezone($this->created_at, 'M j, Y g:i A', $reader),
            // 'reader_timezone_used' => $reader ? $reader->getTimezone() : config('app.timezone', 'UTC'), // For debugging
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
                'user' => [ // Sender of the replied-to message
                    'id' => $this->replyToMessage->user->id,
                    'name' => $this->replyToMessage->user->name,
                    'username' => $this->replyToMessage->user->username,
                ],
                // Convert replied message's created_at to the $reader's timezone
                'created_at' => TimezoneHelper::convertToUserTimezone($this->replyToMessage->created_at, $reader)?->toISOString(),
                'created_at_human' => TimezoneHelper::humanInUserTimezone($this->replyToMessage->created_at, $reader),
            ];
        }

        return $data;
    }

    /**
     * Format file data based on type.
     * (Ensure this is consistent with your FileUploadHelper and metadata structure)
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
        if ($type === 'image') {
            $fileData['dimensions'] = [
                'width' => $metadata['width'] ?? null,
                'height' => $metadata['height'] ?? null,
                'aspect_ratio' => $metadata['aspect_ratio'] ?? null,
                'orientation' => $metadata['orientation'] ?? null, // From FileUploadHelper
            ];
            $fileData['thumbnail_url'] = $metadata['thumbnail_url'] ?? null; // From FileUploadHelper
        } elseif ($type === 'video') {
            $fileData['duration'] = $metadata['duration'] ?? null;
            $fileData['format'] = $metadata['format'] ?? null;
            // $fileData['thumbnail_url'] = $this->generateVideoThumbnail($metadata['file_url'] ?? null); // Implement if needed
        } elseif ($type === 'audio') {
            $fileData['duration'] = $metadata['duration'] ?? null;
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
