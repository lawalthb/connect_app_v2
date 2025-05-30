<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLikesTable extends Migration
{
    public function up()
    {
        Schema::create('user_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Who is liking
            $table->unsignedBigInteger('liked_user_id'); // Who is being liked
            $table->enum('type', ['profile', 'photo', 'super_like'])->default('profile');
            $table->unsignedBigInteger('photo_id')->nullable(); // If liking specific photo
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('liked_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'liked_user_id', 'type']); // Prevent duplicate likes
            $table->index(['liked_user_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_likes');
    }
}
