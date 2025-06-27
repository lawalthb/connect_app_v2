<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSwipesTable extends Migration
{
    public function up()
    {
        Schema::create('user_swipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->date('swipe_date');
            $table->integer('right_swipes')->default(0);
            $table->integer('left_swipes')->default(0);
            $table->integer('super_likes')->default(0);
            $table->integer('daily_limit')->default(50); // Can be different per user/subscription
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'swipe_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_swipes');
    }
}
