<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Collection;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for searching bookmarks with full-text and filters.
 */
class SearchService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Search bookmarks with filters.
     */
    public function search(User $user, array $params): LengthAwarePaginator
    {
        $query = Bookmark::query()
            ->with(['tags', 'collection'])
            ->where('user_id', $user->id);

        // Full-text search
        if (!empty($params['q'])) {
            $searchTerm = $this->sanitizeSearchTerm($params['q']);
            
            if (!empty($params['search_archived']) && $params['search_archived'] === true) {
                // Search in both bookmark fields and archived content
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw("MATCH(title, description, url) AGAINST(? IN BOOLEAN MODE)", [$searchTerm])
                      ->orWhereHas('archive', function ($archiveQuery) use ($searchTerm) {
                          $archiveQuery->whereRaw("MATCH(article_text) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
                      });
                });
            } else {
                // Search only in bookmark fields
                $query->whereRaw("MATCH(title, description, url) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
            }
        }

        // Filter by collection
        if (!empty($params['collection_id'])) {
            if ($params['collection_id'] === 'none') {
                $query->whereNull('collection_id');
            } else {
                $collectionIds = $this->getCollectionWithDescendants((int) $params['collection_id']);
                $query->whereIn('collection_id', $collectionIds);
            }
        }

        // Filter by tags (AND logic by default)
        if (!empty($params['tags'])) {
            $tagIds = is_array($params['tags']) ? $params['tags'] : explode(',', $params['tags']);
            $tagLogic = $params['tag_logic'] ?? 'and';

            if ($tagLogic === 'or') {
                $query->withAnyTags($tagIds);
            } else {
                $query->withAllTags($tagIds);
            }
        }

        // Filter by domain
        if (!empty($params['domain'])) {
            $query->where('domain', $params['domain']);
        }

        // Filter by favorite status
        if (isset($params['is_favorite'])) {
            $query->where('is_favorite', filter_var($params['is_favorite'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by archived status
        if (isset($params['is_archived'])) {
            $query->where('is_archived', filter_var($params['is_archived'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by archive status
        if (!empty($params['archive_status'])) {
            $query->where('archive_status', $params['archive_status']);
        }

        // Date range filter
        if (!empty($params['date_from'])) {
            $query->where('created_at', '>=', $params['date_from']);
        }
        if (!empty($params['date_to'])) {
            $query->where('created_at', '<=', $params['date_to']);
        }

        // Sorting
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortDir = $params['sort_dir'] ?? 'desc';
        $allowedSorts = ['created_at', 'updated_at', 'title', 'domain'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        // Pagination
        $perPage = min((int) ($params['per_page'] ?? 50), 100);

        return $query->paginate($perPage);
    }

    /**
     * Get autocomplete suggestions.
     */
    public function autocomplete(User $user, string $query, int $limit = 10): array
    {
        $cacheKey = "autocomplete:{$user->id}:" . md5($query);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $query, $limit) {
            $suggestions = [];

            // Search in titles
            $titleMatches = Bookmark::where('user_id', $user->id)
                ->where('title', 'LIKE', $query . '%')
                ->select('title')
                ->distinct()
                ->limit($limit)
                ->pluck('title')
                ->toArray();

            foreach ($titleMatches as $title) {
                $suggestions[] = [
                    'type' => 'bookmark',
                    'text' => $title,
                ];
            }

            // Search in domains
            $domainMatches = Bookmark::where('user_id', $user->id)
                ->where('domain', 'LIKE', $query . '%')
                ->select('domain')
                ->distinct()
                ->limit(5)
                ->pluck('domain')
                ->toArray();

            foreach ($domainMatches as $domain) {
                $suggestions[] = [
                    'type' => 'domain',
                    'text' => $domain,
                ];
            }

            return array_slice($suggestions, 0, $limit);
        });
    }

    /**
     * Get search statistics for user.
     */
    public function getStats(User $user): array
    {
        $cacheKey = "search_stats:{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return [
                'total_bookmarks' => Bookmark::where('user_id', $user->id)->count(),
                'archived_count' => Bookmark::where('user_id', $user->id)->where('is_archived', true)->count(),
                'favorite_count' => Bookmark::where('user_id', $user->id)->where('is_favorite', true)->count(),
                'collection_count' => Collection::where('user_id', $user->id)->count(),
                'tag_count' => Tag::where('user_id', $user->id)->count(),
                'domain_count' => Bookmark::where('user_id', $user->id)->distinct('domain')->count('domain'),
                'top_domains' => $this->getTopDomains($user->id, 10),
            ];
        });
    }

    /**
     * Get top domains for user.
     */
    public function getTopDomains(int $userId, int $limit = 10): array
    {
        return Bookmark::where('user_id', $userId)
            ->select('domain', DB::raw('COUNT(*) as count'))
            ->whereNotNull('domain')
            ->groupBy('domain')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get all domains for user (for filter dropdown).
     */
    public function getAllDomains(int $userId): array
    {
        $cacheKey = "domains:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return Bookmark::where('user_id', $userId)
                ->select('domain', DB::raw('COUNT(*) as count'))
                ->whereNotNull('domain')
                ->groupBy('domain')
                ->orderBy('domain')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get collection with all descendant IDs.
     */
    private function getCollectionWithDescendants(int $collectionId): array
    {
        $ids = [$collectionId];
        
        $children = DB::table('collections')
            ->where('parent_id', $collectionId)
            ->pluck('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getCollectionWithDescendants($childId));
        }

        return $ids;
    }

    /**
     * Sanitize search term for full-text search.
     */
    private function sanitizeSearchTerm(string $term): string
    {
        // Remove special characters that could break BOOLEAN MODE
        $term = preg_replace('/[+\-><\(\)~*\"@]+/', ' ', $term);
        
        // Trim and add wildcard for partial matching
        $term = trim($term);
        
        // Add + before each word for AND behavior
        $words = preg_split('/\s+/', $term);
        $words = array_filter($words, fn($w) => strlen($w) >= 2);
        
        if (empty($words)) {
            return '';
        }

        return '+' . implode(' +', $words) . '*';
    }

    /**
     * Clear search cache for user.
     */
    public function clearCache(int $userId): void
    {
        Cache::forget("search_stats:{$userId}");
        Cache::forget("domains:{$userId}");
    }
}
