<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_tagged_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id'); // Tagged user
            $table->unsignedBigInteger('tagged_by'); // Who tagged them
            $table->unsignedBigInteger('media_id')->nullable(); // If tagged on specific media

            // Position on image (for image tagging)
            $table->decimal('position_x', 5, 2)->nullable(); // Percentage 0-100
            $table->decimal('position_y', 5, 2)->nullable(); // Percentage 0-100

            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tagged_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on('post_media')->onDelete('cascade');

            // Prevent duplicate tags
            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tagged_users');
    }
};
