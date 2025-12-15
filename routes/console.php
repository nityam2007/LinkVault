<?php

use App\Jobs\ArchiveBookmarkJob;
use App\Models\Bookmark;
use App\Models\Tag;
use App\Services\SearchService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Archive a single bookmark
Artisan::command('bookmark:archive {id} {--force}', function (int $id, bool $force = false) {
    $bookmark = Bookmark::findOrFail($id);
    
    $this->info("Queuing archive for bookmark: {$bookmark->title}");
    ArchiveBookmarkJob::dispatch($bookmark, $force)->onQueue('archives');
    $this->info("Archive job queued.");
})->purpose('Archive a bookmark');

// Archive all pending bookmarks
Artisan::command('bookmarks:archive-pending', function () {
    $bookmarks = Bookmark::where('archive_status', 'none')
        ->orWhere('archive_status', 'failed')
        ->limit(100)
        ->get();

    $count = $bookmarks->count();
    $this->info("Queuing {$count} bookmarks for archiving...");

    foreach ($bookmarks as $bookmark) {
        $bookmark->markArchivePending();
        ArchiveBookmarkJob::dispatch($bookmark)->onQueue('archives');
    }

    $this->info("Done.");
})->purpose('Archive all pending bookmarks');

// Refresh tag usage counts
Artisan::command('tags:refresh-counts', function () {
    $this->info("Refreshing tag usage counts...");
    
    Tag::chunk(100, function ($tags) {
        foreach ($tags as $tag) {
            $tag->refreshUsageCount();
        }
    });

    $this->info("Done.");
})->purpose('Refresh tag usage counts');

// Clear search cache
Artisan::command('search:clear-cache {--user=}', function (?int $userId = null) {
    $searchService = app(SearchService::class);
    
    if ($userId) {
        $searchService->clearCache($userId);
        $this->info("Cleared search cache for user {$userId}");
    } else {
        // Clear all caches
        Artisan::call('cache:clear');
        $this->info("Cleared all caches");
    }
})->purpose('Clear search cache');

// Cleanup orphaned archive files
Artisan::command('archives:cleanup', function () {
    $this->info("Cleaning up orphaned archive files...");
    
    $archivePath = storage_path('app/archives');
    if (!is_dir($archivePath)) {
        $this->info("No archives directory found.");
        return;
    }

    $cleaned = 0;
    $userDirs = glob($archivePath . '/*', GLOB_ONLYDIR);
    
    foreach ($userDirs as $userDir) {
        $userId = basename($userDir);
        $bookmarkDirs = glob($userDir . '/*', GLOB_ONLYDIR);
        
        foreach ($bookmarkDirs as $bookmarkDir) {
            $bookmarkId = basename($bookmarkDir);
            
            // Check if bookmark exists
            if (!Bookmark::where('id', $bookmarkId)->where('user_id', $userId)->exists()) {
                $this->line("Removing orphaned archive: {$bookmarkDir}");
                \Illuminate\Support\Facades\File::deleteDirectory($bookmarkDir);
                $cleaned++;
            }
        }
    }

    $this->info("Cleaned up {$cleaned} orphaned archives.");
})->purpose('Remove orphaned archive files');

// Scheduled tasks
Schedule::command('archives:cleanup')->weekly();
Schedule::command('tags:refresh-counts')->daily();
