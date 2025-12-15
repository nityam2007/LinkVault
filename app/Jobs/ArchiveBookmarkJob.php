<?php

namespace App\Jobs;

use App\Models\Bookmark;
use App\Services\ArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for archiving a bookmark asynchronously.
 */
class ArchiveBookmarkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Bookmark $bookmark,
        public bool $force = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ArchiveService $archiveService): void
    {
        // Refresh bookmark from DB to get latest status
        $this->bookmark->refresh();
        
        // Skip if already archived (unless forced)
        if (!$this->force && $this->bookmark->hasArchive()) {
            Log::info('Bookmark already archived, skipping', ['bookmark_id' => $this->bookmark->id]);
            return;
        }

        // Skip if another job is currently processing (not just pending)
        if ($this->bookmark->archive_status === 'processing' && !$this->force) {
            Log::info('Bookmark archive in progress by another job, skipping', ['bookmark_id' => $this->bookmark->id]);
            return;
        }

        Log::info('Starting bookmark archive', [
            'bookmark_id' => $this->bookmark->id,
            'url' => $this->bookmark->url,
            'attempt' => $this->attempts(),
        ]);

        if ($this->force) {
            $result = $archiveService->reArchive($this->bookmark);
        } else {
            $result = $archiveService->archiveBookmark($this->bookmark);
        }

        if ($result['success']) {
            Log::info('Bookmark archived successfully', [
                'bookmark_id' => $this->bookmark->id,
                'image_count' => $result['image_count'],
                'word_count' => $result['word_count'],
            ]);
        } else {
            Log::warning('Bookmark archive failed', [
                'bookmark_id' => $this->bookmark->id,
                'error' => $result['error'] ?? 'Unknown error',
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry
            if ($this->attempts() < $this->tries) {
                throw new \Exception($result['error'] ?? 'Archive failed');
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Bookmark archive job failed permanently', [
            'bookmark_id' => $this->bookmark->id,
            'url' => $this->bookmark->url,
            'error' => $exception?->getMessage(),
        ]);

        $this->bookmark->markArchiveFailed();
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'archive',
            'bookmark:' . $this->bookmark->id,
            'user:' . $this->bookmark->user_id,
        ];
    }
}
