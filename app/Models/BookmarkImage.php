<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BookmarkImage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'bookmark_images';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'archive_id',
        'original_url',
        'local_path',
        'file_name',
        'file_size',
        'width',
        'height',
        'mime_type',
        'alt_text',
        'caption',
        'is_primary',
        'position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_primary' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Get the archive this image belongs to.
     */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(BookmarkArchive::class, 'archive_id');
    }

    /**
     * Get the full local path.
     */
    public function getFullPath(): string
    {
        return storage_path('app/' . $this->local_path);
    }

    /**
     * Get the public URL for the image.
     */
    public function getPublicUrl(): string
    {
        return url('storage/' . $this->local_path);
    }

    /**
     * Check if the image file exists.
     */
    public function fileExists(): bool
    {
        return Storage::exists($this->local_path);
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanFileSize(): string
    {
        $bytes = $this->file_size ?? 0;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get dimensions as string.
     */
    public function getDimensions(): ?string
    {
        if ($this->width && $this->height) {
            return "{$this->width}x{$this->height}";
        }
        return null;
    }

    /**
     * Check if this is a valid image type.
     */
    public function isValidType(): bool
    {
        $validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($this->mime_type, $validTypes);
    }

    /**
     * Delete the image file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::delete($this->local_path);
        }
        return true;
    }

    /**
     * Boot the model to handle file cleanup.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (BookmarkImage $image) {
            $image->deleteFile();
        });
    }
}
