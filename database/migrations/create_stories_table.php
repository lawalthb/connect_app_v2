<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoriesTable extends Migration
{
    public function up()
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['text', 'image', 'video']);
            $table->text('content')->nullable(); // Text content or file path
            $table->string('file_url')->nullable(); // For media files
            $table->text('caption')->nullable();
            $table->string('background_color')->default('#000000'); // For text stories
            $table->json('font_settings')->nullable(); // Font size, family for text stories
            $table->enum('privacy', ['all_connections', 'close_friends', 'custom'])->default('all_connections');
            $table->json('custom_viewers')->nullable(); // Array of user IDs for custom privacy
            $table->boolean('allow_replies')->default(true);
            $table->integer('views_count')->default(0);
            $table->timestamp('expires_at'); // 24 hours from creation
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'expires_at']);
            $table->index(['expires_at']); // For cleanup jobs
        });
    }

    public function down()
    {
        Schema::dropIfExists('stories');
    }
}
