<?php

namespace App\Models;

use App\Models\Printer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a setting value by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            return self::where('key', $key)->first();
        });

        if (!$setting) {
            return $default;
        }

        return self::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value
     *
     * @param string $key
     * @param mixed $value
     * @param string $type
     * @param string|null $description
     * @return Setting
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): Setting
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'description' => $description,
            ]
        );

        // Clear cache
        Cache::forget("setting_{$key}");

        return $setting;
    }

    /**
     * Cast value to appropriate type
     *
     * @param string $value
     * @param string $type
     * @return mixed
     */
    protected static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer', 'int' => (int) $value,
            'decimal', 'float', 'double' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Get cover charge price
     *
     * @return float
     */
    public static function getCoverCharge(): float
    {
        return (float) self::get('cover_charge', 2.00);
    }

    /**
     * Get preconto printer ID
     *
     * @return int|null
     */
    public static function getPrecontoPrinterId(): ?int
    {
        $id = self::get('preconto_printer_id', null);
        return $id ? (int) $id : null;
    }

    /**
     * Get preconto printer
     *
     * @return Printer|null
     */
    public static function getPrecontoPrinter(): ?Printer
    {
        $printerId = self::getPrecontoPrinterId();
        if (!$printerId) {
            return null;
        }
        return Printer::find($printerId);
    }
}
