<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles bookmark import and export.
 */
class ImportExportController extends Controller
{
    public function __construct(
        private ExportService $exportService,
    ) {}

    /**
     * Upload and start import job.
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50MB max
            'duplicate_handling' => ['in:skip,merge,keep_both'],
            'collection_id' => ['nullable', 'exists:collections,id'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        // Determine file type
        $fileType = match (true) {
            $extension === 'html' || $extension === 'htm' => 'html',
            $extension === 'json' => 'json',
            $extension === 'csv' => 'csv',
            str_contains($mimeType, 'html') => 'html',
            str_contains($mimeType, 'json') => 'json',
            str_contains($mimeType, 'csv') => 'csv',
            default => null,
        };

        if (!$fileType) {
            return response()->json([
                'error' => 'Unsupported file type. Please upload HTML, JSON, or CSV.',
            ], 422);
        }

        $user = $request->user();

        // Store uploaded file
        $path = $file->store('imports/' . $user->id);

        // Create import job
        $importJob = ImportJob::create([
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $fileType,
            'duplicate_handling' => $validated['duplicate_handling'] ?? 'skip',
            'target_collection_id' => $validated['collection_id'] ?? null,
            'status' => 'pending',
        ]);

        // Dispatch job
        ProcessImportJob::dispatch($importJob)->onQueue('imports');

        return response()->json([
            'message' => 'Import job created',
            'job' => [
                'id' => $importJob->id,
                'status' => $importJob->status,
                'file_name' => $importJob->file_name,
                'file_type' => $importJob->file_type,
            ],
        ], 202);
    }

    /**
     * Get import job status.
     */
    public function importStatus(Request $request, int $id): JsonResponse
    {
        $job = ImportJob::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json([
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
                'file_name' => $job->file_name,
                'file_type' => $job->file_type,
                'total_items' => $job->total_items,
                'processed_items' => $job->processed_items,
                'success_count' => $job->success_count,
                'failed_count' => $job->failed_count,
                'skipped_count' => $job->skipped_count,
                'progress_percentage' => $job->getProgressPercentage(),
                'estimated_time_remaining' => $job->getEstimatedTimeRemaining(),
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'error_log' => $job->error_log,
            ],
        ]);
    }

    /**
     * List import jobs.
     */
    public function importHistory(Request $request): JsonResponse
    {
        $jobs = ImportJob::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'jobs' => $jobs->map(fn($job) => [
                'id' => $job->id,
                'status' => $job->status,
                'file_name' => $job->file_name,
                'file_type' => $job->file_type,
                'total_items' => $job->total_items,
                'success_count' => $job->success_count,
                'failed_count' => $job->failed_count,
                'created_at' => $job->created_at,
                'completed_at' => $job->completed_at,
            ]),
        ]);
    }

    /**
     * Cancel an import job.
     */
    public function cancelImport(Request $request, int $id): JsonResponse
    {
        $job = ImportJob::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'processing'])
            ->findOrFail($id);

        $job->markCancelled();

        // Clean up file
        if ($job->file_path && Storage::exists($job->file_path)) {
            Storage::delete($job->file_path);
        }

        return response()->json([
            'message' => 'Import job cancelled',
        ]);
    }

    /**
     * Export bookmarks.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'format' => ['required', 'in:html,json,csv,markdown,md'],
            'collection_id' => ['nullable', 'exists:collections,id'],
            'bookmark_ids' => ['nullable', 'array'],
            'bookmark_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $format = $validated['format'];
        $collectionId = $validated['collection_id'] ?? null;
        $bookmarkIds = $validated['bookmark_ids'] ?? null;

        try {
            $content = $this->exportService->export($user, $format, $collectionId, $bookmarkIds);
            $filename = $this->exportService->getFilename($format);
            $contentType = $this->exportService->getContentType($format);

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $filename, [
                'Content-Type' => $contentType,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get export preview (count of bookmarks).
     */
    public function exportPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collection_id' => ['nullable', 'exists:collections,id'],
            'bookmark_ids' => ['nullable', 'array'],
            'bookmark_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $query = $user->bookmarks();

        if (!empty($validated['collection_id'])) {
            $query->where('collection_id', $validated['collection_id']);
        }

        if (!empty($validated['bookmark_ids'])) {
            $query->whereIn('id', $validated['bookmark_ids']);
        }

        $count = $query->count();

        return response()->json([
            'count' => $count,
            'available_formats' => ['html', 'json', 'csv', 'markdown'],
        ]);
    }
}
