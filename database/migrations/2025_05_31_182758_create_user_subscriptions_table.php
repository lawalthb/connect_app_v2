<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method')->nullable(); // stripe, nomba
            $table->string('payment_status')->default('pending'); // pending, completed, failed, cancelled
            $table->string('transaction_reference')->nullable();
            $table->string('customer_id')->nullable(); // Stripe customer ID or Nomba customer ID
            $table->json('payment_details')->nullable(); // Store payment gateway response
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('status')->default('active'); // active, expired, cancelled
            $table->unsignedBigInteger('parent_id')->nullable(); // For boost subscriptions under premium
            $table->integer('boost_count')->default(0); // For tracking boost usage
            $table->string('auto_renew')->default('N'); // Y/N
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('deleted_flag')->default('N');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscribes')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
