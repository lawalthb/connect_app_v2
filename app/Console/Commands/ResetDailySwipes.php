<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSwipe;
use Carbon\Carbon;

class ResetDailySwipes extends Command
{
    protected $signature = 'swipes:reset-daily';
    protected $description = 'Reset daily swipe counts for all users';

    public function handle()
    {
        $this->info('Starting daily swipe reset...');

        $yesterday = Carbon::yesterday()->toDateString();

        // Archive yesterday's swipe data
        $archivedCount = UserSwipe::where('swipe_date', $yesterday)
                                 ->update(['archived_at' => now()]);

        $this->info("Archived {$archivedCount} swipe records from yesterday");

        // Clean up old archived records (older than 30 days)
        $deletedCount = UserSwipe::where('archived_at', '<', Carbon::now()->subDays(30))
                                ->delete();

        $this->info("Deleted {$deletedCount} old swipe records");

        $this->info('Daily swipe reset completed successfully');

        return 0;
    }
}
