<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ArchiveBookmarkJob;
use App\Jobs\BatchArchiveJob;
use App\Models\Bookmark;
use App\Models\Tag;
use App\Services\MetadataExtractor;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles bookmark CRUD operations.
 */
class BookmarkController extends Controller
{
    public function __construct(
        private SearchService $searchService,
        private MetadataExtractor $metadataExtractor,
    ) {}

    /**
     * List bookmarks with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bookmarks = $this->searchService->search($user, $request->all());

        return response()->json([
            'bookmarks' => $bookmarks->items(),
            'pagination' => [
                'total' => $bookmarks->total(),
                'per_page' => $bookmarks->perPage(),
                'current_page' => $bookmarks->currentPage(),
                'last_page' => $bookmarks->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new bookmark.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'collection_id' => ['nullable', 'exists:collections,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'is_favorite' => ['boolean'],
            'auto_archive' => ['boolean'],
        ]);

        $user = $request->user();

        // Check for collection ownership
        if (!empty($validated['collection_id'])) {
            $collectionExists = $user->collections()->where('id', $validated['collection_id'])->exists();
            if (!$collectionExists) {
                return response()->json(['error' => 'Collection not found'], 404);
            }
        }

        // Check for duplicate URL
        $existingBookmark = Bookmark::findByUrl($user->id, $validated['url']);
        if ($existingBookmark) {
            return response()->json([
                'error' => 'Bookmark with this URL already exists',
                'existing_bookmark' => $existingBookmark,
            ], 409);
        }

        // Fetch metadata if title/description not provided
        $title = $validated['title'] ?? null;
        $description = $validated['description'] ?? null;
        
        if (empty($title) || empty($description)) {
            $metadata = $this->fetchQuickMetadata($validated['url']);
            $title = $title ?? $metadata['title'] ?? $this->extractTitleFromUrl($validated['url']);
            $description = $description ?? $metadata['description'] ?? null;
        }

        // Create bookmark
        $bookmark = Bookmark::create([
            'user_id' => $user->id,
            'url' => $validated['url'],
            'title' => $title,
            'description' => $description,
            'notes' => $validated['notes'] ?? null,
            'collection_id' => $validated['collection_id'] ?? null,
            'is_favorite' => $validated['is_favorite'] ?? false,
        ]);

        // Handle tags
        if (!empty($validated['tags'])) {
            $tags = Tag::findOrCreateManyForUser($user->id, $validated['tags']);
            $tagIds = array_map(fn($tag) => $tag->id, $tags);
            $bookmark->syncTagsWithCounts($tagIds);
        }

        // Queue archive if requested
        if ($validated['auto_archive'] ?? true) {
            $bookmark->markArchivePending();
            ArchiveBookmarkJob::dispatch($bookmark)->onQueue('archives');
        }

        $bookmark->load(['tags', 'collection']);

        return response()->json([
            'message' => 'Bookmark created successfully',
            'bookmark' => $bookmark,
        ], 201);
    }

    /**
     * Get a single bookmark.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $bookmark = Bookmark::with(['tags', 'collection', 'archive.images'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'bookmark' => $bookmark,
        ]);
    }

    /**
     * Update a bookmark.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bookmark = Bookmark::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'collection_id' => ['nullable', 'exists:collections,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'is_favorite' => ['boolean'],
            'is_archived' => ['boolean'],
        ]);

        // Check URL uniqueness if changed
        if (isset($validated['url']) && $validated['url'] !== $bookmark->url) {
            $existingBookmark = Bookmark::findByUrl($request->user()->id, $validated['url']);
            if ($existingBookmark && $existingBookmark->id !== $bookmark->id) {
                return response()->json([
                    'error' => 'Bookmark with this URL already exists',
                ], 409);
            }
        }

        // Update bookmark fields
        $bookmark->fill($validated);
        $bookmark->save();

        // Handle tags
        if (isset($validated['tags'])) {
            $tags = Tag::findOrCreateManyForUser($request->user()->id, $validated['tags']);
            $tagIds = array_map(fn($tag) => $tag->id, $tags);
            $bookmark->syncTagsWithCounts($tagIds);
        }

        $bookmark->load(['tags', 'collection']);

        return response()->json([
            'message' => 'Bookmark updated successfully',
            'bookmark' => $bookmark,
        ]);
    }

    /**
     * Delete a bookmark.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $bookmark = Bookmark::where('user_id', $request->user()->id)->findOrFail($id);
        
        // Delete associated archive and files
        if ($bookmark->archive) {
            $bookmark->archive->images()->delete();
            $bookmark->archive->delete();
        }
        
        $bookmark->delete();

        return response()->json([
            'message' => 'Bookmark deleted successfully',
        ]);
    }

    /**
     * Bulk delete bookmarks.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = Bookmark::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json([
            'message' => "{$deleted} bookmark(s) deleted successfully",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Bulk move bookmarks to collection.
     */
    public function bulkMove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'collection_id' => ['nullable', 'exists:collections,id'],
        ]);

        $updated = Bookmark::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->update(['collection_id' => $validated['collection_id']]);

        return response()->json([
            'message' => "{$updated} bookmark(s) moved successfully",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Bulk add tags to bookmarks.
     */
    public function bulkAddTags(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['string', 'max:100'],
        ]);

        $user = $request->user();
        $tags = Tag::findOrCreateManyForUser($user->id, $validated['tags']);
        $tagIds = array_map(fn($tag) => $tag->id, $tags);

        $bookmarks = Bookmark::where('user_id', $user->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($bookmarks as $bookmark) {
            $existingTagIds = $bookmark->tags()->pluck('tags.id')->toArray();
            $mergedTagIds = array_unique(array_merge($existingTagIds, $tagIds));
            $bookmark->syncTagsWithCounts($mergedTagIds);
        }

        return response()->json([
            'message' => 'Tags added successfully',
            'updated_count' => $bookmarks->count(),
        ]);
    }

    /**
     * Toggle bookmark favorite status.
     */
    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $bookmark = Bookmark::where('user_id', $request->user()->id)->findOrFail($id);
        $bookmark->is_favorite = !$bookmark->is_favorite;
        $bookmark->save();

        return response()->json([
            'message' => $bookmark->is_favorite ? 'Added to favorites' : 'Removed from favorites',
            'is_favorite' => $bookmark->is_favorite,
        ]);
    }

    /**
     * Trigger archive for a bookmark.
     */
    public function archive(Request $request, int $id): JsonResponse
    {
        $bookmark = Bookmark::where('user_id', $request->user()->id)->findOrFail($id);

        if ($bookmark->isArchiving()) {
            return response()->json([
                'message' => 'Archive is already in progress',
                'archive_status' => $bookmark->archive_status,
            ]);
        }

        $force = $request->boolean('force', false);
        $bookmark->markArchivePending();
        ArchiveBookmarkJob::dispatch($bookmark, $force)->onQueue('archives');

        return response()->json([
            'message' => 'Archive job queued',
            'archive_status' => 'pending',
        ]);
    }

    /**
     * Get archived content for a bookmark.
     */
    public function getArchive(Request $request, int $id): JsonResponse
    {
        $bookmark = Bookmark::with(['archive.images'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (!$bookmark->archive) {
            return response()->json([
                'error' => 'No archive available',
                'archive_status' => $bookmark->archive_status,
            ], 404);
        }

        return response()->json([
            'archive' => $bookmark->archive,
        ]);
    }

    /**
     * Batch archive bookmarks.
     */
    public function batchArchive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'force' => ['boolean'],
        ]);

        $user = $request->user();
        
        // Mark all as pending
        Bookmark::where('user_id', $user->id)
            ->whereIn('id', $validated['ids'])
            ->update(['archive_status' => 'pending']);

        // Dispatch batch job
        BatchArchiveJob::dispatch(
            $validated['ids'],
            $user->id,
            $validated['force'] ?? false
        )->onQueue('archives');

        return response()->json([
            'message' => 'Batch archive job queued',
            'count' => count($validated['ids']),
        ]);
    }

    /**
     * Get search statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->searchService->getStats($request->user());

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Extract title from URL (basic fallback).
     */
    private function extractTitleFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        return ($parsed['host'] ?? 'Unknown') . ($parsed['path'] ?? '');
    }

    /**
     * Quickly fetch metadata for a URL (title, description).
     * This is a fast, lightweight fetch - full archiving happens in background.
     *
     * @return array{title: ?string, description: ?string, favicon: ?string}
     */
    private function fetchQuickMetadata(string $url): array
    {
        $result = ['title' => null, 'description' => null, 'favicon' => null];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (!$response->successful()) {
                return $result;
            }

            $html = $response->body();
            
            // Use MetadataExtractor for proper parsing
            $metadata = $this->metadataExtractor->extract($html, $url);

            $result['title'] = $metadata['og_title'] ?? $metadata['title'] ?? null;
            $result['description'] = $metadata['og_description'] ?? $metadata['description'] ?? null;
            $result['favicon'] = $metadata['favicon'] ?? null;

            // Truncate if too long
            if ($result['title'] && strlen($result['title']) > 500) {
                $result['title'] = substr($result['title'], 0, 497) . '...';
            }
            if ($result['description'] && strlen($result['description']) > 5000) {
                $result['description'] = substr($result['description'], 0, 4997) . '...';
            }

        } catch (\Exception $e) {
            Log::warning('Failed to fetch metadata for URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
