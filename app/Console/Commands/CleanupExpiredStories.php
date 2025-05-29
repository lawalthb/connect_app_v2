<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Services\MediaProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredStories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stories:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired stories and their associated files';

    public function __construct(
        private MediaProcessingService $mediaService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting cleanup of expired stories...');

        $expiredStories = Story::expired()
            ->where('deleted_at', null)
            ->get();

        $deletedCount = 0;
        $failedCount = 0;

        foreach ($expiredStories as $story) {
            try {
                // Delete associated file if exists
                if ($story->file_url && $story->type !== 'text') {
                    $this->mediaService->deleteFile($story->content);
                }

                // Soft delete the story
                $story->delete();
                $deletedCount++;

                $this->line("Deleted story ID: {$story->id}");
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("Failed to delete story ID: {$story->id} - {$e->getMessage()}");
                Log::error('Failed to cleanup expired story', [
                    'story_id' => $story->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Cleanup completed. Deleted: {$deletedCount}, Failed: {$failedCount}");

        return Command::SUCCESS;
    }
}
