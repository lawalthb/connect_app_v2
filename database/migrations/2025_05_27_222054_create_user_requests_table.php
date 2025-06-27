<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('user_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            $table->unsignedBigInteger('social_id')->nullable(); // Social circle context
            $table->enum('status', ['pending', 'accepted', 'rejected', 'blocked'])->default('pending');
            $table->enum('sender_status', ['pending', 'accepted', 'rejected', 'blocked', 'disconnect'])->default('pending');
            $table->enum('receiver_status', ['pending', 'accepted', 'rejected', 'blocked', 'disconnect'])->default('pending');
            $table->enum('request_type', ['right_swipe', 'left_swipe', 'super_like', 'direct_request'])->default('right_swipe');
            $table->text('message')->nullable(); // Optional message with request
            $table->timestamp('expires_at')->nullable(); // For time-limited requests
            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['sender_id', 'receiver_id']); // Prevent duplicate requests
            $table->index(['receiver_id', 'status']);
            $table->index(['sender_id', 'created_at']);
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->char('deleted_flag', 1)->default('N')->comment('Y for deleted, N for active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_requests');
    }
}
