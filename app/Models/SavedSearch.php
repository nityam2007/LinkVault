<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'saved_searches';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'query',
        'filters',
        'is_smart_collection',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_smart_collection' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this saved search.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get filter value by key.
     */
    public function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    /**
     * Check if this is a smart collection.
     */
    public function isSmartCollection(): bool
    {
        return $this->is_smart_collection;
    }

    /**
     * Scope for smart collections.
     */
    public function scopeSmartCollections($query)
    {
        return $query->where('is_smart_collection', true);
    }
}
