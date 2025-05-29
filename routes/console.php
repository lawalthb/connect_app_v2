<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// to publish scheduled posts
Schedule::command('posts:publish-scheduled')->everyMinute();
    // Clean up expired stories every hour
    $schedule->command('stories:cleanup')->hourly();
