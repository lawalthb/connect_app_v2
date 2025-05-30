<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoryViewsTable extends Migration
{
    public function up()
    {
        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id');
            $table->unsignedBigInteger('viewer_id');
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
            $table->foreign('viewer_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['story_id', 'viewer_id']); // Prevent duplicate views
            $table->index(['story_id', 'viewed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('story_views');
    }
}
