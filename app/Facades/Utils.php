<?php

namespace App\Facades;

use App\Models\Language;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class Utils
{
    public static  function site_title() : string
    {
        return 'Sito Test';
    }

    public static function queryLog($models, $print = false, $return  = false) {
        Log::info($models->toSql());
        if (preg_match('/\?/', $models->toSql())) {
            $query = str_replace(array('?'), array('\'%s\''), $models->toSql());
            $query = vsprintf($query, $models->getBindings());
        } else {
            $query = $models->toSql();
        }
        if ($return) {
            return $query;
        }
        if (!$print) {
            Log::info($query);
        } else {
            echo $query;
        }
    }

    public static function price(float $price = null) : string
    {
        return isset($price) ? number_format($price, '2', ',', '.') . ' €' : '0,00 €';
    }

    public static function setting(string $name) : string {
        $settings = Setting::where('company_id', Session::get('company_id'))
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->parameter => $setting->content];
            })->toArray();
        return $settings[$name] ?? '';
    }

    public static function data(string $data = null) : string {
        return isset($data) ? Carbon::parse($data)->format('d/m/Y') : '';
    }

    public static function data_long(string $data = null) : string {
        return isset($data) ? Carbon::parse($data)->format('d/m/Y H:i') : '';
    }

    public static function data_text(string $data = null) : string {
        Carbon::setLocale('it');
        return isset($data) ? Carbon::parse($data)->isoFormat('D MMMM YYYY') : '';
    }

    public static function data_extra_long(string $data = null) : string {
        return isset($data) ? Carbon::parse($data)->format('d/m/Y H:i:s') : '';
    }

    public static function data_from_ita(string $data = null) : Carbon {
        return Carbon::createFromFormat('d/m/Y', $data);
    }

    public static function place_holders(string $text, array $fields = []) : string {
        if (count($fields) > 0) {
            foreach ($fields as $key => $field) {
                $text = preg_replace('/{'. $key . '}/', $field, $text);
            }
        }
        return $text;
    }
    public static function map_collection($items) : array {
        return $items->get()->map(function ($item) {
            return ['id' => $item->id, 'label' => $item->custom_label ?? $item->label];
        })->values()->toArray();
    }

    public static function key_value(array $elements): array
    {
        if (count($elements) == 0) {
            return [];
        }
        return collect($elements)->map(function ($item, $key) {
            return ['id' => $key, 'label' => $item];
        })->toArray();
    }

    public static function map_key(array $elements): array
    {
        if (count($elements) == 0) {
            return [];
        }
        return collect($elements)->map(function ($item) {
            return ['id' => $item, 'label' => $item];
        })->toArray();
    }

}
