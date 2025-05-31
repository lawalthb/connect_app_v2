<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscribes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 8, 2);
            $table->string('currency', 3)->default('USD');
            $table->integer('duration_days')->default(30); // 30 days subscription
            $table->json('features')->nullable(); // Store features as JSON
            $table->string('stripe_price_id')->nullable();
            $table->string('nomba_plan_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('badge_color')->nullable(); // For UI display
            $table->string('icon')->nullable(); // Icon for the plan
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscribes');
    }
};
