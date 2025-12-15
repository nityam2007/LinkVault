<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'color',
        'usage_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    /**
     * Get the user that owns the tag.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all bookmarks with this tag.
     */
    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Bookmark::class, 'bookmark_tags');
    }

    /**
     * Find or create a tag for a user.
     */
    public static function findOrCreateForUser(int $userId, string $name, ?string $color = null): static
    {
        $tag = static::where('user_id', $userId)
            ->where('name', $name)
            ->first();

        if (!$tag) {
            $tag = static::create([
                'user_id' => $userId,
                'name' => $name,
                'color' => $color,
            ]);
        }

        return $tag;
    }

    /**
     * Find or create multiple tags for a user.
     */
    public static function findOrCreateManyForUser(int $userId, array $tagNames): array
    {
        $tags = [];
        foreach ($tagNames as $name) {
            if (!empty(trim($name))) {
                $tags[] = static::findOrCreateForUser($userId, trim($name));
            }
        }
        return $tags;
    }

    /**
     * Scope for autocomplete search.
     */
    public function scopeAutocomplete($query, string $term)
    {
        return $query->where('name', 'LIKE', $term . '%')
            ->orderBy('usage_count', 'desc')
            ->limit(10);
    }

    /**
     * Scope to get popular tags.
     */
    public function scopePopular($query, int $limit = 20)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    /**
     * Update usage count based on actual bookmark count.
     */
    public function refreshUsageCount(): void
    {
        $this->usage_count = $this->bookmarks()->count();
        $this->save();
    }

    /**
     * Generate random color if not set.
     */
    public static function generateRandomColor(): string
    {
        $colors = [
            '#EF4444', '#F97316', '#F59E0B', '#EAB308', '#84CC16',
            '#22C55E', '#10B981', '#14B8A6', '#06B6D4', '#0EA5E9',
            '#3B82F6', '#6366F1', '#8B5CF6', '#A855F7', '#D946EF',
            '#EC4899', '#F43F5E',
        ];
        return $colors[array_rand($colors)];
    }
}
