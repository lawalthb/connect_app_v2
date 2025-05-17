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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 2)->unique()->comment('ISO 3166-1 alpha-2 code');
            $table->string('code3', 3)->nullable()->comment('ISO 3166-1 alpha-3 code');
            $table->string('numeric_code', 3)->nullable()->comment('ISO 3166-1 numeric code');
            $table->string('phone_code', 10)->nullable()->comment('Country calling code');
            $table->string('capital')->nullable();
            $table->string('currency')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('region')->nullable()->comment('Continent or region');
            $table->string('subregion')->nullable();

            // Timezone related columns
            $table->string('timezone')->nullable()->comment('Default timezone');
            $table->json('timezones')->nullable()->comment('All timezones in the country');
            $table->string('timezone_offset')->nullable()->comment('UTC offset of default timezone');
            $table->boolean('has_dst')->default(false)->comment('Whether the country observes Daylight Saving Time');

            // Geolocation data
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Additional info
            $table->string('emoji', 16)->nullable()->comment('Country flag emoji');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
