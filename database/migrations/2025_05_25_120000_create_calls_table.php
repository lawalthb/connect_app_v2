<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('initiated_by'); // User who started the call
            $table->string('call_type')->default('audio'); // 'audio' or 'video'
            $table->string('status')->default('initiated'); // initiated, ringing, connected, ended, missed
            $table->string('agora_channel_name')->unique();
            $table->json('agora_tokens')->nullable(); // Store tokens for participants
            $table->timestamp('started_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->default(0); // Duration in seconds
            $table->string('end_reason')->nullable(); // ended_by_caller, ended_by_callee, missed, rejected, failed
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('initiated_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['conversation_id', 'status']);
            $table->index(['initiated_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
