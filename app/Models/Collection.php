<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'description',
        'color',
        'icon',
        'is_public',
        'public_slug',
        'sort_order',
        'bookmark_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'sort_order' => 'integer',
            'bookmark_count' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Collection $collection) {
            if ($collection->is_public && empty($collection->public_slug)) {
                $collection->public_slug = Str::slug($collection->name) . '-' . Str::random(8);
            }
        });

        static::updating(function (Collection $collection) {
            if ($collection->isDirty('is_public') && $collection->is_public && empty($collection->public_slug)) {
                $collection->public_slug = Str::slug($collection->name) . '-' . Str::random(8);
            }
        });
    }

    /**
     * Get the user that owns the collection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent collection.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'parent_id');
    }

    /**
     * Get child collections.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Collection::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all bookmarks in this collection.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Get all descendants (nested children) recursively.
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestor collections.
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($ancestors, $parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the full path of the collection.
     */
    public function getFullPathAttribute(): string
    {
        $ancestors = $this->ancestors();
        $names = array_map(fn($c) => $c->name, $ancestors);
        $names[] = $this->name;
        return implode(' / ', $names);
    }

    /**
     * Check if collection is visible to a user.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->is_public) {
            return true;
        }

        return $user && $user->id === $this->user_id;
    }

    /**
     * Update bookmark count.
     */
    public function updateBookmarkCount(): void
    {
        $this->bookmark_count = $this->bookmarks()->count();
        $this->save();
    }

    /**
     * Get total bookmark count including all descendants.
     */
    public function getTotalBookmarkCountAttribute(): int
    {
        $count = $this->bookmarks()->count();
        
        foreach ($this->children as $child) {
            $count += $child->total_bookmark_count;
        }
        
        return $count;
    }

    /**
     * Get all descendant collection IDs (for querying bookmarks).
     */
    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];
        
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }
        
        return $ids;
    }

    /**
     * Scope to get root collections (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get public collections.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
