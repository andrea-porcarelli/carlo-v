<?php

namespace App\Models;

use App\Facades\Utils;
use App\Models\Printer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $setting = self::where('key', $key)->first();
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
     * Get the restaurant display name
     */
    public static function getRestaurantName(): string
    {
        return (string) self::get('restaurant_name', 'Carlo V');
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
    public static function getCashDrawerPrinterId(): ?int
    {
        $id = self::get('cash_drawer_printer_id', null);
        return $id ? (int) $id : null;
    }

    private static function getPrinterBySettingKey(string $idKey): ?Printer
    {
        Log::info(__METHOD__ . ': ' . __LINE__, ['id' => $idKey]);
        $id = self::get($idKey, null);
        return $id ? Printer::find((int) $id) : null;
    }

    public static function getPrecontoPrinter(): ?Printer
    {
        return self::getPrinterBySettingKey('preconto_printer_id');
    }

    public static function getCashDrawerPrinter(): ?string
    {
        $rawSetting = self::where('key', 'cash_drawer_printer_id')->first();
        return $rawSetting->value ?? null;
    }

    /**
     * Integrazione cassa automatica: 'none' | 'printer'
     */
    public static function getCashDrawerIntegration(): string
    {
        return (string) self::get('cash_drawer_integration', 'none');
    }

    public static function isCashDrawerEnabled(): bool
    {
        return self::getCashDrawerIntegration() === 'printer';
    }

    /**
     * Integrazione POS attiva: 'none' | 'revolut'
     */
    public static function getPosIntegration(): string
    {
        return (string) self::get('pos_integration', 'none');
    }

    public static function isRevolutEnabled(): bool
    {
        return self::getPosIntegration() === 'revolut';
    }

    /**
     * @return array{environment:string, api_key:string, location_id:string, webhook_secret:string, timeout_seconds:int, mock_mode:bool}
     */
    public static function getRevolutConfig(): array
    {
        return [
            'environment'      => (string) self::get('revolut.environment', 'sandbox'),
            'api_key'          => (string) self::get('revolut.api_key', ''),
            'location_id'      => (string) self::get('revolut.location_id', ''),
            'webhook_secret'   => (string) self::get('revolut.webhook_secret', ''),
            'timeout_seconds'  => (int) self::get('revolut.timeout_seconds', 90),
            'mock_mode'        => (bool) self::get('revolut.mock_mode', false),
        ];
    }

    /**
     * Mock mode è attivo solo se la flag è ON E siamo in sandbox.
     * Doppio guard per evitare che venga lasciato attivo per errore in produzione.
     */
    public static function isRevolutMockMode(): bool
    {
        $cfg = self::getRevolutConfig();
        return $cfg['mock_mode'] && $cfg['environment'] === 'sandbox';
    }
}
