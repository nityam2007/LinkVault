<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookmarkArchive extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'bookmark_archives';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'bookmark_id',
        'article_text',
        'article_html',
        'html_snapshot_path',
        'author',
        'published_date',
        'word_count',
        'reading_time_minutes',
        'language',
        'site_name',
        'og_title',
        'og_description',
        'og_image_path',
        'images_directory',
        'image_count',
        'primary_image_path',
        'metadata',
        'archived_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_date' => 'datetime',
            'archived_at' => 'datetime',
            'word_count' => 'integer',
            'reading_time_minutes' => 'integer',
            'image_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the bookmark this archive belongs to.
     */
    public function bookmark(): BelongsTo
    {
        return $this->belongsTo(Bookmark::class);
    }

    /**
     * Get all images for this archive.
     */
    public function images(): HasMany
    {
        return $this->hasMany(BookmarkImage::class, 'archive_id')->orderBy('position');
    }

    /**
     * Get the primary image.
     */
    public function primaryImage(): ?BookmarkImage
    {
        return $this->images()->where('is_primary', true)->first();
    }

    /**
     * Calculate reading time from word count.
     */
    public static function calculateReadingTime(int $wordCount): int
    {
        // Average reading speed: 200-250 words per minute
        return max(1, (int) ceil($wordCount / 225));
    }

    /**
     * Get storage path for HTML snapshot.
     */
    public function getSnapshotPath(): ?string
    {
        if ($this->html_snapshot_path) {
            return storage_path('app/' . $this->html_snapshot_path);
        }
        return null;
    }

    /**
     * Get the full images directory path.
     */
    public function getImagesDirectoryPath(): ?string
    {
        if ($this->images_directory) {
            return storage_path('app/' . $this->images_directory);
        }
        return null;
    }

    /**
     * Scope for searching in article text.
     */
    public function scopeSearchText($query, string $term)
    {
        return $query->whereRaw(
            "MATCH(article_text) AGAINST(? IN BOOLEAN MODE)",
            [$term . '*']
        );
    }

    /**
     * Check if archive has extracted content.
     */
    public function hasContent(): bool
    {
        return !empty($this->article_text) || !empty($this->article_html);
    }

    /**
     * Get excerpt of article text.
     */
    public function getExcerpt(int $length = 200): string
    {
        if (empty($this->article_text)) {
            return '';
        }

        if (strlen($this->article_text) <= $length) {
            return $this->article_text;
        }

        return substr($this->article_text, 0, $length) . '...';
    }
}
