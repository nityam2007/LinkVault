<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job for processing bookmark imports asynchronously.
 */
class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes for large imports

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ImportJob $importJob,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImportService $importService): void
    {
        Log::info('Starting import job', [
            'job_id' => $this->importJob->id,
            'user_id' => $this->importJob->user_id,
            'file_type' => $this->importJob->file_type,
        ]);

        try {
            $importService->processImport($this->importJob);

            Log::info('Import job completed', [
                'job_id' => $this->importJob->id,
                'total' => $this->importJob->total_items,
                'success' => $this->importJob->success_count,
                'failed' => $this->importJob->failed_count,
                'skipped' => $this->importJob->skipped_count,
            ]);

        } catch (\Exception $e) {
            Log::error('Import job failed', [
                'job_id' => $this->importJob->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Clean up uploaded file
            if ($this->importJob->file_path && Storage::exists($this->importJob->file_path)) {
                Storage::delete($this->importJob->file_path);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Import job failed permanently', [
            'job_id' => $this->importJob->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->importJob->markFailed($exception?->getMessage() ?? 'Unknown error');

        // Clean up uploaded file
        if ($this->importJob->file_path && Storage::exists($this->importJob->file_path)) {
            Storage::delete($this->importJob->file_path);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'import',
            'import_job:' . $this->importJob->id,
            'user:' . $this->importJob->user_id,
        ];
    }
}
