<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ErplyProductVariation extends Model
{
    use HasFactory;

    protected $fillable = [
        'erply_variation_id',
        'erply_matrix_id',
        'erply_product_id',
        'xero_variation_id',
        'name',
        'sku',
        'price',
        'cost',
        'attributes',
        'xero_account_code',
        'sync_status',
        'last_synced_at',
        'xero_sync_error',
        'erply_data',
        'xero_data'
    ];

    protected $casts = [
        'erply_data' => 'array',
        'xero_data' => 'array',
        'attributes' => 'array',
        'last_synced_at' => 'datetime',
        'sync_status' => 'string'
    ];

    public function matrix()
    {
        return $this->belongsTo(ErplyProduct::class, 'erply_matrix_id', 'erply_product_id');
    }

    public function syncToXero()
    {
        $this->update([
            'sync_status' => 'synced_to_xero',
            'last_synced_at' => now()
        ]);
    }

    public function markAsFailed($error = null)
    {
        $this->update([
            'sync_status' => 'failed',
            'xero_sync_error' => $error
        ]);
    }

    public function markAsSkipped()
    {
        $this->update([
            'sync_status' => 'skipped'
        ]);
    }

    public function getXeroMapping()
    {
        return $this->xero_variation_id;
    }

    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }

    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced_to_xero');
    }

    public function scopeFailed($query)
    {
        return $query->where('sync_status', 'failed');
    }

    public function scopeSkipped($query)
    {
        return $query->where('sync_status', 'skipped');
    }
}
