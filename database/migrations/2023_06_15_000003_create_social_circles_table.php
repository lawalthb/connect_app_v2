<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_circles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable()->comment('Path to the logo image');
            $table->string('logo_url')->nullable()->comment('URL directory for the logo');
            $table->text('description')->nullable();
            $table->integer('order_by')->default(0)->comment('For sorting social circles');
            $table->string('color', 20)->nullable()->comment('Color code for UI representation');
            $table->boolean('is_default')->default(false)->comment('Is this a default social circle');
            $table->boolean('is_active')->default(true)->comment('Whether this social circle is active');
            $table->boolean('is_private')->default(false)->comment('Whether this is a private circle');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete implementation');
            $table->char('deleted_flag', 1)->default('N')->comment('Y for deleted, N for active');

            // Add foreign key if you're tracking who created the social circle
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });

        // Create many-to-many relationship between users and social circles
        Schema::create('user_social_circle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('social_id')->comment('References social_circles.id');
            $table->timestamps();
            $table->softDeletes();
            $table->char('deleted_flag', 1)->default('N')->comment('Y for deleted, N for active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Add foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('social_id')->references('id')->on('social_circles')->onDelete('cascade');

            // Prevent duplicate entries
            $table->unique(['user_id', 'social_id', 'deleted_flag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_social_circle');
        Schema::dropIfExists('social_circles');
    }
};
