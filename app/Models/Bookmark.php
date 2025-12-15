<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Bookmark extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'collection_id',
        'url',
        'url_hash',
        'title',
        'description',
        'notes',
        'favicon_path',
        'domain',
        'is_archived',
        'is_favorite',
        'archive_status',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'is_favorite' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Appended attributes for JSON serialization.
     */
    protected $appends = ['og_image', 'site_name', 'author', 'reading_time'];

    /**
     * Get the OG image from the archive.
     */
    public function getOgImageAttribute(): ?string
    {
        if (!$this->relationLoaded('archive') || !$this->archive) {
            return null;
        }
        
        // Return local path or primary image
        if ($this->archive->og_image_path) {
            return '/storage/' . $this->archive->og_image_path;
        }
        if ($this->archive->primary_image_path) {
            return '/storage/' . $this->archive->primary_image_path;
        }
        return null;
    }

    /**
     * Get the site name from the archive metadata.
     */
    public function getSiteNameAttribute(): ?string
    {
        if (!$this->relationLoaded('archive') || !$this->archive) {
            return null;
        }
        return $this->archive->site_name;
    }

    /**
     * Get the author from the archive.
     */
    public function getAuthorAttribute(): ?string
    {
        if (!$this->relationLoaded('archive') || !$this->archive) {
            return null;
        }
        return $this->archive->author;
    }

    /**
     * Get the reading time from the archive.
     */
    public function getReadingTimeAttribute(): ?int
    {
        if (!$this->relationLoaded('archive') || !$this->archive) {
            return null;
        }
        return $this->archive->reading_time_minutes;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Bookmark $bookmark) {
            $bookmark->url_hash = hash('sha256', $bookmark->url);
            $bookmark->domain = parse_url($bookmark->url, PHP_URL_HOST);
        });

        static::updating(function (Bookmark $bookmark) {
            if ($bookmark->isDirty('url')) {
                $bookmark->url_hash = hash('sha256', $bookmark->url);
                $bookmark->domain = parse_url($bookmark->url, PHP_URL_HOST);
            }
        });

        static::created(function (Bookmark $bookmark) {
            if ($bookmark->collection_id) {
                $bookmark->collection?->updateBookmarkCount();
            }
        });

        static::deleted(function (Bookmark $bookmark) {
            if ($bookmark->collection_id) {
                $bookmark->collection?->updateBookmarkCount();
            }
        });
    }

    /**
     * Get the user that owns the bookmark.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the collection this bookmark belongs to.
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * Get all tags for this bookmark.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'bookmark_tags');
    }

    /**
     * Get the archive for this bookmark.
     */
    public function archive(): HasOne
    {
        return $this->hasOne(BookmarkArchive::class);
    }

    /**
     * Check if bookmark has been archived.
     */
    public function hasArchive(): bool
    {
        return $this->archive_status === 'completed';
    }

    /**
     * Check if archive is pending or processing.
     */
    public function isArchiving(): bool
    {
        return in_array($this->archive_status, ['pending', 'processing']);
    }

    /**
     * Mark archive as pending.
     */
    public function markArchivePending(): void
    {
        $this->archive_status = 'pending';
        $this->save();
    }

    /**
     * Mark archive as processing.
     */
    public function markArchiveProcessing(): void
    {
        $this->archive_status = 'processing';
        $this->save();
    }

    /**
     * Mark archive as completed.
     */
    public function markArchiveCompleted(): void
    {
        $this->archive_status = 'completed';
        $this->is_archived = true;
        $this->save();
    }

    /**
     * Mark archive as failed.
     */
    public function markArchiveFailed(): void
    {
        $this->archive_status = 'failed';
        $this->save();
    }

    /**
     * Sync tags and update usage counts.
     */
    public function syncTagsWithCounts(array $tagIds): void
    {
        $oldTagIds = $this->tags()->pluck('tags.id')->toArray();
        
        $this->tags()->sync($tagIds);

        // Decrement old tags
        Tag::whereIn('id', array_diff($oldTagIds, $tagIds))
            ->decrement('usage_count');

        // Increment new tags
        Tag::whereIn('id', array_diff($tagIds, $oldTagIds))
            ->increment('usage_count');
    }

    /**
     * Get storage path for this bookmark's archive.
     */
    public function getArchiveStoragePath(): string
    {
        return "archives/{$this->user_id}/{$this->id}";
    }

    /**
     * Scope for full-text search.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->whereRaw(
            "MATCH(title, description, url) AGAINST(? IN BOOLEAN MODE)",
            [$term . '*']
        );
    }

    /**
     * Scope for searching in archived content.
     */
    public function scopeSearchArchived($query, string $term)
    {
        return $query->whereHas('archive', function ($q) use ($term) {
            $q->whereRaw(
                "MATCH(article_text) AGAINST(? IN BOOLEAN MODE)",
                [$term . '*']
            );
        });
    }

    /**
     * Scope to filter by tags (AND logic).
     */
    public function scopeWithAllTags($query, array $tagIds)
    {
        foreach ($tagIds as $tagId) {
            $query->whereHas('tags', function ($q) use ($tagId) {
                $q->where('tags.id', $tagId);
            });
        }
        return $query;
    }

    /**
     * Scope to filter by tags (OR logic).
     */
    public function scopeWithAnyTags($query, array $tagIds)
    {
        return $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        });
    }

    /**
     * Scope to filter favorites.
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope to filter archived.
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Check if a URL already exists for a user.
     */
    public static function urlExistsForUser(int $userId, string $url): bool
    {
        $hash = hash('sha256', $url);
        return static::where('user_id', $userId)
            ->where('url_hash', $hash)
            ->exists();
    }

    /**
     * Find existing bookmark by URL for a user.
     */
    public static function findByUrl(int $userId, string $url): ?static
    {
        $hash = hash('sha256', $url);
        return static::where('user_id', $userId)
            ->where('url_hash', $hash)
            ->first();
    }
}
