<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SyncStatus extends Model
{
    protected $fillable = [
        'entity_type',
        'last_sync_date',
        'last_sync_status',
        'total_records_synced',
        'error_message'
    ];

    protected $casts = [
        'last_sync_date' => 'datetime',
        'total_records_synced' => 'integer'
    ];

    /**
     * Get or create sync status for an entity type
     */
    public static function getOrCreate(string $entityType): self
    {
        return self::firstOrCreate(
            ['entity_type' => $entityType],
            [
                'last_sync_status' => 'in_progress',
                'total_records_synced' => 0
            ]
        );
    }

    /**
     * Mark sync as successful
     */
    public function markSuccess(int $totalRecords = 0): void
    {
        $this->update([
            'last_sync_status' => 'success',
            'last_sync_date' => Carbon::now(),
            'total_records_synced' => $totalRecords,
            'error_message' => null
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'last_sync_status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Mark sync as in progress
     */
    public function markInProgress(): void
    {
        $this->update([
            'last_sync_status' => 'in_progress',
            'error_message' => null
        ]);
    }

    /**
     * Get formatted last sync date for Erply API
     */
    public function getChangedSinceDate(): ?string
    {
        return $this->last_sync_date 
            ? $this->last_sync_date->format('Y-m-d H:i:s') 
            : null;
    }

    /**
     * Check if sync is needed
     */
    public function needsSync(): bool
    {
        if ($this->last_sync_status === 'failed') {
            return true;
        }

        if (!$this->last_sync_date) {
            return true;
        }

        // If last sync was more than 1 hour ago, sync again
        return $this->last_sync_date->lt(Carbon::now()->subHour());
    }
}
