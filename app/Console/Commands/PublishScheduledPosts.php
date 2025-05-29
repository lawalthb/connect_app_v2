<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';
    protected $description = 'Publish posts that are scheduled for now or earlier';

    public function handle()
    {
        $now = Carbon::now();

        $scheduledPosts = Post::where('is_published', false)
                            ->where('scheduled_at', '<=', $now)
                            ->whereNotNull('scheduled_at')
                            ->get();

        $publishedCount = 0;

        foreach ($scheduledPosts as $post) {
            try {
                $post->update([
                    'is_published' => true,
                    'published_at' => $now,
                    'scheduled_at' => null,
                ]);

                $publishedCount++;

                Log::info('Scheduled post published', [
                    'post_id' => $post->id,
                    'user_id' => $post->user_id,
                    'scheduled_for' => $post->scheduled_at,
                    'published_at' => $now
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to publish scheduled post', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Published {$publishedCount} scheduled posts");

        return 0;
    }
}
