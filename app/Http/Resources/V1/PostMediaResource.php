<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PostMediaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'file_url' => $this->file_url,
            'thumbnail_url' => $this->thumbnail_url,
            'original_name' => $this->original_name,
            'file_size' => $this->file_size,
            'file_size_human' => $this->file_size_human,
            'mime_type' => $this->mime_type,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'duration_human' => $this->duration_human,
            'alt_text' => $this->alt_text,
            'order' => $this->order,
            'compressed_versions' => $this->compressed_versions,
        ];
    }
}
