<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'import_jobs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'file_type',
        'total_items',
        'processed_items',
        'success_count',
        'failed_count',
        'skipped_count',
        'status',
        'duplicate_handling',
        'target_collection_id',
        'error_log',
        'options',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'options' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this import job.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the target collection.
     */
    public function targetCollection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'target_collection_id');
    }

    /**
     * Check if job is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if job was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }
        return round(($this->processed_items / $this->total_items) * 100, 2);
    }

    /**
     * Mark as processing.
     */
    public function markProcessing(): void
    {
        $this->status = 'processing';
        $this->started_at = now();
        $this->save();
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark as failed.
     */
    public function markFailed(string $error = null): void
    {
        $this->status = 'failed';
        $this->completed_at = now();
        if ($error) {
            $this->appendError($error);
        }
        $this->save();
    }

    /**
     * Mark as cancelled.
     */
    public function markCancelled(): void
    {
        $this->status = 'cancelled';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Increment processed count.
     */
    public function incrementProcessed(bool $success = true, bool $skipped = false): void
    {
        $this->processed_items++;
        if ($skipped) {
            $this->skipped_count++;
        } elseif ($success) {
            $this->success_count++;
        } else {
            $this->failed_count++;
        }
        $this->save();
    }

    /**
     * Append error to log.
     */
    public function appendError(string $error): void
    {
        $log = $this->error_log ?? '';
        $log .= "[" . now()->toDateTimeString() . "] " . $error . "\n";
        $this->error_log = $log;
        $this->save();
    }

    /**
     * Get file extension.
     */
    public function getFileExtension(): string
    {
        return match ($this->file_type) {
            'html' => 'html',
            'json' => 'json',
            'csv' => 'csv',
            default => 'txt',
        };
    }

    /**
     * Scope to get active jobs.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Get estimated time remaining (in seconds).
     */
    public function getEstimatedTimeRemaining(): ?int
    {
        if (!$this->started_at || $this->processed_items === 0) {
            return null;
        }

        $elapsed = now()->diffInSeconds($this->started_at);
        $rate = $this->processed_items / $elapsed;
        
        if ($rate === 0) {
            return null;
        }

        $remaining = $this->total_items - $this->processed_items;
        return (int) ($remaining / $rate);
    }
}
