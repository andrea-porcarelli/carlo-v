<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SyncWatermark extends Model
{
    protected $fillable = [
        'table_name',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public static function getWatermark(string $table): ?Carbon
    {
        $watermark = static::where('table_name', $table)->first();

        return $watermark?->last_synced_at;
    }

    public static function setWatermark(string $table, Carbon $timestamp): void
    {
        static::updateOrCreate(
            ['table_name' => $table],
            ['last_synced_at' => $timestamp],
        );
    }

    public static function resetWatermark(string $table): void
    {
        static::where('table_name', $table)->delete();
    }
}
