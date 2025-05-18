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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Basic information
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('reset_otp')->nullable();
            $table->string('email_otp')->nullable();

            // Profile information
            $table->text('bio')->nullable();
            $table->json('social_links')->nullable()->comment('JSON array of social media links');
            $table->string('profile')->nullable()->comment('Profile image filename');
            $table->string('profile_url')->nullable()->comment('URL directory for profile images');
            $table->string('avatar')->nullable()->comment('Avatar/icon image');
            $table->string('cover_photo')->nullable()->comment('Cover photo for profile');

            // Personal information
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'non_binary', 'other', 'prefer_not_to_say'])->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('timezone')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Preferences & Settings
            $table->boolean('privacy_public_profile')->default(true)->comment('Is profile visible to public');
            $table->boolean('privacy_show_online_status')->default(true);
            $table->boolean('privacy_show_activity')->default(true);
            $table->boolean('notification_email')->default(true);
            $table->boolean('notification_push')->default(true);
            $table->json('notification_preferences')->nullable();
            $table->string('language')->default('en');
            $table->boolean('is_advertiser')->default(false)->comment('Can create advertisements');

            // Targeting attributes
            $table->json('interests')->nullable()->comment('Array of interest IDs or keywords');
            $table->json('skills')->nullable();
            $table->string('occupation')->nullable();
            $table->string('education_level')->nullable();
            $table->string('relationship_status')->nullable();
            $table->boolean('has_children')->nullable();
            $table->string('income_range')->nullable();

            // Technical information
            $table->string('device_token')->nullable()->comment('For push notifications');
            $table->string('social_id')->nullable()->comment('For social login');
            $table->string('social_type')->nullable()->comment('google, facebook, etc.');

            // Tracking & Activity
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->integer('login_count')->default(0);
            $table->boolean('is_online')->default(false);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_until')->nullable();
            $table->text('ban_reason')->nullable();

            // Metadata
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->char('deleted_flag', 1)->default('N')->comment('Y for deleted, N for active');
            $table->timestamp('email_otp_expires_at')->nullable();

            // Foreign keys
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
