<?php

namespace App\Jobs;

use App\Models\Bookmark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for archiving multiple bookmarks in batch.
 */
class BatchArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour for batch operations

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $bookmarkIds,
        public int $userId,
        public bool $force = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting batch archive', [
            'user_id' => $this->userId,
            'count' => count($this->bookmarkIds),
        ]);

        $bookmarks = Bookmark::whereIn('id', $this->bookmarkIds)
            ->where('user_id', $this->userId)
            ->get();

        foreach ($bookmarks as $bookmark) {
            // Dispatch individual archive jobs
            ArchiveBookmarkJob::dispatch($bookmark, $this->force)
                ->onQueue('archives');
        }

        Log::info('Batch archive jobs dispatched', [
            'user_id' => $this->userId,
            'dispatched' => $bookmarks->count(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'batch_archive',
            'user:' . $this->userId,
        ];
    }
}
