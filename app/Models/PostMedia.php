<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PostMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'type',
        'file_path',
        'file_url',
        'original_name',
        'file_size',
        'mime_type',
        'width',
        'height',
        'duration',
        'thumbnail_path',
        'thumbnail_url',
        'compressed_versions',
        'alt_text',
        'order'
    ];

    protected $casts = [
        'compressed_versions' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
        'order' => 'integer',
    ];

    protected $appends = [
        'file_size_human',
        'duration_human'
    ];

    // Relationships
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    // Accessors
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDurationHumanAttribute(): ?string
    {
        if (!$this->duration) return null;

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    // Methods
    public function getCompressedVersion($size = 'medium'): ?string
    {
        $versions = $this->compressed_versions ?? [];
        return $versions[$size] ?? $this->file_url;
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function delete()
    {
        // Delete files from S3 when model is deleted
        if ($this->file_path) {
            Storage::disk('s3')->delete($this->file_path);
        }

        if ($this->thumbnail_path) {
            Storage::disk('s3')->delete($this->thumbnail_path);
        }

        // Delete compressed versions
        if ($this->compressed_versions) {
            foreach ($this->compressed_versions as $version) {
                $path = str_replace(config('filesystems.disks.s3.url') . '/', '', $version);
                Storage::disk('s3')->delete($path);
            }
        }

        return parent::delete();
    }
}
