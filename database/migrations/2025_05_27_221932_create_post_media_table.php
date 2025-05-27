<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->enum('type', ['image', 'video', 'audio', 'document']);

            // File information
            $table->string('file_path'); // S3 path
            $table->string('file_url'); // Full S3 URL
            $table->string('original_name');
            $table->unsignedBigInteger('file_size'); // bytes
            $table->string('mime_type');

            // Image/Video specific
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable(); // seconds for video/audio
            $table->string('thumbnail_path')->nullable(); // For videos
            $table->string('thumbnail_url')->nullable();

            // Compressed versions
            $table->json('compressed_versions')->nullable(); // Different sizes

            // Accessibility & Organization
            $table->string('alt_text')->nullable();
            $table->unsignedTinyInteger('order')->default(1); // For multiple media

            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->index(['post_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
