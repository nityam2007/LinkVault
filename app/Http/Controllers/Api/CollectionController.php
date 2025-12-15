<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Handles collection CRUD operations.
 */
class CollectionController extends Controller
{
    /**
     * List all collections (tree structure).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "collections_tree:{$user->id}";

        $collections = Cache::remember($cacheKey, 300, function () use ($user) {
            return Collection::withCount('bookmarks')
                ->where('user_id', $user->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });

        // Build tree structure
        $tree = $this->buildTree($collections);
        
        // Add total_bookmark_count to each collection
        $collectionsWithTotals = $this->addTotalCounts($tree, $collections);

        // Option to return flat list
        if ($request->boolean('flat')) {
            $flat = $this->flattenCollections($collectionsWithTotals);
            return response()->json(['collections' => $flat]);
        }

        return response()->json([
            'collections' => $collectionsWithTotals,
        ]);
    }

    /**
     * Build tree structure from flat collection list.
     */
    private function buildTree($collections, $parentId = null, $depth = 0): array
    {
        $branch = [];
        
        // Limit depth to 10 levels
        if ($depth >= 10) {
            return $branch;
        }
        
        foreach ($collections as $collection) {
            if ($collection->parent_id == $parentId) {
                $children = $this->buildTree($collections, $collection->id, $depth + 1);
                
                $item = $collection->toArray();
                $item['children'] = $children;
                $item['depth'] = $depth;
                
                $branch[] = $item;
            }
        }
        
        return $branch;
    }

    /**
     * Recursively add total bookmark counts to collections.
     */
    private function addTotalCounts(array $tree, $allCollections): array
    {
        $result = [];
        
        foreach ($tree as $item) {
            // Find the original collection to get bookmark_count
            $collection = $allCollections->firstWhere('id', $item['id']);
            $item['bookmark_count'] = $collection->bookmarks_count ?? 0;
            
            // Process children recursively
            if (!empty($item['children'])) {
                $item['children'] = $this->addTotalCounts($item['children'], $allCollections);
            }
            
            // Calculate total count (direct + all children)
            $item['total_bookmark_count'] = $this->calculateTotalFromTree($item);
            
            $result[] = $item;
        }
        
        return $result;
    }

    /**
     * Calculate total bookmark count from tree structure.
     */
    private function calculateTotalFromTree(array $item): int
    {
        $count = $item['bookmark_count'] ?? 0;
        
        if (!empty($item['children'])) {
            foreach ($item['children'] as $child) {
                $count += $this->calculateTotalFromTree($child);
            }
        }
        
        return $count;
    }

    /**
     * Flatten nested collection tree.
     */
    private function flattenCollections(array $collections, int $depth = 0): array
    {
        $flat = [];
        foreach ($collections as $item) {
            $children = $item['children'] ?? [];
            $itemCopy = $item;
            unset($itemCopy['children']);
            $itemCopy['depth'] = $depth;
            $flat[] = $itemCopy;

            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenCollections($children, $depth + 1));
            }
        }
        return $flat;
    }

    /**
     * Create a new collection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'exists:collections,id'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_public' => ['boolean'],
        ]);

        $user = $request->user();

        // Verify parent ownership and check depth limit
        if (!empty($validated['parent_id'])) {
            $parent = Collection::where('id', $validated['parent_id'])
                ->where('user_id', $user->id)
                ->first();
            
            if (!$parent) {
                return response()->json(['error' => 'Parent collection not found'], 404);
            }
            
            // Check depth limit (max 10 levels)
            $depth = $this->getCollectionDepth($parent);
            if ($depth >= 9) { // 9 because we're adding one more level
                return response()->json([
                    'error' => 'Maximum nesting depth (10 levels) reached',
                ], 422);
            }
        }

        $collection = Collection::create([
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#3B82F6',
            'icon' => $validated['icon'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        $this->clearCache($user->id);

        return response()->json([
            'message' => 'Collection created successfully',
            'collection' => $collection,
        ], 201);
    }

    /**
     * Get the depth of a collection in the hierarchy.
     */
    private function getCollectionDepth(Collection $collection): int
    {
        $depth = 0;
        $parent = $collection->parent;
        
        while ($parent && $depth < 10) {
            $depth++;
            $parent = $parent->parent;
        }
        
        return $depth;
    }

    /**
     * Get a single collection with bookmarks.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $collection = Collection::with(['children', 'parent'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $collection->load(['bookmarks' => function ($query) {
            $query->with(['tags'])->latest()->limit(50);
        }]);

        return response()->json([
            'collection' => $collection,
        ]);
    }

    /**
     * Update a collection.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $collection = Collection::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'exists:collections,id'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_public' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        // Prevent setting self as parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return response()->json(['error' => 'Collection cannot be its own parent'], 422);
        }

        // Prevent circular reference
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            if ($this->wouldCreateCircularReference($collection, $validated['parent_id'])) {
                return response()->json(['error' => 'This would create a circular reference'], 422);
            }
        }

        $collection->update($validated);
        $this->clearCache($request->user()->id);

        return response()->json([
            'message' => 'Collection updated successfully',
            'collection' => $collection,
        ]);
    }

    /**
     * Delete a collection.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $collection = Collection::where('user_id', $request->user()->id)->findOrFail($id);

        // Move bookmarks to parent or null
        $collection->bookmarks()->update(['collection_id' => $collection->parent_id]);

        // Move children to parent
        $collection->children()->update(['parent_id' => $collection->parent_id]);

        $collection->delete();
        $this->clearCache($request->user()->id);

        return response()->json([
            'message' => 'Collection deleted successfully',
        ]);
    }

    /**
     * Reorder collections.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer', 'exists:collections,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
            'order.*.parent_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;

        foreach ($validated['order'] as $item) {
            Collection::where('id', $item['id'])
                ->where('user_id', $userId)
                ->update([
                    'sort_order' => $item['sort_order'],
                    'parent_id' => $item['parent_id'] ?? null,
                ]);
        }

        $this->clearCache($userId);

        return response()->json([
            'message' => 'Collections reordered successfully',
        ]);
    }

    /**
     * Get public collection by slug.
     */
    public function showPublic(string $slug): JsonResponse
    {
        $collection = Collection::with(['bookmarks' => function ($query) {
            $query->with(['tags'])->latest();
        }])
            ->where('public_slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        return response()->json([
            'collection' => [
                'name' => $collection->name,
                'description' => $collection->description,
                'color' => $collection->color,
                'bookmark_count' => $collection->bookmarks->count(),
                'bookmarks' => $collection->bookmarks,
            ],
        ]);
    }

    /**
     * Generate/regenerate public link.
     */
    public function generatePublicLink(Request $request, int $id): JsonResponse
    {
        $collection = Collection::where('user_id', $request->user()->id)->findOrFail($id);

        $collection->is_public = true;
        $collection->public_slug = Str::slug($collection->name) . '-' . Str::random(8);
        $collection->save();

        $this->clearCache($request->user()->id);

        return response()->json([
            'message' => 'Public link generated',
            'public_url' => url("/public/collections/{$collection->public_slug}"),
            'public_slug' => $collection->public_slug,
        ]);
    }

    /**
     * Remove public link.
     */
    public function removePublicLink(Request $request, int $id): JsonResponse
    {
        $collection = Collection::where('user_id', $request->user()->id)->findOrFail($id);

        $collection->is_public = false;
        $collection->public_slug = null;
        $collection->save();

        $this->clearCache($request->user()->id);

        return response()->json([
            'message' => 'Public link removed',
        ]);
    }

    /**
     * Check if setting parent would create circular reference.
     */
    private function wouldCreateCircularReference(Collection $collection, int $newParentId): bool
    {
        $parent = Collection::find($newParentId);
        
        while ($parent) {
            if ($parent->id === $collection->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * Clear collection cache.
     */
    private function clearCache(int $userId): void
    {
        Cache::forget("collections_tree:{$userId}");
    }
}
