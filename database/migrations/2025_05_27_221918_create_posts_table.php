<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('social_circle_id'); // Required - category
            $table->text('content')->nullable();
            $table->enum('type', ['text', 'image', 'video', 'mixed'])->default('text');
            $table->json('location')->nullable(); // {lat, lng, address}

            // Editing & Publishing
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            // Cached counters for performance
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);

            // Metadata
            $table->json('metadata')->nullable(); // For extra data
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('social_circle_id')->references('id')->on('social_circles')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
            $table->index(['social_circle_id', 'created_at']);
            $table->index(['published_at', 'is_published']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
