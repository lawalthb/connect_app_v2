<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlockUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('block_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // User who is blocking
            $table->unsignedBigInteger('block_user_id'); // User being blocked
            $table->text('reason')->nullable(); // Reason for blocking
            $table->enum('deleted_flag', ['Y', 'N'])->default('N');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('block_user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['user_id', 'deleted_flag']);
            $table->index(['block_user_id', 'deleted_flag']);

            // Unique constraint to prevent duplicate blocks
            $table->unique(['user_id', 'block_user_id', 'deleted_flag'], 'unique_user_block');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('block_users');
    }
}
