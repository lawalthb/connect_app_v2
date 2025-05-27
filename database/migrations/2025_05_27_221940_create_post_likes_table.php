<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('reaction_type', [
                'like',
                'love',
                'laugh',
                'angry',
                'sad',
                'wow'
            ])->default('like');
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // One reaction per user per post
            $table->unique(['post_id', 'user_id']);
            $table->index(['post_id', 'reaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_likes');
    }
};
