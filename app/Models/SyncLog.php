<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'direction',
        'peer_role',
        'table_name',
        'status',
        'records_exported',
        'records_imported',
        'records_created',
        'records_updated',
        'records_deleted',
        'records_skipped',
        'last_synced_at',
        'error_message',
        'details',
        'duration_ms',
        'triggered_by',
    ];

    protected $casts = [
        'details' => 'array',
        'last_synced_at' => 'datetime',
        'records_exported' => 'integer',
        'records_imported' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_deleted' => 'integer',
        'records_skipped' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function scopeForTable(Builder $query, string $table): Builder
    {
        return $query->where('table_name', $table);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeLatestRun(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
