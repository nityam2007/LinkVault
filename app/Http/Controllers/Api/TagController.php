<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Handles tag operations.
 */
class TagController extends Controller
{
    /**
     * List all tags for user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "tags:{$user->id}";

        $tags = Cache::remember($cacheKey, 300, function () use ($user) {
            return Tag::where('user_id', $user->id)
                ->orderBy('usage_count', 'desc')
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'tags' => $tags,
        ]);
    }

    /**
     * Autocomplete tags.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $tags = Tag::where('user_id', $request->user()->id)
            ->autocomplete($validated['q'])
            ->get();

        return response()->json([
            'tags' => $tags,
        ]);
    }

    /**
     * Create a new tag.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $user = $request->user();

        // Check if tag already exists
        $existingTag = Tag::where('user_id', $user->id)
            ->where('name', $validated['name'])
            ->first();

        if ($existingTag) {
            return response()->json([
                'message' => 'Tag already exists',
                'tag' => $existingTag,
            ]);
        }

        $tag = Tag::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? Tag::generateRandomColor(),
        ]);

        $this->clearCache($user->id);

        return response()->json([
            'message' => 'Tag created successfully',
            'tag' => $tag,
        ], 201);
    }

    /**
     * Update a tag.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tag = Tag::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // Check for duplicate name
        if (isset($validated['name']) && $validated['name'] !== $tag->name) {
            $exists = Tag::where('user_id', $request->user()->id)
                ->where('name', $validated['name'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'Tag name already exists'], 422);
            }
        }

        $tag->update($validated);
        $this->clearCache($request->user()->id);

        return response()->json([
            'message' => 'Tag updated successfully',
            'tag' => $tag,
        ]);
    }

    /**
     * Delete a tag.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tag = Tag::where('user_id', $request->user()->id)->findOrFail($id);
        $tag->delete();
        
        $this->clearCache($request->user()->id);

        return response()->json([
            'message' => 'Tag deleted successfully',
        ]);
    }

    /**
     * Merge tags.
     */
    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_ids' => ['required', 'array', 'min:1'],
            'source_ids.*' => ['integer', 'exists:tags,id'],
            'target_id' => ['required', 'integer', 'exists:tags,id'],
        ]);

        $user = $request->user();
        
        // Verify ownership
        $sourceTags = Tag::where('user_id', $user->id)
            ->whereIn('id', $validated['source_ids'])
            ->get();

        $targetTag = Tag::where('user_id', $user->id)
            ->where('id', $validated['target_id'])
            ->firstOrFail();

        // Move all bookmark associations
        foreach ($sourceTags as $sourceTag) {
            $bookmarkIds = $sourceTag->bookmarks()->pluck('bookmarks.id')->toArray();
            
            foreach ($bookmarkIds as $bookmarkId) {
                // Add target tag if not already present
                $targetTag->bookmarks()->syncWithoutDetaching([$bookmarkId]);
            }

            $sourceTag->delete();
        }

        // Refresh usage count
        $targetTag->refreshUsageCount();
        $this->clearCache($user->id);

        return response()->json([
            'message' => 'Tags merged successfully',
            'target_tag' => $targetTag,
        ]);
    }

    /**
     * Get tag cloud data.
     */
    public function cloud(Request $request): JsonResponse
    {
        $tags = Tag::where('user_id', $request->user()->id)
            ->where('usage_count', '>', 0)
            ->orderBy('usage_count', 'desc')
            ->limit(50)
            ->get(['id', 'name', 'color', 'usage_count']);

        // Calculate relative sizes (1-5)
        $maxCount = $tags->max('usage_count') ?: 1;
        $minCount = $tags->min('usage_count') ?: 1;
        $range = max(1, $maxCount - $minCount);

        $cloudData = $tags->map(function ($tag) use ($minCount, $range) {
            $normalized = ($tag->usage_count - $minCount) / $range;
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'count' => $tag->usage_count,
                'size' => 1 + round($normalized * 4), // 1-5 scale
            ];
        });

        return response()->json([
            'cloud' => $cloudData,
        ]);
    }

    /**
     * Get popular tags.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 20), 50);

        $tags = Tag::where('user_id', $request->user()->id)
            ->popular($limit)
            ->get();

        return response()->json([
            'tags' => $tags,
        ]);
    }

    /**
     * Clear tag cache.
     */
    private function clearCache(int $userId): void
    {
        Cache::forget("tags:{$userId}");
    }
}
