<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('reported_by');
            $table->enum('reason', [
                'spam',
                'inappropriate_content',
                'harassment',
                'hate_speech',
                'violence',
                'false_information',
                'copyright_violation',
                'other'
            ]);
            $table->text('description')->nullable();
            $table->enum('status', [
                'pending',
                'under_review',
                'dismissed',
                'action_taken',
                'resolved'
            ])->default('pending');

            // Admin review
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            // Prevent duplicate reports from same user
            $table->unique(['post_id', 'reported_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reports');
    }
};
