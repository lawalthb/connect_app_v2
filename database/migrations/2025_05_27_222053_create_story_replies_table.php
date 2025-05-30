<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoryRepliesTable extends Migration
{
    public function up()
    {
        Schema::create('story_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id');
            $table->unsignedBigInteger('user_id'); // Who replied
            $table->enum('type', ['text', 'emoji', 'media']);
            $table->text('content');
            $table->string('file_url')->nullable(); // For media replies
            $table->timestamps();

            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['story_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('story_replies');
    }
}
